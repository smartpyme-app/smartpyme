/** ponytail: smoke de reglas solicitudes cross-empresa. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const src = fs.readFileSync(path.join(dir, 'solicitudes-compra-acceso.ts'), 'utf8');

assert.match(src, /export function mostrarMenuSolicitudesCompra/);
assert.doesNotMatch(src, /isVentasLimitado/);
assert.match(src, /ventas\.solicitudes\.ver/);

console.log('solicitudes-compra-acceso.check: ok');
