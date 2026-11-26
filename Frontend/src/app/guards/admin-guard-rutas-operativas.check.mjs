/**
 * Run: node Frontend/src/app/guards/admin-guard-rutas-operativas.check.mjs
 */
import assert from 'node:assert/strict';

function mostrarMenuCompras(hasPermission, hasAnyPermission, canInv) {
  const ps = ['compras.ver', 'compras.registros.ver', 'compras.ordenes_compra.ver', 'compras.proveedores.ver', 'compras.retaceo.ver', 'compras.consignas.ver'];
  return hasAnyPermission(ps) || (canInv() && hasPermission('compras.consignas.ver'));
}
function mostrarMenuGastos(hasPermission) {
  return hasPermission('gastos.ver') || hasPermission('gastos.registros.ver') || hasPermission('gastos.categorias.ver');
}
const ORDEN_COMPRA = /^\/orden-de-compra(\/|$)/;
const COMPRAS = /^\/compras(\/|$)/;

function adminGuardPermiteRutaOperativa(url, hasPermission, hasAnyPermission, canInv) {
  const p = url.split('?')[0];
  if (ORDEN_COMPRA.test(p) || COMPRAS.test(p)) {
    return mostrarMenuCompras(hasPermission, hasAnyPermission, canInv);
  }
  return false;
}

const perms = ['compras.ordenes_compra.ver'];
assert.equal(
  adminGuardPermiteRutaOperativa('/orden-de-compra/crear', (x) => perms.includes(x), (xs) => xs.some((p) => perms.includes(p)), () => false),
  true,
);
assert.equal(
  adminGuardPermiteRutaOperativa('/compras', (p) => perms.includes(p), (xs) => xs.some((p) => perms.includes(p)), () => false),
  true,
);
assert.equal(
  adminGuardPermiteRutaOperativa('/orden-de-compra/1', () => false, () => false, () => false),
  false,
);

console.log('admin-guard-rutas-operativas.check: ok');