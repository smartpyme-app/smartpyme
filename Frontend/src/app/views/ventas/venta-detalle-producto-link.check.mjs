/**
 * Run: node --experimental-strip-types Frontend/src/app/views/ventas/venta-detalle-producto-link.check.mjs
 */
import assert from 'node:assert/strict';
import { idProductoEditable, rutaEditarProductoDetalle } from './venta-detalle-producto-link.util.ts';

assert.equal(idProductoEditable({ id_producto: 12 }), 12);
assert.equal(idProductoEditable({ producto: { id: 3 } }), 3);
assert.equal(idProductoEditable({ descripcion: 'x' }), null);

assert.deepEqual(rutaEditarProductoDetalle({ id_producto: 1, tipo: 'Bien' }), ['/producto/editar', '1']);
assert.deepEqual(rutaEditarProductoDetalle({ id_producto: 2, tipo: 'Servicio' }), ['/servicio/editar', '2']);

console.log('venta-detalle-producto-link.check: ok');
