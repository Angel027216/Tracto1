<?php
class ControlDiarioController
{
    private ControlDiarioModel $modelo;

    public function __construct(ControlDiarioModel $modelo)
    {
        $this->modelo = $modelo;
    }

    public function registrarEntrada(array $datos, int $usuarioId): array
    {
        $texto = static function (string $campo) use ($datos): string {
            $valor = $datos[$campo] ?? '';
            return is_string($valor) ? trim($valor) : '';
        };

        $boleto = [
            'operador_empresa' => $texto('operador_empresa'),
            'marca_trailer' => $texto('marca_trailer'),
            'placa_tracto' => strtoupper($texto('placa_tracto')),
            'placa_caja_1' => strtoupper($texto('placa_caja_1')),
            'placa_caja_2' => strtoupper($texto('placa_caja_2')),
            'tipo_unidad' => $texto('tipo_unidad'),
            'categoria_tarifa' => $texto('categoria_tarifa'),
        ];

        $errores = [];
        $longitudes = [
            'operador_empresa' => 150,
            'marca_trailer' => 80,
            'placa_tracto' => 20,
            'placa_caja_1' => 20,
            'placa_caja_2' => 20,
        ];

        foreach (['operador_empresa', 'marca_trailer', 'placa_tracto', 'placa_caja_1'] as $campo) {
            if ($boleto[$campo] === '') {
                $errores[] = 'Completa todos los campos obligatorios.';
                break;
            }
        }

        foreach ($longitudes as $campo => $maximo) {
            if (mb_strlen($boleto[$campo], 'UTF-8') > $maximo) {
                $errores[] = 'Uno de los campos supera la longitud permitida.';
                break;
            }
        }

        $categoriasPermitidas = [
            'TRAILER_SENCILLO' => ['ESPECIAL', 'CASA'],
            'TORTON' => ['GENERAL'],
            'TRAILER_FULL_DOBLE' => ['GENERAL', 'CASA'],
        ];
        if (
            !isset($categoriasPermitidas[$boleto['tipo_unidad']])
            || !in_array($boleto['categoria_tarifa'], $categoriasPermitidas[$boleto['tipo_unidad']], true)
        ) {
            $errores[] = 'Selecciona un tipo de unidad y una tarifa válidos.';
        }

        $esFull = $boleto['tipo_unidad'] === 'TRAILER_FULL_DOBLE';
        if ($esFull && $boleto['placa_caja_2'] === '') {
            $errores[] = 'La placa de caja 2 es obligatoria para full o doble remolque.';
        } elseif (!$esFull && $boleto['placa_caja_2'] !== '') {
            $errores[] = 'La placa de caja 2 solo aplica para full o doble remolque.';
        }

        if ($errores) {
            return ['tipo' => 'danger', 'mensaje' => implode(' ', array_unique($errores))];
        }

        try {
            $folio = $this->modelo->registrarEntrada($boleto, $usuarioId);
        } catch (DomainException $e) {
            return ['tipo' => 'warning', 'mensaje' => $e->getMessage()];
        }

        return [
            'tipo' => 'success',
            'mensaje' => 'Entrada registrada. El boleto quedó activo.',
            'folio' => $folio,
        ];
    }

    public function registrarSalida(array $datos, int $usuarioId): array
    {
        $valorFolio = $datos['folio'] ?? null;
        $folio = is_string($valorFolio) || is_int($valorFolio)
            ? filter_var($valorFolio, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if ($folio === false) {
            return ['tipo' => 'danger', 'mensaje' => 'Selecciona un folio válido para registrar la salida.'];
        }

        try {
            $this->modelo->registrarSalida((int) $folio, $usuarioId);
        } catch (DomainException $e) {
            return ['tipo' => 'warning', 'mensaje' => $e->getMessage()];
        }

        return [
            'tipo' => 'success',
            'mensaje' => 'Salida y cobro registrados correctamente.',
            'folio' => (int) $folio,
        ];
    }

    public function guardarSaldoInicial(array $datos, int $usuarioId): array
    {
        $monto = $this->normalizarMonto($datos['saldo_inicial'] ?? null);
        if ($monto === null) {
            return ['tipo' => 'danger', 'mensaje' => 'Ingresa un saldo inicial válido, de hasta dos decimales.'];
        }

        try {
            $this->modelo->guardarSaldoInicial($monto, $usuarioId);
        } catch (DomainException $e) {
            return ['tipo' => 'warning', 'mensaje' => $e->getMessage()];
        }

        return ['tipo' => 'success', 'mensaje' => 'El saldo inicial del fondo se guardó correctamente.'];
    }

    public function registrarEgresoFondo(array $datos, int $usuarioId): array
    {
        $conceptoValor = $datos['concepto'] ?? '';
        $concepto = is_string($conceptoValor) ? trim($conceptoValor) : '';
        if ($concepto === '') {
            return ['tipo' => 'danger', 'mensaje' => 'Ingresa el concepto del egreso.'];
        }
        if (mb_strlen($concepto, 'UTF-8') > 150) {
            return ['tipo' => 'danger', 'mensaje' => 'El concepto no puede superar los 150 caracteres.'];
        }

        $monto = $this->normalizarMonto($datos['monto'] ?? null);
        if ($monto === null || $monto === '0.00') {
            return ['tipo' => 'danger', 'mensaje' => 'Ingresa un monto mayor que cero, con hasta dos decimales.'];
        }

        $fechaValor = $datos['fecha_hora'] ?? '';
        $fechaLocal = is_string($fechaValor) ? trim($fechaValor) : '';
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $fechaLocal);
        $erroresFecha = DateTimeImmutable::getLastErrors();
        if (
            !$fecha
            || ($erroresFecha !== false && ($erroresFecha['warning_count'] > 0 || $erroresFecha['error_count'] > 0))
            || $fecha->format('Y-m-d\TH:i') !== $fechaLocal
        ) {
            return ['tipo' => 'danger', 'mensaje' => 'Selecciona una fecha y hora válidas para el egreso.'];
        }

        try {
            $idEgreso = $this->modelo->registrarEgresoFondo(
                $concepto,
                $monto,
                $fecha->format('Y-m-d H:i:s'),
                $usuarioId
            );
        } catch (DomainException $e) {
            return ['tipo' => 'warning', 'mensaje' => $e->getMessage()];
        }

        return [
            'tipo' => 'success',
            'mensaje' => 'Egreso registrado correctamente.',
            'id_egreso' => $idEgreso,
        ];
    }

    public function iniciarTurno(array $datos, int $usuarioId): array
    {
        $lamparas = $this->normalizarCantidad($datos['lamparas_recibidas'] ?? null);
        $radios = $this->normalizarCantidad($datos['radios_recibidos'] ?? null);
        if ($lamparas === null || $radios === null) {
            return ['tipo' => 'danger', 'mensaje' => 'Ingresa cantidades enteras no negativas para lámparas y radios.'];
        }

        try {
            $idTurno = $this->modelo->iniciarTurno([
                'lamparas' => $lamparas,
                'radios' => $radios,
            ], $usuarioId);
        } catch (DomainException $e) {
            return ['tipo' => 'warning', 'mensaje' => $e->getMessage()];
        }

        return [
            'tipo' => 'success',
            'mensaje' => 'Turno iniciado y material recibido registrado.',
            'id_turno' => $idTurno,
        ];
    }

    public function cerrarTurno(array $datos, int $usuarioId): array
    {
        $lamparas = $this->normalizarCantidad($datos['lamparas_entregadas'] ?? null);
        $radios = $this->normalizarCantidad($datos['radios_entregados'] ?? null);
        if ($lamparas === null || $radios === null) {
            return ['tipo' => 'danger', 'mensaje' => 'Ingresa cantidades enteras no negativas para lámparas y radios.'];
        }

        $notaLamparasValor = $datos['nota_lamparas'] ?? '';
        $notaRadiosValor = $datos['nota_radios'] ?? '';
        $notaLamparas = is_string($notaLamparasValor) ? trim($notaLamparasValor) : '';
        $notaRadios = is_string($notaRadiosValor) ? trim($notaRadiosValor) : '';
        if (
            mb_strlen($notaLamparas, 'UTF-8') > 255
            || mb_strlen($notaRadios, 'UTF-8') > 255
        ) {
            return ['tipo' => 'danger', 'mensaje' => 'Cada nota puede tener hasta 255 caracteres.'];
        }

        try {
            $this->modelo->cerrarTurno([
                'lamparas' => $lamparas,
                'radios' => $radios,
                'nota_lamparas' => $notaLamparas,
                'nota_radios' => $notaRadios,
            ], $usuarioId);
        } catch (DomainException $e) {
            return ['tipo' => 'warning', 'mensaje' => $e->getMessage()];
        }

        return ['tipo' => 'success', 'mensaje' => 'Turno cerrado y material entregado registrado.'];
    }

    private function normalizarMonto($valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        $valor = trim($valor);
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $valor)) {
            return null;
        }

        [$pesos, $centavos] = array_pad(explode('.', $valor, 2), 2, '');
        $pesos = ltrim($pesos, '0');
        $pesos = $pesos === '' ? '0' : $pesos;
        if (strlen($pesos) > 8) {
            return null;
        }

        return $pesos . '.' . str_pad($centavos, 2, '0');
    }

    private function normalizarCantidad($valor): ?int
    {
        if (!is_string($valor) && !is_int($valor)) {
            return null;
        }

        $valor = trim((string) $valor);
        if ($valor === '' || !ctype_digit($valor)) {
            return null;
        }

        $cantidad = ltrim($valor, '0');
        $cantidad = $cantidad === '' ? '0' : $cantidad;
        if (
            strlen($cantidad) > 10
            || (strlen($cantidad) === 10 && strcmp($cantidad, '4294967295') > 0)
        ) {
            return null;
        }

        return (int) $cantidad;
    }
}
