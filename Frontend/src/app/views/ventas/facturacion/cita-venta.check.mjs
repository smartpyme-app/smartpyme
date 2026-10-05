/**
 * Cita → líneas de venta, sin consultar el producto.
 * Run: node --experimental-strip-types Frontend/src/app/views/ventas/facturacion/cita-venta.check.mjs
 */
import assert from 'node:assert/strict';
import { aplicarCitaEnVenta, lineasVentaDesdeCita } from './cita-venta.ts';

const evento = {
  id: 1329,
  id_cliente: 114799,
  productos: [
    { id_producto: 314782, cantidad: '1', nombre_producto: 'Jornadas médicas', precio_producto: '26.548700' },
    { cantidad: '1', nombre_producto: 'Sin producto' },
  ],
};

const lineas = lineasVentaDesdeCita(evento);
assert.equal(lineas.length, 1);
assert.equal(lineas[0].descripcion, 'Jornadas médicas');
assert.equal(lineas[0].id_producto, 314782);
assert.equal(lineas[0].id_cita, 1329);
assert.equal(lineas[0].cantidad, 1);
assert.equal(lineas[0].precio, 26.5487);
assert.equal(lineas[0].total, '26.5487');

assert.deepEqual(lineasVentaDesdeCita({ id: 1 }), []);

const venta = aplicarCitaEnVenta({ detalles: [{ id_producto: 1 }], cliente: {} }, evento);
assert.equal(venta.id_evento, 1329);
assert.equal(venta.detalles.length, 2);
assert.equal(venta.detalles[1].descripcion, 'Jornadas médicas');

console.log('ok');
