-- Bancos
CREATE TABLE cuentas_bancarias (
    id int NOT NULL AUTO_INCREMENT,
    numero varchar(255) NOT NULL,
    nombre_banco varchar(255) NOT NULL,
    tipo varchar(255) NOT NULL,
    saldo decimal(10,2) NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE cheques (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    id_cuenta int NOT NULL,
    correlativo int NOT NULL,
    anombrede varchar(255) NOT NULL,
    concepto varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    total decimal(10,2) NOT NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE cuentas_bancarias_transacciones (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    id_cuenta int NOT NULL,
    concepto varchar(255) NOT NULL,
    tipo varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    total decimal(10,2) NOT NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

-- Catalogo

CREATE TABLE catalogo_cuentas (
    id int NOT NULL AUTO_INCREMENT,
    codigo varchar(255) NOT NULL,
    nombre varchar(255) NOT NULL,
    id_cuenta_mayor int NOT NULL,
    nivel int NOT NULL,
    tipo varchar(255) NOT NULL,
    sub_cuenta int NOT NULL,
    rubro varchar(255) NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE partidas (
    id int NOT NULL AUTO_INCREMENT,
    tipo varchar(255) NOT NULL,
    concepto varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE partida_detalles (
    id int NOT NULL AUTO_INCREMENT,
    id_cuenta int NOT NULL,
    id_partida int NOT NULL,
    concepto varchar(255) NOT NULL,
    abono decimal(10,2) NOT NULL,
    cargo decimal(10,2) NOT NULL,
    saldo decimal(10,2) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

ALTER TABLE empresas ADD agrupar_detalles_venta BOOL DEFAULT false after editar_precio_venta;
