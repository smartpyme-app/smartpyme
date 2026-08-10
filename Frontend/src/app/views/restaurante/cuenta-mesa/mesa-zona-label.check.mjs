/**
 * Smoke: etiqueta mesa + zona en POS.
 * Run: node Frontend/src/app/views/restaurante/cuenta-mesa/mesa-zona-label.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const ts = fs.readFileSync(path.join(dir, 'cuenta-mesa.component.ts'), 'utf8');
const html = fs.readFileSync(path.join(dir, 'cuenta-mesa.component.html'), 'utf8');
const api = fs.readFileSync(
  path.resolve(dir, '../../../../../../Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php'),
  'utf8'
);

assert.match(ts, /mesaConZonaLabel/);
assert.match(ts, /zona_restaurante\?\.nombre/);
assert.match(html, /\{\{\s*mesaConZonaLabel\s*\}\}/);
assert.doesNotMatch(html, /Mesa \{\{\s*sesion\.mesa\?\.numero\s*\}\}/);
assert.match(api, /mesa\.zonaRestaurante/);

console.log('mesa-zona-label.check: ok');
