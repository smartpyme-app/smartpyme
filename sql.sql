-- Actualizar catalogos

CREATE TABLE paises (
    id int NOT NULL AUTO_INCREMENT,
    cod varchar(255) NOT NULL,
    nombre varchar(255)  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE distritos (
    id int NOT NULL AUTO_INCREMENT,
    cod varchar(100) NOT NULL,
    nombre varchar(255) NOT NULL,
    cod_municipio varchar(100) NOT NULL,
    cod_departamento varchar(100) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE recintos (
    id int NOT NULL AUTO_INCREMENT,
    cod varchar(100) NOT NULL,
    nombre varchar(255) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE incoterms (
    id int NOT NULL AUTO_INCREMENT,
    cod varchar(100) NOT NULL,
    nombre varchar(255) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE regimenes (
    id int NOT NULL AUTO_INCREMENT,
    cod varchar(100) NOT NULL,
    nombre varchar(255) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

ALTER TABLE clientes ADD distrito varchar(255) NULL AFTER tipo_contribuyente;
ALTER TABLE clientes ADD cod_distrito varchar(100) NULL AFTER distrito;

ALTER TABLE proveedores ADD distrito varchar(255) NULL AFTER tipo_contribuyente;
ALTER TABLE proveedores ADD cod_distrito varchar(100) NULL AFTER distrito;

ALTER TABLE empresas ADD distrito varchar(255) NULL AFTER cod_departamento;
ALTER TABLE empresas ADD cod_distrito varchar(100) NULL AFTER distrito;


ALTER TABLE devoluciones_compra ADD iva_retenido decimal(10,2) NULL AFTER iva;
ALTER TABLE devoluciones_compra ADD tipo_documento varchar(255) NULL AFTER iva;
ALTER TABLE devoluciones_compra ADD referencia varchar(255) NULL AFTER iva;

ALTER TABLE devoluciones_compra ADD iva_percibido decimal(10,2) NULL AFTER iva;

