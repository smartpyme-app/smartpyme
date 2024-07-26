-- Bancos
CREATE TABLE cuentas_bancarias (
    id int NOT NULL AUTO_INCREMENT,
    numero varchar(255) NOT NULL,
    nombre_banco varchar(255) NOT NULL,
    tipo varchar(255) NOT NULL,
    saldo decimal(10,2) NOT NULL,
    correlativo_cheques int NULL,
    id_cuenta_contable int NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE cuentas_bancarias_cheques (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    id_cuenta int NOT NULL,
    correlativo int NOT NULL,
    anombrede varchar(255) NOT NULL,
    concepto varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    referencia varchar(255) NULL,
    id_referencia int NULL,
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
    tipo_operacion varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    total decimal(10,2) NOT NULL,
    referencia varchar(255) NULL,
    id_referencia int NULL,
    url_referencia varchar(255) NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE cuentas_bancarias_conciliaciones (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    desde date NOT NULL,
    hasta date NOT NULL,
    id_cuenta int NOT NULL,
    gastos decimal(10,2) NULL DEFAULT 0,
    impuestos decimal(10,2) NULL DEFAULT 0,
    otras_entradas decimal(10,2) NULL DEFAULT 0,
    saldo_anterior decimal(10,2) NOT NULL,
    saldo_actual decimal(10,2) NOT NULL,
    nota varchar(255) NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

-- Catalogo

CREATE TABLE contabilidad_configuracion (
    id int NOT NULL AUTO_INCREMENT,
    id_cuenta_ingresos int NOT NULL,
    id_cuenta_devoluciones_ventas int NOT NULL,
    id_cuenta_inventario int NOT NULL,
    id_cuenta_ajustes_inventario int NOT NULL,
    id_cuenta_cxc int NOT NULL,
    id_cuenta_devoluciones_clientes int NOT NULL,
    id_cuenta_cxp int NOT NULL,
    id_cuenta_devoluciones_proveedores int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);


CREATE TABLE catalogo_cuentas (
    id int NOT NULL AUTO_INCREMENT,
    codigo int NOT NULL,
    nombre varchar(255) NOT NULL,
    naturaleza varchar(255) NOT NULL,
    id_cuenta_padre int NULL,
    rubro varchar(255) NOT NULL,
    nivel int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);


CREATE TABLE partidas (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
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
    codigo varchar(255) NOT NULL,
    nombre_cuenta varchar(255) NOT NULL,
    concepto varchar(255) NOT NULL,
    debe decimal(10,2) NULL DEFAULT 0,
    haber decimal(10,2) NULL DEFAULT 0,
    saldo decimal(10,2) NULL DEFAULT 0,
    id_partida int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE retenciones (
    id int NOT NULL AUTO_INCREMENT,
    nombre varchar(255) NOT NULL,
    porcentaje decimal(10,2) NOT NULL,
    id_cuenta_contable_ventas int NOT NULL,
    id_cuenta_contable_compras int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);


ALTER TABLE empresas ADD agrupar_detalles_venta BOOL DEFAULT false after editar_precio_venta;
ALTER TABLE empresas ADD vendedor_inventario BOOL DEFAULT false after agrupar_detalles_venta;

ALTER TABLE impuestos ADD id_cuenta_contable_ventas INT NULL after porcentaje;
ALTER TABLE impuestos ADD id_cuenta_contable_compras INT NULL after id_cuenta_contable_ventas;
