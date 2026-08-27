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
assert.match(sidebar, /\[routerLink\]="\['\/finanzas\/reportes'\]"/);
assert.doesNotMatch(sidebar, /toggleFinanzasReportes\(\)/);
assert.doesNotMatch(sidebar, /libroIvaResumenRoute/);
assert.doesNotMatch(sidebar, /\/finanzas\/reportes\/cuentas-cobrar/);

const reportesNav = fs.readFileSync(path.join(root, 'src/app/views/finanzas/reportes/finanzas-reportes-nav.component.ts'), 'utf8');
assert.match(reportesNav, /\/finanzas\/reportes\/cuentas-cobrar/);
assert.match(reportesNav, /\/finanzas\/reportes\/cuentas-pagar/);
assert.match(reportesNav, /\/finanzas\/reportes\/antiguedad-cxc/);
assert.match(reportesNav, /\/finanzas\/reportes\/antiguedad-cxp/);
assert.match(reportesNav, /Antigüedad CxC/);
assert.match(reportesNav, /Antigüedad CxP/);
assert.doesNotMatch(reportesNav, /Antigüedad de saldos/);
assert.match(reportesNav, /\/finanzas\/reportes\/resumen-impuestos/);

const agingHtml = fs.readFileSync(path.join(root, 'src/app/views/finanzas/antiguedad-saldos/antiguedad-saldos.component.html'), 'utf8');
assert.doesNotMatch(agingHtml, /btn-group/);
assert.doesNotMatch(agingHtml, /setTipo\(/);
assert.ok(agingHtml.indexOf('fecha_corte') < agingHtml.indexOf('app-finanzas-reportes-nav'));

const finanzasRouting = fs.readFileSync(path.join(root, 'src/app/views/finanzas/finanzas.routing.module.ts'), 'utf8');
assert.match(finanzasRouting, /reportes\/antiguedad-cxc/);
assert.match(finanzasRouting, /reportes\/antiguedad-cxp/);
assert.match(finanzasRouting, /redirectTo: '\/finanzas\/reportes\/antiguedad-cxc'/);

const resumenHtml = fs.readFileSync(path.join(root, 'src/app/views/finanzas/reportes/finanzas-reportes-resumen.component.html'), 'utf8');
assert.ok(resumenHtml.indexOf('app-libro-iva-periodo-filtros') < resumenHtml.indexOf('app-finanzas-reportes-nav'));

assert.match(planillasRouting, /PermissionGuard/);
assert.match(planillasRouting, /permission: 'planilla\.ver'/);
assert.match(inventarioRouting, /permission: 'consignas\.ver'/);
assert.match(restauranteRouting, /permission: 'restaurante\.ver'/);
assert.match(restauranteRouting, /funcionalidadSlug: 'modulo-restaurante'/);
assert.match(pedidosRouting, /permission: 'pedidos\.ver'/);
assert.match(pedidosRouting, /funcionalidadSlug: 'modulo-restaurante'/);

console.log('sidebar-operativos-nav.check: ok');
