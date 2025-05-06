-- Super Admin
UPDATE sucursales SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE sucursal_bodegas SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE productos SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE categorias SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE ajustes SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE traslados SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE ventas SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE clientes SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE canales SET id_empresa = 2 WHERE id_empresa = 59;
DELETE FROM documentos WHERE id_empresa = 2;
UPDATE documentos SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE impuestos SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE compras SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE proveedores SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE egresos SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE gastos_categorias SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE presupuestos SET id_empresa = 2 WHERE id_empresa = 59;
DELETE FROM bancos WHERE id_empresa = 2;
UPDATE bancos SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE users SET id_empresa = 2 WHERE id_empresa = 59;

ALTER TABLE empresas ADD id_cliente BIGINT NULL AFTER wompi_secret;
ALTER TABLE empresas ADD id_documento BIGINT AFTER id_cliente;
ALTER TABLE planes ADD id_producto BIGINT NULL AFTER activo;
ALTER TABLE ordenes_pago ADD id_venta BIGINT NULL AFTER fecha_transaccion;

-- Actualizar anexos hacienda

ALTER TABLE compras ADD tipo varchar(255) NULL after fecha_pago;
ALTER TABLE compras ADD sector varchar(255) NULL after tipo;

ALTER TABLE ventas ADD tipo_renta varchar(255) NULL after fecha_pago;
