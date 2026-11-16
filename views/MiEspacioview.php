<?php 
require_once 'views/includes/SideBarView.php'; 
require_once 'views/includes/NavBarView.php';

$apartadoActual = $apartados[0] ?? null;
$numeroEspacioActual = (int) ($apartadoActual['numero_espacio'] ?? 0);
$estadoEspacioActual = (string) ($apartadoActual['estado'] ?? 'sin_asignar');
$espaciosPorNumero = [];
foreach ($espacios as $espacio) {
    $espaciosPorNumero[(int) $espacio['numero_espacio']] = $espacio;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Mi Espacio</title>

    <link rel="stylesheet" href="public/assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="public/assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="public/assets/css/demo_1/style.css">

</head>

<body>

<div class="container-scroller">

    <?=NavBarView();?>

    <div class="container-fluid page-body-wrapper">

        <?=SideBarView();?>

        <div class="main-panel">

            <div class="content-wrapper">

                <!-- TITULO -->
                <div class="page-header">

                    <h3 class="page-title">

                        <i class="mdi mdi-parking text-dark mr-2"></i>
                        Mi Espacio

                    </h3>

                </div>

                <!-- CARD -->
                <div class="card shadow-sm border-0 rounded-4">

                    <div class="card-body">

                        <h2 class="text-center font-weight-bold mb-5">
                            Mi Espacio
                        </h2>

                        <!-- MAPA -->
                        <div class="d-flex flex-wrap justify-content-center mb-5">

                            <?php for($i = 1; $i <= 60; $i++): ?>
                                <?php
                                $espacioMapa = $espaciosPorNumero[$i] ?? null;
                                $ocupado = $espacioMapa && ($espacioMapa['estado'] ?? '') !== 'disponible';
                                $esActual = $i === $numeroEspacioActual;
                                ?>

                                <button
                                    class="btn m-1 espacio-btn <?= $esActual ? 'btn-warning' : ($ocupado ? 'btn-danger' : 'btn-secondary') ?>"
                                    onclick="seleccionarEspacio(<?=$i;?>)"
                                    id="espacio<?=$i;?>"
                                    data-id-espacio="<?= (int) ($espacioMapa['id_espacio'] ?? 0) ?>"
                                    data-disponible="<?= !$ocupado || $esActual ? '1' : '0' ?>"
                                    <?= $ocupado && !$esActual ? 'disabled' : '' ?>>

                                    <?=$i;?>

                                </button>

                            <?php endfor; ?>

                        </div>

                        <!-- TABLA -->
                        <div class="table-responsive">

                            <table class="table table-bordered text-center">

                                <thead class="bg-light">

                                    <tr>

                                        <th colspan="2"
                                            class="text-primary font-weight-bold">

                                            Detalles Del Espacio

                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <tr>

                                        <td class="font-weight-bold">
                                            Número de Espacio
                                        </td>

                                        <td>

                                            <input type="text"
                                                   id="numeroEspacio"
                                                   class="form-control text-center"
                                                   value="<?= $numeroEspacioActual ?: '' ?>"
                                                   disabled>

                                        </td>

                                    </tr>

                                    <tr>

                                        <td class="font-weight-bold">
                                            Terreno Asignado
                                        </td>

                                        <td>

                                            <input type="text"
                                                   id="terreno"
                                                   class="form-control text-center"
                                                   value="<?= e((string) ($apartadoActual['terreno'] ?? '')) ?>"
                                                   disabled>

                                        </td>

                                    </tr>

                                    <tr>

                                        <td class="font-weight-bold">
                                            Estado
                                        </td>

                                        <td>

                                            <label id="estadoBadge"
                                                                    class="badge <?= $estadoEspacioActual === 'activo' ? 'badge-success' : 'badge-secondary' ?> p-2">

                                                                <?= e($estadoEspacioActual === 'activo' ? 'En uso' : 'Sin asignar') ?>

                                            </label>

                                        </td>

                                    </tr>

                                </tbody>

                            </table>

                        </div>

                        <!-- BOTONES -->
                        <div class="text-center mt-4">

                            <button class="btn btn-primary rounded-pill mr-2"
                                    onclick="editarEspacio()">

                                <i class="mdi mdi-pencil"></i>
                                Editar Espacio

                            </button>

                            <form method="POST" action="index.php?menu=miespacio" id="formEspacio" class="d-inline">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="accion" value="guardar">
                                <input type="hidden" name="id_camion" value="<?= $id_camion_seleccionado ?>">
                                <input type="hidden" name="id_espacio" id="idEspacioSeleccionado" value="<?= (int) ($espaciosPorNumero[$numeroEspacioActual]['id_espacio'] ?? 0) ?>">
                                <input type="hidden" name="id_apartado" value="<?= (int) ($apartadoActual['id_apartado'] ?? 0) ?>">
                                <input type="hidden" name="fecha_inicio" value="<?= date('Y-m-d') ?>">
                                <input type="hidden" name="precio_total" value="2500">
                                <button type="submit" id="btnGuardarEspacio" class="btn btn-success rounded-pill mr-2" <?= $apartadoActual ? 'disabled' : '' ?>>
                                    <i class="mdi mdi-content-save"></i>
                                    Guardar Espacio
                                </button>
                            </form>

                            <button class="btn btn-danger rounded-pill"
                                    onclick="liberarEspacio()" <?= $apartadoActual ? '' : 'disabled' ?>>

                                <i class="mdi mdi-close-circle"></i>
                                Liberar Espacio

                            </button>

                        </div>

                    </div>

                </div>

            </div>

            <!-- SCRIPT -->
            <script>

            let modoEdicion = <?= $apartadoActual ? 'false' : 'true' ?>;
            let espacioActual = <?= $numeroEspacioActual ?: 0 ?>;

            document.getElementById('formEspacio').addEventListener('submit', async function(event) {
                event.preventDefault();

                const respuesta = await fetch(this.action, {
                    method: 'POST',
                    body: new FormData(this),
                    headers: { 'Accept': 'application/json' }
                });
                const resultado = await respuesta.json();

                alert(resultado.mensaje);
                if (resultado.exito) {
                    window.location.reload();
                }
            });

            function editarEspacio() {

                modoEdicion = true;

                let botones = document.querySelectorAll('.espacio-btn');

                botones.forEach(function(btn) {
                    btn.disabled = btn.dataset.disponible !== '1';

                });

                document.getElementById('btnGuardarEspacio').disabled = false;

            }

            function seleccionarEspacio(numero) {

                if(!modoEdicion) return;

                const espacioAnterior = document.getElementById("espacio" + espacioActual);
                if (espacioAnterior) {
                    espacioAnterior.classList.remove("btn-warning");
                    espacioAnterior.classList.add("btn-secondary");
                }

                // nuevo espacio
                espacioActual = numero;

                const espacioSeleccionado = document.getElementById("espacio" + numero);
                espacioSeleccionado.classList.remove("btn-secondary", "btn-danger");

                espacioSeleccionado.classList.add("btn-warning");

                // actualizar tabla
                document.getElementById("numeroEspacio").value = numero;
                document.getElementById("idEspacioSeleccionado").value = espacioSeleccionado.dataset.idEspacio;

                // cambiar terreno automático
                let terreno = <?= json_encode(array_map(static fn (array $espacio): array => [
                    'numero' => (int) $espacio['numero_espacio'],
                    'terreno' => (string) $espacio['terreno'],
                ], $espacios)) ?>.find(item => item.numero === numero)?.terreno || '';

                document.getElementById("terreno").value = terreno;

            }

            function liberarEspacio() {

                const form = document.getElementById('formEspacio');
                const datos = new FormData(form);
                datos.set('accion', 'finalizar');

                fetch(form.action, {
                    method: 'POST',
                    body: datos,
                    headers: { 'Accept': 'application/json' }
                }).then(respuesta => respuesta.json()).then(resultado => {
                    alert(resultado.mensaje);
                    if (resultado.exito) {
                        window.location.reload();
                    }
                });

            }

            </script>

            <!-- FOOTER -->
            <footer class="footer">

                <div class="footer-inner-wraper">

                    <div class="d-flex justify-content-start">

                        <span class="text-muted text-left d-block">Copyright © 2025 Tracto Pensión San Leonardo. Todos los derechos reservados.</span>

                    </div>

                </div>

            </footer>

        </div>

    </div>

</div>

<!-- plugins:js -->
<script src="public/assets/vendors/js/vendor.bundle.base.js"></script>
<!-- inject:js -->
<script src="public/assets/js/off-canvas.js"></script>
<script src="public/assets/js/hoverable-collapse.js"></script>
<script src="public/assets/js/misc.js"></script>
<script src="public/assets/js/settings.js"></script>
<script src="public/assets/js/todolist.js"></script>

</body>
</html>