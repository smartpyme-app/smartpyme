/**
 * Run: node Frontend/src/app/views/compras/compras-routing-access.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..');
const routing = fs.readFileSync(path.join(root, 'src/app/views/compras/compras.routing.module.ts'), 'utf8');

assert.match(routing, /esRutaLazyModuloCompras|PERM_VER_COMPRAS/);
assert.match(routing, /compras\.ordenes_compra\.crear/);
assert.match(routing, /compras\.ordenes_compra\.ver/);
assert.match(routing, /compras\.devoluciones\.ver/);
assert.ok(routing.includes("path: 'gasto/crear'") && routing.includes('gastos.crear'));
assert.ok(routing.includes("path: 'compra/crear'") && routing.includes('compras.crear'));
assert.doesNotMatch(routing, /component: GastosComponent/);

const routeBlocks = routing.split(/\{\s*\n\s*path:/).slice(1);
for (const block of routeBlocks) {
  if (block.includes('loadComponent') && !block.includes('canActivate')) {
    assert.fail(`Ruta sin canActivate: path:${block.slice(0, 40)}`);
  }
}

console.log('compras-routing-access.check: ok');