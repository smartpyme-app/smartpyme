/**
 * Pedidos: elegir precio de la lista (como facturación).
 * Producto: eliminar tarifa extra por id (string o number).
 * Run: node Frontend/src/app/views/pedidos/pedido-form/pedido-precios.util.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const formHtml = fs.readFileSync(path.join(dir, 'pedido-form.component.html'), 'utf8');
const formTs = fs.readFileSync(path.join(dir, 'pedido-form.component.ts'), 'utf8');
const preciosHtml = fs.readFileSync(
  path.resolve(dir, '../../inventario/productos/producto/precios/producto-precios.component.html'),
  'utf8'
);
const preciosTs = fs.readFileSync(
  path.resolve(dir, '../../inventario/productos/producto/precios/producto-precios.component.ts'),
  'utf8'
);

assert.match(formTs, /armarListaPreciosPedido/);
assert.match(formHtml, /tieneListaPrecios\(l\)/);
assert.match(formHtml, /<select/);
assert.match(preciosHtml, /delete\(precio\)/);
assert.match(preciosTs, /Number\(p\.id\) !== Number\(id\)/);

console.log('pedido-precios.util.check: ok');
