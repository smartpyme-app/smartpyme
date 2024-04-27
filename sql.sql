ALTER TABLE devoluciones_venta ADD correlativo varchar(255) NULL after fecha;
ALTER TABLE devoluciones_venta ADD id_documento int NULL after correlativo;

ALTER TABLE productos ADD deleted_at timestamp NULL after updated_at;
ALTER TABLE inventario ADD deleted_at timestamp NULL after updated_at;


CREATE TABLE licencias (
    id int NOT NULL AUTO_INCREMENT,
    num_licencias int NULL,
    id_empresa int  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE licencia_empresas (
    id int NOT NULL AUTO_INCREMENT,
    id_licencia int  NOT NULL,
    id_empresa int NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);
