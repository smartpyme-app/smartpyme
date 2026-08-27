/**
 * Los botones de Pedidos deben usar permisos (pedidos.crear/editar/eliminar),
 * no el tipo legacy del usuario. Un rol custom con solo esos permisos
 * no tiene tipo Administrador/Supervisor/Ventas.
 * Run: node Frontend/src/app/views/pedidos/pedidos-lista/pedidos-permisos-ui.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const html = fs.readFileSync(path.join(dir, 'pedidos-lista.component.html'), 'utf8');
const api = fs.readFileSync(
  path.resolve(dir, '../../../services/api.service.ts'),
  'utf8'
);

assert.match(html, /canCreateTest\('pedidos\.crear'\)/);
assert.match(html, /canEditTest\('pedidos\.editar'\)/);
assert.match(html, /canDeleteTest\('pedidos\.eliminar'\)/);
assert.doesNotMatch(html, /puedeGestionarPedidosCanal\(/);
assert.doesNotMatch(api, /puedeGestionarPedidosCanal\(/);

console.log('pedidos-permisos-ui.check: ok');
