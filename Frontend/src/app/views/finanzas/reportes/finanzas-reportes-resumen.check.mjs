import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../../../');
const html = fs.readFileSync(path.join(root, 'src/app/views/finanzas/reportes/finanzas-reportes-resumen.component.html'), 'utf8');
const filtros = html.indexOf('app-libro-iva-periodo-filtros');
const nav = html.indexOf('app-finanzas-reportes-nav');
assert.ok(filtros >= 0 && nav > filtros);
assert.match(html, /enLibrosFiscales/);
assert.match(html, /app-libro-iva-sv-nav/);
assert.match(html, /app-libro-iva-resumen-panel/);

const routing = fs.readFileSync(path.join(root, 'src/app/views/contabilidad/contabilidad.routing.module.ts'), 'utf8');
for (const ruta of ['libro-iva-sv/resumen', 'libro-iva-cr/resumen', 'libro-iva-hd/resumen', 'libro-iva-general/resumen']) {
  assert.match(routing, new RegExp(ruta.replace('/', '\\/')));
}
assert.doesNotMatch(routing, /redirectTo: '\/finanzas\/reportes\/resumen-impuestos'/);
assert.match(html, /app-finanzas-reportes-nav/);

console.log('finanzas-reportes-resumen.check: ok');
