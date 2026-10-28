/** ponytail: smoke permisos menú ventas. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const util = fs.readFileSync(path.join(dir, 'ventas-acciones-menu.util.ts'), 'utf8');
const caja = fs.readFileSync(
  path.join(dir, '../dash/caja/ventas/caja-ventas.component.html'),
  'utf8',
);
const ventasHtml = fs.readFileSync(path.join(dir, 'ventas.component.html'), 'utf8');

assert.match(util, /ventas\.devoluciones\.crear/);
assert.match(util, /ventas\.registros\.ver/);
assert.match(caja, /canEmitirFeVenta/);
assert.match(caja, /canDevolucionVenta/);
assert.match(ventasHtml, /canEmitirFeVenta/);
assert.match(ventasHtml, /canVerDetalleVenta/);
assert.match(ventasHtml, /canNotaCreditoDebitoVenta/);
assert.match(ventasHtml, /canEditVentas\(\).*generarTrasladoEmpresa/s);

console.log('ventas-acciones-menu.check: ok');
