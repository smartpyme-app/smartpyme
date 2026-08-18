/**
 * Smoke: resolver de grupo en Preferencias del sistema.
 * Run: node Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const tsPath = path.join(dir, 'preferencias-grupo.ts');
assert.equal(fs.existsSync(tsPath), true, 'falta preferencias-grupo.ts');

const ts = fs.readFileSync(tsPath, 'utf8');
assert.match(ts, /export function resolverGrupoPreferencias/);
assert.match(ts, /modulos/);
assert.match(ts, /documentos/);
assert.match(ts, /facturacion/);
assert.match(ts, /inventario/);
assert.match(ts, /permisos/);
assert.match(ts, /cuenta/);

const require = createRequire(import.meta.url);
let mod;
try {
  mod = require('./preferencias-grupo.ts');
} catch {
  const js = ts
    .replace(/export type[^\n]+\n/g, '')
    .replace(/: PreferenciasGrupo/g, '')
    .replace(/ as const/g, '')
    .replace(/export /g, '');
  const fn = new Function(`${js}; return { resolverGrupoPreferencias, PREFERENCIAS_GRUPOS };`);
  mod = fn();
}

const { resolverGrupoPreferencias } = mod;
assert.equal(resolverGrupoPreferencias('facturacion', true), 'facturacion');
assert.equal(resolverGrupoPreferencias('permisos', false), 'modulos');
assert.equal(resolverGrupoPreferencias(null, true), 'modulos');
assert.equal(resolverGrupoPreferencias('foo', true), 'modulos');
assert.equal(resolverGrupoPreferencias('permisos', true), 'permisos');
assert.equal(resolverGrupoPreferencias(undefined, false), 'modulos');

const html = fs.readFileSync(path.join(dir, 'empresa.component.html'), 'utf8');
assert.match(html, /empresa-pref-nav/);
assert.match(html, /setPreferenciasGrupo/);
assert.match(html, /preferenciasGrupo === 'modulos'/);
assert.match(html, /preferenciasGrupo === 'documentos'/);
assert.match(html, /preferenciasGrupo === 'facturacion'/);
assert.match(html, /preferenciasGrupo === 'inventario'/);
assert.match(html, /preferenciasGrupo === 'permisos'/);
assert.match(html, /preferenciasGrupo === 'cuenta'/);
assert.match(html, /empresa-pref-save/);
assert.doesNotMatch(html, /empresa-pref-tile/);
assert.doesNotMatch(html, /empresa-pref-facturacion/);

console.log('preferencias-grupo.check: ok');
