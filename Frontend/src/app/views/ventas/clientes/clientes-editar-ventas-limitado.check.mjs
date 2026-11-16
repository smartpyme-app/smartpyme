/**
 * Run: node Frontend/src/app/views/ventas/clientes/clientes-editar-ventas-limitado.check.mjs
 *
 * Editar clientes depende solo de ventas.clientes.editar (no del tipo de usuario).
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));

const html = fs.readFileSync(path.join(dir, 'clientes.component.html'), 'utf8');
const lineas = html.split('\n');
const i = lineas.findIndex((l) => l.includes('fa-edit') && l.includes('Editar'));
assert.ok(i > 0, 'falta la opción Editar');
const condicion = lineas.slice(Math.max(0, i - 3), i).find((l) => l.includes('@if'));
assert.match(condicion, /ventas\.clientes\.editar/);
assert.doesNotMatch(condicion, /isVentasLimitado/);

const ts = fs.readFileSync(path.join(dir, 'cliente/informacion/cliente-informacion.component.ts'), 'utf8');
const guard = ts.split('\n').find((l) => l.includes("canEditTest('ventas.clientes.editar')"));
assert.ok(guard, 'falta la validación de permiso al editar');
assert.doesNotMatch(guard, /isVentasLimitado/);

console.log('clientes-editar-ventas-limitado.check: ok');
