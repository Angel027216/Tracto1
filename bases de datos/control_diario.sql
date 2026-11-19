-- Esquema para Control diario.
-- Requiere MySQL 8.0.16 o posterior. Selecciona la base de datos de Tracto1
-- antes de ejecutar este archivo. Ejecutar una sola vez.
-- La tabla usuarios existente debe tener la llave primaria usuarios.id.

CREATE TABLE control_diario_roles (
    id_rol TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id_rol),
    UNIQUE KEY uq_control_diario_rol_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO control_diario_roles (codigo, nombre) VALUES
    ('SUPERPATRON', 'Administrador Superpatrón'),
    ('TRABAJADOR', 'Administrador trabajador');

CREATE TABLE control_diario_usuarios_roles (
    usuario_id INT NOT NULL,
    rol_id TINYINT UNSIGNED NOT NULL,
    asignado_por INT NULL,
    asignado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, rol_id),
    KEY idx_control_diario_roles_rol (rol_id),
    CONSTRAINT fk_control_diario_usuario_rol_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_control_diario_usuario_rol
        FOREIGN KEY (rol_id) REFERENCES control_diario_roles (id_rol)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_control_diario_rol_asignado_por
        FOREIGN KEY (asignado_por) REFERENCES usuarios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Concede Superpatrón a las cuentas existentes durante esta instalación.
-- Las asignaciones iniciales no tienen un actor dentro de la aplicación.
INSERT INTO control_diario_usuarios_roles (usuario_id, rol_id, asignado_por)
SELECT u.id, r.id_rol, NULL
FROM usuarios u
INNER JOIN control_diario_roles r ON r.codigo = 'SUPERPATRON';

CREATE TABLE tarifas_diarias (
    id_tarifa SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo_unidad ENUM(
        'TRAILER_SENCILLO',
        'TORTON',
        'TRAILER_FULL_DOBLE'
    ) NOT NULL,
    categoria ENUM('ESPECIAL', 'CASA', 'GENERAL') NOT NULL,
    descripcion VARCHAR(100) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_tarifa),
    UNIQUE KEY uq_tarifa_tipo_categoria (tipo_unidad, categoria),
    UNIQUE KEY uq_tarifa_relacion (id_tarifa, tipo_unidad, categoria),
    CONSTRAINT chk_tarifa_monto CHECK (monto >= 0),
    CONSTRAINT chk_tarifa_categoria CHECK (
        (tipo_unidad = 'TRAILER_SENCILLO'
            AND categoria IN ('ESPECIAL', 'CASA'))
        OR
        (tipo_unidad = 'TORTON' AND categoria = 'GENERAL')
        OR
        (tipo_unidad = 'TRAILER_FULL_DOBLE'
            AND categoria IN ('GENERAL', 'CASA'))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tarifas_diarias (tipo_unidad, categoria, descripcion, monto) VALUES
    ('TRAILER_SENCILLO', 'ESPECIAL', 'Tráiler sencillo - Especial', 150.00),
    ('TRAILER_SENCILLO', 'CASA', 'Tráiler sencillo - Casa', 100.00),
    ('TORTON', 'GENERAL', 'Tortón en general', 150.00),
    ('TRAILER_FULL_DOBLE', 'GENERAL', 'Tráiler full / doble remolque', 300.00),
    ('TRAILER_FULL_DOBLE', 'CASA', 'Tráiler full / doble remolque de casa', 250.00);

CREATE TABLE boletos_diarios (
    folio BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    operador_empresa VARCHAR(150) NOT NULL,
    fecha_hora_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_hora_salida DATETIME NULL DEFAULT NULL,
    marca_trailer VARCHAR(80) NOT NULL,
    placa_tracto VARCHAR(20) NOT NULL,
    placa_caja_1 VARCHAR(20) NOT NULL,
    placa_caja_2 VARCHAR(20) NULL DEFAULT NULL,
    tipo_unidad ENUM(
        'TRAILER_SENCILLO',
        'TORTON',
        'TRAILER_FULL_DOBLE'
    ) NOT NULL,
    categoria_tarifa ENUM('ESPECIAL', 'CASA', 'GENERAL') NOT NULL,
    id_tarifa SMALLINT UNSIGNED NOT NULL,
    descripcion_tarifa_aplicada VARCHAR(100) NOT NULL,
    monto_tarifa_aplicada DECIMAL(10,2) NOT NULL,
    estado ENUM('ACTIVO', 'COBRADO') NOT NULL DEFAULT 'ACTIVO',
    PRIMARY KEY (folio),
    KEY idx_boleto_fecha_entrada (fecha_hora_entrada),
    KEY idx_boleto_fecha_salida (fecha_hora_salida),
    KEY idx_boleto_placa_tracto (placa_tracto),
    KEY idx_boleto_placa_caja_1 (placa_caja_1),
    KEY idx_boleto_placa_caja_2 (placa_caja_2),
    CONSTRAINT fk_boleto_tarifa
        FOREIGN KEY (id_tarifa, tipo_unidad, categoria_tarifa)
        REFERENCES tarifas_diarias (id_tarifa, tipo_unidad, categoria)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_boleto_estado_salida CHECK (
        (estado = 'ACTIVO' AND fecha_hora_salida IS NULL)
        OR
        (estado = 'COBRADO' AND fecha_hora_salida IS NOT NULL)
    ),
    CONSTRAINT chk_boleto_salida_posterior CHECK (
        fecha_hora_salida IS NULL
        OR fecha_hora_salida >= fecha_hora_entrada
    ),
    CONSTRAINT chk_boleto_caja_2 CHECK (
        (tipo_unidad = 'TRAILER_FULL_DOBLE' AND placa_caja_2 IS NOT NULL)
        OR
        (tipo_unidad <> 'TRAILER_FULL_DOBLE' AND placa_caja_2 IS NULL)
    ),
    CONSTRAINT chk_boleto_monto CHECK (monto_tarifa_aplicada >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fondo_cambio (
    id_fondo TINYINT UNSIGNED NOT NULL DEFAULT 1,
    saldo_inicial DECIMAL(10,2) NOT NULL,
    fecha_apertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT NOT NULL,
    PRIMARY KEY (id_fondo),
    CONSTRAINT chk_fondo_unico CHECK (id_fondo = 1),
    CONSTRAINT chk_fondo_saldo CHECK (saldo_inicial >= 0),
    CONSTRAINT fk_fondo_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fondo_egresos (
    id_egreso BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_fondo TINYINT UNSIGNED NOT NULL DEFAULT 1,
    concepto VARCHAR(150) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT NOT NULL,
    PRIMARY KEY (id_egreso),
    KEY idx_fondo_egreso_fecha (fecha_hora),
    CONSTRAINT chk_fondo_egreso_monto CHECK (monto > 0),
    CONSTRAINT fk_egreso_fondo
        FOREIGN KEY (id_fondo) REFERENCES fondo_cambio (id_fondo)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_egreso_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE turnos (
    id_turno BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fecha_hora_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_hora_fin DATETIME NULL,
    usuario_inicio INT NOT NULL,
    usuario_cierre INT NULL,
    estado ENUM('ABIERTO', 'CERRADO') NOT NULL DEFAULT 'ABIERTO',
    PRIMARY KEY (id_turno),
    KEY idx_turno_inicio (fecha_hora_inicio),
    CONSTRAINT fk_turno_usuario_inicio
        FOREIGN KEY (usuario_inicio) REFERENCES usuarios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_turno_usuario_cierre
        FOREIGN KEY (usuario_cierre) REFERENCES usuarios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_turno_estado CHECK (
        (estado = 'ABIERTO' AND fecha_hora_fin IS NULL AND usuario_cierre IS NULL)
        OR
        (estado = 'CERRADO' AND fecha_hora_fin IS NOT NULL AND usuario_cierre IS NOT NULL)
    ),
    CONSTRAINT chk_turno_fechas CHECK (
        fecha_hora_fin IS NULL OR fecha_hora_fin >= fecha_hora_inicio
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE materiales (
    id_material SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(80) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_material),
    UNIQUE KEY uq_material_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO materiales (nombre) VALUES
    ('Lámparas'),
    ('Radios');

CREATE TABLE turno_materiales (
    id_turno BIGINT UNSIGNED NOT NULL,
    id_material SMALLINT UNSIGNED NOT NULL,
    cantidad_recibida INT UNSIGNED NOT NULL,
    cantidad_entregada INT UNSIGNED NULL,
    nota VARCHAR(255) NULL,
    PRIMARY KEY (id_turno, id_material),
    CONSTRAINT fk_turno_material_turno
        FOREIGN KEY (id_turno) REFERENCES turnos (id_turno)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_turno_material_material
        FOREIGN KEY (id_material) REFERENCES materiales (id_material)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_turno_material_nota CHECK (
        cantidad_entregada IS NULL
        OR cantidad_entregada = cantidad_recibida
        OR (nota IS NOT NULL AND CHAR_LENGTH(TRIM(nota)) > 0)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE control_diario_auditoria (
    id_auditoria BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    registro_id VARCHAR(100) NOT NULL,
    accion VARCHAR(30) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valores_anteriores JSON NULL,
    valores_nuevos JSON NULL,
    PRIMARY KEY (id_auditoria),
    KEY idx_auditoria_entidad_registro (entidad, registro_id),
    KEY idx_auditoria_fecha (fecha_hora),
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER trg_boleto_folio_inmutable
BEFORE UPDATE ON boletos_diarios
FOR EACH ROW
BEGIN
    IF NEW.folio <> OLD.folio THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El folio del boleto no se puede modificar';
    END IF;
END$$
DELIMITER ;
