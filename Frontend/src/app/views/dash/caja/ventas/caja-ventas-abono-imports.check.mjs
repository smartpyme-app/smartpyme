/**
 * Run: node Frontend/src/app/views/dash/caja/ventas/caja-ventas-abono-imports.check.mjs
 * Modal de pago en /vendedor/ventas requiere CrearAbonoVentaComponent en imports.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const dir = dirname(fileURLToPath(import.meta.url));
const ts = readFileSync(join(dir, 'caja-ventas.component.ts'), 'utf8');

assert.match(ts, /CrearAbonoVentaComponent/);
assert.match(ts, /imports:\s*\[[\s\S]*CrearAbonoVentaComponent/);

console.log('caja-ventas-abono-imports.check: ok');