/**
 * SP-2316: el listado no debe forzar id_sucursal del usuario logueado.
 * Run: node Frontend/src/app/views/pedidos/pedidos-lista/pedidos-lista-sucursal.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const ts = fs.readFileSync(path.join(dir, 'pedidos-lista.component.ts'), 'utf8');

assert.doesNotMatch(ts, /filtros\.id_sucursal\s*=\s*u\.id_sucursal/);
assert.doesNotMatch(ts, /u\.tipo !== 'Administrador'/);

console.log('pedidos-lista-sucursal.check: ok');
