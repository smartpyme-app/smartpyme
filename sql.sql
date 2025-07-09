ALTER TABLE empresas ADD tipo_renta_servicios VARCHAR(255) NULL AFTER logo;
ALTER TABLE empresas ADD tipo_renta_productos VARCHAR(255) NULL AFTER tipo_renta_servicios;
ALTER TABLE empresas ADD tipo_sector VARCHAR(255) NULL AFTER tipo_renta_productos;
