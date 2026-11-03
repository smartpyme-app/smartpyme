/**
 * Run: node Frontend/src/app/views/inventario/productos/productos-acciones-menu.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.join(import.meta.dirname);
const ts = fs.readFileSync(path.join(root, 'productos.component.ts'), 'utf8');

assert.match(ts, /mostrarMenuAcciones\(\)/);
assert.doesNotMatch(ts, /validateRole\('usuario_supervisor', false\)/);
assert.match(ts, /!this\.apiService\.isSupervisorLimitado\(\)/);

console.log('productos-acciones-menu.check: ok');
