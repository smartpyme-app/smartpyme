/**
 * Run: node Frontend/src/app/layout/puede-usar-plataforma.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { empresaPuedeUsarPlataforma } from './puede-usar-plataforma.ts';

const dir = path.dirname(fileURLToPath(import.meta.url));

assert.equal(
  empresaPuedeUsarPlataforma({ licencia: { id: 1 }, es_empresa_padre: true }),
  true,
  'organización padre entra a la plataforma'
);
assert.equal(
  empresaPuedeUsarPlataforma({ licencia: null, es_empresa_padre: false }),
  true,
  'empresa sin licencia entra a la plataforma'
);
assert.equal(empresaPuedeUsarPlataforma(undefined), true);
assert.equal(
  empresaPuedeUsarPlataforma({ licencia: { id: 1 }, es_empresa_padre: false }),
  false
);

const sinComentarios = (html) => html.replace(/<!--[\s\S]*?-->/g, '');
const layout = sinComentarios(fs.readFileSync(path.join(dir, 'layout.component.html'), 'utf8'));
const dash = sinComentarios(fs.readFileSync(path.join(dir, '../views/dash/dash.component.html'), 'utf8'));

assert.match(layout, /puedeUsarPlataforma\(\)/);
assert.doesNotMatch(layout, /<app-sidebar-organizaciones>/);
assert.match(dash, /@if \(apiService\.isAdmin\(\)\)/);
assert.doesNotMatch(dash, /<app-organizaciones-dash>/);

console.log('puede-usar-plataforma.check: ok');
