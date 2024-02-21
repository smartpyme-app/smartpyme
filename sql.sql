ALTER TABLE users ADD codigo varchar(100) NULL after enable;

ALTER TABLE detalles_venta ADD no_sujeta decimal(9,2) DEFAULT 0 NOT NULL after descuento;
ALTER TABLE detalles_venta ADD exenta decimal(9,2) DEFAULT 0 NOT NULL after no_sujeta;

ALTER TABLE ventas ADD no_sujeta decimal(9,2) DEFAULT 0 NOT NULL after sub_total;
ALTER TABLE ventas ADD exenta decimal(9,2) DEFAULT 0 NOT NULL after no_sujeta;

CREATE TABLE paquetes (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    wr varchar(255),
    transportista varchar(255),
    consignatario varchar(255),
    transportador varchar(255),
    estado varchar(255) NOT NULL,
    num_seguimiento varchar(255),
    num_guia varchar(255),
    piezas int NULL,
    peso decimal(9,2) NULL,
    precio decimal(9,2) NULL,
    volumen decimal(9,2) NULL,
    cuanta_a_terceros decimal(9,2) NULL,
    total decimal(10,2) NULL,
    nota text NULL,
    id_venta int NULL,
    id_venta_detalle int NULL,
    id_cliente int NULL,
    id_asesor int NULL,
    id_usuario int  NOT NULL,
    id_empresa int  NOT NULL,
    id_sucursal int  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);
