/**
 * activo en documentos API es string "1"; el filtro no debe exigir === true.
 * Run: node Frontend/src/app/views/compras/cotizaciones/components/orden-compra-form/orden-compra-documento-activo.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const ts = fs.readFileSync(path.join(dir, 'orden-compra-form.component.ts'), 'utf8');

assert.match(ts, /documentoEsOrdenCompraActiva/);
assert.doesNotMatch(ts, /activo === true \|\| d\.activo === 1/);

console.log('orden-compra-documento-activo.check: ok');
