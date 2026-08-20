/**
 * Smoke checks for operational module navigation rules.
 * Run: node Frontend/src/app/layout/sidebar/sidebar-operativos-nav.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..');
const sidebar = fs.readFileSync(path.join(root, 'src/app/layout/sidebar/sidebar.component.html'), 'utf8');
const planillasRouting = fs.readFileSync(path.join(root, 'src/app/views/planillas/planillas.routing.module.ts'), 'utf8');
const inventarioRouting = fs.readFileSync(path.join(root, 'src/app/views/inventario/inventario.routing.module.ts'), 'utf8');
const restauranteRouting = fs.readFileSync(path.join(root, 'src/app/views/restaurante/restaurante-routing.module.ts'), 'utf8');
const pedidosRouting = fs.readFileSync(path.join(root, 'src/app/views/pedidos/pedidos-routing.module.ts'), 'utf8');

assert.doesNotMatch(sidebar, /!apiService\.hasPermission\('planilla/);
assert.match(sidebar, /apiService\.hasPermission\('planilla\.ver'\)/);
assert.match(sidebar, /canAccederOperacionesInventario\(\) && apiService\.hasPermission\('consignas\.ver'\)/);
assert.match(sidebar, /mostrarMenuRestaurante && apiService\.hasPermission\('restaurante\.ver'\)/);
assert.match(sidebar, /mostrarMenuPedidos && apiService\.hasPermission\('pedidos\.ver'\)/);
assert.match(sidebar, /organizacion\.usuarios\.ver/);
assert.match(sidebar, /administracion\.sucursales\.ver/);
assert.match(sidebar, /validateRole\('admin', true\)/);
assert.doesNotMatch(sidebar, /<li \*ngIf="apiService\.auth_user\(\)\.tipo != 'Contador'"/);
assert.match(sidebar, /\[routerLink\]="\['\/reportes-automaticos'\]"/);

assert.match(planillasRouting, /PermissionGuard/);
assert.match(planillasRouting, /permission: 'planilla\.ver'/);
assert.match(inventarioRouting, /permission: 'consignas\.ver'/);
assert.match(restauranteRouting, /permission: 'restaurante\.ver'/);
assert.match(restauranteRouting, /funcionalidadSlug: 'modulo-restaurante'/);
assert.match(pedidosRouting, /permission: 'pedidos\.ver'/);
assert.match(pedidosRouting, /funcionalidadSlug: 'modulo-restaurante'/);

console.log('sidebar-operativos-nav.check: ok');
