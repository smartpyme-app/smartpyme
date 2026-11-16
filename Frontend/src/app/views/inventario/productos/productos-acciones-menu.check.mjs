/**
 * Run: node Frontend/src/app/views/inventario/productos/productos-acciones-menu.check.mjs
 *
 * Crear/editar productos dependen solo de productos.crear / productos.editar, no del tipo de usuario.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.join(import.meta.dirname);
const leer = (rel) => fs.readFileSync(path.join(root, rel), 'utf8').replace(/<!--[\s\S]*?-->/g, '');

const ts = leer('productos.component.ts');
const html = leer('productos.component.html');
const detalle = leer('detalle-producto/detalle-producto.component.html');
const form = leer('producto/producto.component.ts');

assert.doesNotMatch(ts, /isSupervisorLimitado\(\)/);
assert.doesNotMatch(html, /mostrarMenuAcciones\(\)/);
for (const src of [html, detalle]) {
  assert.doesNotMatch(src, /apiService\.(canEdit|canCreate)\(\)/);
}
assert.match(html, /canCreateTest\('productos\.crear'\)/);
assert.match(html, /canEditTest\('productos\.editar'\)/);
assert.match(detalle, /canEditTest\('productos\.editar'\)/);

assert.match(form, /if \(params\.id && !this\.apiService\.canEditTest\('productos\.editar'\)\)/);
assert.match(form, /if \(!params\.id && !this\.apiService\.canCreateTest\('productos\.crear'\)\)/);

console.log('productos-acciones-menu.check: ok');
