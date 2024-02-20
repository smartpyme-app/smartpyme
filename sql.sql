ALTER TABLE clientes ADD red_social VARCHAR(255) NULL after nota;

ALTER TABLE eventos ADD frecuencia_fin date NULL after frecuencia;

ALTER TABLE notificaciones ADD referencia VARCHAR(255) NULL after leido;
ALTER TABLE notificaciones ADD id_referencia int NULL after referencia;

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
    cuenta_a_tercero decimal(9,2) NULL,
    total decimal(10,2) NULL,
    nota text NULL,
    id_cliente int  NOT NULL,
    id_usuario int  NOT NULL,
    id_empresa int  NOT NULL,
    id_sucursal int  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);
