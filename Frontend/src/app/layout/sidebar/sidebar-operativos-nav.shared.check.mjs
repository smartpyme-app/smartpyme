/**
 * Run: node Frontend/src/app/layout/sidebar/sidebar-operativos-nav.shared.check.mjs
 */
import assert from 'node:assert/strict';
const PERMISOS_MENU_COMPRAS = [
  'compras.ver',
  'compras.registros.ver',
  'compras.ordenes_compra.ver',
  'compras.proveedores.ver',
  'compras.retaceo.ver',
  'compras.consignas.ver',
];

function mostrarMenuCompras(hasPermission, hasAnyPermission, canAccederOperacionesInventario) {
  return (
    hasAnyPermission(PERMISOS_MENU_COMPRAS) ||
    (canAccederOperacionesInventario() && hasPermission('compras.consignas.ver'))
  );
}

function mostrarMenuGastos(hasPermission) {
  return (
    hasPermission('gastos.ver') ||
    hasPermission('gastos.registros.ver') ||
    hasPermission('gastos.categorias.ver')
  );
}

function puedeVerRegistrosCompras(hasPermission) {
  return hasPermission('compras.ver') || hasPermission('compras.registros.ver');
}

const has = (...names) => (p) => names.includes(p);
const hasAny = (arr) => (ps) => ps.some((p) => arr.includes(p));

assert.equal(
  mostrarMenuCompras(has('compras.registros.ver'), hasAny(['compras.registros.ver']), () => false),
  true,
);
assert.equal(
  mostrarMenuGastos(has('gastos.registros.ver')),
  true,
);
assert.equal(puedeVerRegistrosCompras(has('compras.registros.ver')), true);
assert.equal(puedeVerRegistrosCompras(has('compras.ordenes_compra.ver')), false);

console.log('sidebar-operativos-nav.shared.check: ok');