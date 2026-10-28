/** ponytail: estados cotización minúsculas → badge ventas. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const util = fs.readFileSync(
  path.join(path.dirname(fileURLToPath(import.meta.url)), 'cotizacion-estado.util.ts'),
  'utf8',
);
const html = fs.readFileSync(
  path.join(path.dirname(fileURLToPath(import.meta.url)), 'cotizaciones.component.html'),
  'utf8',
);

assert.match(util, /pendiente.*Pendiente/s);
assert.match(util, /bg-warning/);
assert.match(html, /estadoCotizacionBadgeClass/);

console.log('cotizacion-estado.util.check: ok');
