/**
 * Smoke: nombre comercial en preferencias, formulario y búsquedas (no listado).
 * Run: node Frontend/src/app/views/inventario/productos/nombre-comercial.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const app = path.resolve(dir, '../../..');

const empresaHtml = fs.readFileSync(
  path.join(app, 'views/admin/empresa/empresa.component.html'),
  'utf8',
);
assert.match(empresaHtml, /nombre_comercial_activo/);
assert.match(empresaHtml, /Permitir agregar nombre comercial a productos/);

const empresaTs = fs.readFileSync(
  path.join(app, 'views/admin/empresa/empresa.component.ts'),
  'utf8',
);
assert.match(empresaTs, /nombre_comercial_activo:\s*false/);

const formHtml = fs.readFileSync(
  path.join(dir, 'producto/informacion/producto-informacion.component.html'),
  'utf8',
);
const nombreIdx = formHtml.indexOf('label for="nombre"');
const comercialIdx = formHtml.indexOf('label for="nombre_comercial"');
assert.ok(nombreIdx >= 0, 'falta el campo Nombre');
assert.ok(comercialIdx > nombreIdx, 'Nombre comercial debe ir después de Nombre');
assert.match(formHtml, /\*ngIf="isNombreComercialHabilitado\(\)"/);

const listHtml = fs.readFileSync(path.join(dir, 'productos.component.html'), 'utf8');
assert.doesNotMatch(listHtml, /<th[^>]*>Nombre comercial/);
assert.match(listHtml, /nombre comercial/);

const buscadorHtml = fs.readFileSync(
  path.join(app, 'shared/parts/buscador-productos/buscador-productos.component.html'),
  'utf8',
);
assert.match(buscadorHtml, /isNombreComercialHabilitado\(\)/);
assert.match(buscadorHtml, /Nombre comercial:/);

console.log('nombre-comercial.check: ok');
