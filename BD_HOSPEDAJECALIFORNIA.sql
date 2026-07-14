-- =========================================================
-- BASE DE DATOS: hospedaje_california
-- Script actualizado para MySQL Workbench
-- =========================================================
DROP DATABASE IF EXISTS hospedaje_california;
CREATE DATABASE hospedaje_california;
USE hospedaje_california;

-- =========================================================
-- TABLAS CATÁLOGO
-- =========================================================

CREATE TABLE roles (
    id      TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(30)         NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

INSERT INTO roles (nombre)
VALUES ('gerente'), ('recepcionista');

CREATE TABLE estados_habitacion (
    id      TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(30)         NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

INSERT INTO estados_habitacion (nombre)
VALUES ('disponible'), ('reservada'), ('ocupada'), ('limpieza'), ('mantenimiento');

CREATE TABLE estados_reserva (
    id      TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(30)         NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

INSERT INTO estados_reserva (nombre)
VALUES ('pendiente'), ('activa'), ('finalizada'), ('cancelada');

CREATE TABLE metodos_pago (
    id      TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(30)         NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

INSERT INTO metodos_pago (nombre)
VALUES ('efectivo'), ('transferencia'), ('yape'), ('plin');

CREATE TABLE tipos_pago (
    id      TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(30)         NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

INSERT INTO tipos_pago (nombre)
VALUES ('adelanto'), ('pago final'), ('ingreso temprano'), ('extension');

-- Catálogo: tipos de comprobante (boleta, factura, etc.)
CREATE TABLE tipos_comprobante (
    id      TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(30)         NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

INSERT INTO tipos_comprobante (nombre)
VALUES ('boleta'), ('factura');

-- =========================================================
-- USERS
-- =========================================================

CREATE TABLE users (
    id          BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name        VARCHAR(255)        NOT NULL,
    email       VARCHAR(255)        NOT NULL UNIQUE,
    rol_id      TINYINT UNSIGNED    NOT NULL DEFAULT 2,
    activo      TINYINT(1)          NOT NULL DEFAULT 1,
    password    VARCHAR(255)        NOT NULL,
    created_at  TIMESTAMP           NULL,
    updated_at  TIMESTAMP           NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_users_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- =========================================================
-- HABITACIONES
-- =========================================================

CREATE TABLE tipos_habitacion (
    id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(50)         NOT NULL UNIQUE,
    precio_hora     DECIMAL(8,2)        NOT NULL,
    precio_noche    DECIMAL(8,2)        NOT NULL,
    max_huespedes   TINYINT             NOT NULL DEFAULT 1,
    descripcion     VARCHAR(255)        NULL,
    activo          TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      TIMESTAMP           NULL,
    updated_at      TIMESTAMP           NULL,
    PRIMARY KEY (id)
);

CREATE TABLE habitaciones (
    numero      SMALLINT UNSIGNED   NOT NULL,
    tipo_id     INT UNSIGNED        NOT NULL,
    estado_id   TINYINT UNSIGNED    NOT NULL,
    activo      TINYINT(1)          NOT NULL DEFAULT 1,
    created_at  TIMESTAMP           NULL,
    updated_at  TIMESTAMP           NULL,
    PRIMARY KEY (numero),
    CONSTRAINT fk_habitacion_tipo
        FOREIGN KEY (tipo_id) REFERENCES tipos_habitacion(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_habitacion_estado
        FOREIGN KEY (estado_id) REFERENCES estados_habitacion(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- =========================================================
-- HUESPEDES
-- =========================================================

CREATE TABLE huespedes (
    num_doc     VARCHAR(20)         NOT NULL,
    nombre      VARCHAR(100)        NOT NULL,
    telefono    VARCHAR(15)         NULL,
    activo      TINYINT(1)          NOT NULL DEFAULT 1,
    created_at  TIMESTAMP           NULL,
    updated_at  TIMESTAMP           NULL,
    PRIMARY KEY (num_doc),
    CONSTRAINT uk_huespedes_telefono UNIQUE (telefono)
);

-- =========================================================
-- COMPROBANTES
-- Se crea ANTES de reservas porque reservas la referencia.
-- Ya no depende de pagos ni de reservas (sin ciclo).
-- =========================================================

CREATE TABLE comprobantes (
    id              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    serie           VARCHAR(10)         NOT NULL,
    numero          VARCHAR(15)         NOT NULL,
    fecha_emision   DATETIME            NOT NULL,
    tipo_id         TINYINT UNSIGNED    NOT NULL,
    ruc             VARCHAR(11)         NULL,
    razon_social    VARCHAR(150)        NULL,
    created_at      TIMESTAMP           NULL,
    updated_at      TIMESTAMP           NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_serie_numero (serie, numero),
    CONSTRAINT fk_comp_tipo
        FOREIGN KEY (tipo_id) REFERENCES tipos_comprobante(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- =========================================================
-- RESERVAS
-- Cambios: se agrega huesped_principal (sin FK) y
-- comprobante_id (1 comprobante general por reserva, NULL
-- hasta que se emite al finalizar el hospedaje).
-- =========================================================

CREATE TABLE reservas (
    id                  BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    usuario_id          BIGINT UNSIGNED     NOT NULL,
    huesped_principal   VARCHAR(100)        NOT NULL,
    comprobante_id      BIGINT UNSIGNED     NULL,
    fecha_entrada       DATETIME            NOT NULL,
    fecha_salida        DATETIME            NOT NULL,
    estado_id           TINYINT UNSIGNED    NOT NULL,
    es_por_horas        TINYINT(1)          NOT NULL DEFAULT 0,
    costo_total         DECIMAL(8,2)        NULL,
    monto_recargo	 	DECIMAL(8,2) 		NOT NULL DEFAULT 0,
    saldo_pendiente     DECIMAL(8,2)        NULL,
    observacion         VARCHAR(255)        NULL,
    created_at          TIMESTAMP           NULL,
    updated_at          TIMESTAMP           NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_reservas_usuario
        FOREIGN KEY (usuario_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservas_estado
        FOREIGN KEY (estado_id) REFERENCES estados_reserva(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservas_comprobante
        FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- =========================================================
-- RESERVA_HUESPEDES
-- =========================================================

CREATE TABLE reserva_huespedes (
    reserva_id      BIGINT UNSIGNED     NOT NULL,
    huesped_num_doc VARCHAR(20)         NOT NULL,
    PRIMARY KEY (reserva_id, huesped_num_doc),
    CONSTRAINT fk_rh_reserva
        FOREIGN KEY (reserva_id) REFERENCES reservas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rh_huesped
        FOREIGN KEY (huesped_num_doc) REFERENCES huespedes(num_doc)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- =========================================================
-- RESERVA_HABITACIONES
-- =========================================================

CREATE TABLE reserva_habitaciones (
    reserva_id          BIGINT UNSIGNED     NOT NULL,
    habitacion_numero   SMALLINT UNSIGNED   NOT NULL,
    tipo_nombre_historico VARCHAR(50) 		NOT NULL,
    precio_aplicado     DECIMAL(8,2)        NOT NULL,
    tiempo_estadia      TINYINT UNSIGNED    NULL,
    fecha_salida_efectiva DATETIME 			NOT NULL,
    PRIMARY KEY (reserva_id, habitacion_numero),
    CONSTRAINT fk_rhab_reserva
        FOREIGN KEY (reserva_id) REFERENCES reservas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rhab_habitacion
        FOREIGN KEY (habitacion_numero) REFERENCES habitaciones(numero)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- =========================================================
-- EXTENSIONES
-- =========================================================

CREATE TABLE extensiones (
    id          BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    reserva_id  BIGINT UNSIGNED     NOT NULL,
    cantidad    TINYINT UNSIGNED    NOT NULL,
    created_at  TIMESTAMP           NULL,
    updated_at  TIMESTAMP           NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_ext_reserva
        FOREIGN KEY (reserva_id) REFERENCES reservas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE extension_habitaciones (
    reserva_id          BIGINT UNSIGNED     NOT NULL,
    extension_id        BIGINT UNSIGNED     NOT NULL,
    numero_habitacion   SMALLINT UNSIGNED   NOT NULL,
    monto               DECIMAL(8,2)        NOT NULL,
    PRIMARY KEY (reserva_id, extension_id, numero_habitacion),
    CONSTRAINT fk_exthab_extension
        FOREIGN KEY (extension_id) REFERENCES extensiones(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

-- =========================================================
-- PAGOS
-- Ya NO tiene comprobante_id: el comprobante se obtiene vía
-- pagos.reserva_id -> reservas.comprobante_id (opción B).
-- =========================================================

CREATE TABLE devoluciones (
    id                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    reserva_id        BIGINT UNSIGNED     NOT NULL,
    origen 			  ENUM('cancelacion', 'ajuste fechas') NOT NULL DEFAULT 'cancelacion',
    monto_devuelto    DECIMAL(8,2)        NOT NULL,
    monto_retenido    DECIMAL(8,2)        NOT NULL,
    metodo            ENUM('efectivo', 'transferencia', 'yape', 'plin') NOT NULL,
    numero_operacion  VARCHAR(30) 		  NULL,
    fecha_devolucion  DATETIME            NOT NULL,
    created_at        TIMESTAMP           NULL,
    updated_at        TIMESTAMP           NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_devoluciones_reserva
        FOREIGN KEY (reserva_id) REFERENCES reservas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE pagos (
    id                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    reserva_id        BIGINT UNSIGNED     NOT NULL,
    monto             DECIMAL(8,2)        NOT NULL,
    metodo_id         TINYINT UNSIGNED    NOT NULL,
    tipo_id           TINYINT UNSIGNED    NOT NULL,
    fecha_pago        DATETIME            NOT NULL,
    numero_operacion  VARCHAR(30)         NULL,
    created_at        TIMESTAMP           NULL,
    updated_at        TIMESTAMP           NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_pagos_reserva
        FOREIGN KEY (reserva_id) REFERENCES reservas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_metodo
        FOREIGN KEY (metodo_id) REFERENCES metodos_pago(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_tipo
        FOREIGN KEY (tipo_id) REFERENCES tipos_pago(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);
