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
UPDATE documentos SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE impuestos SET id_empresa = 2 WHERE id_empresa = 59;

UPDATE compras SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE proveedores SET id_empresa = 2 WHERE id_empresa = 59;

UPDATE egresos SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE gastos_categorias SET id_empresa = 2 WHERE id_empresa = 59;
UPDATE presupuestos SET id_empresa = 2 WHERE id_empresa = 59;

DELETE FROM documentos WHERE id_empresa = 2;
UPDATE documentos SET id_empresa = 2 WHERE id_empresa = 59;

DELETE FROM bancos WHERE id_empresa = 2;
UPDATE bancos SET id_empresa = 2 WHERE id_empresa = 59;

UPDATE users SET id_empresa = 2 WHERE id_empresa = 59;

ALTER TABLE empresas ADD id_cliente BIGINT NULL AFTER wompi_secret;
