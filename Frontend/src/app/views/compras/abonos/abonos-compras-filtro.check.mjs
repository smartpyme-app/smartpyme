/** ponytail: modal filtro abonos compras usa ng-select. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const ts = fs.readFileSync(path.join(dir, 'abonos-compras.component.ts'), 'utf8');
const html = fs.readFileSync(path.join(dir, 'abonos-compras.component.html'), 'utf8');

assert.match(ts, /NgSelectModule/);
assert.match(html, /appendTo="body"/);
assert.match(html, /proveedoresSelect/);

console.log('abonos-compras-filtro.check: ok');
