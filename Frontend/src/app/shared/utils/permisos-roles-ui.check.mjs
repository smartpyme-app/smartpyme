/** ponytail: runnable check for SP-2339 permission UI helpers. */
import assert from 'node:assert/strict';

function permisoEliminarOculto(name) {
  return typeof name === 'string' && name.endsWith('.eliminar');
}

function permisoPadreJerarquia(name) {
  const partes = name.split('.');
  if (partes.length < 3) return null;
  const verbo = partes[partes.length - 1];
  if (!['ver', 'crear', 'editar', 'anular'].includes(verbo)) return null;
  return `${partes[0]}.${verbo}`;
}

function esPermisoHijoDePadre(hijo, padre) {
  const h = hijo.split('.');
  const p = padre.split('.');
  if (h.length < 3 || p.length !== 2) return false;
  return h[0] === p[0] && h[h.length - 1] === p[1];
}

assert.equal(permisoEliminarOculto('ventas.eliminar'), true);
assert.equal(permisoEliminarOculto('ventas.anular'), false);
assert.equal(permisoPadreJerarquia('ventas.cotizaciones.anular'), 'ventas.anular');
assert.equal(
  esPermisoHijoDePadre('ventas.cotizaciones.ver', 'ventas.ver'),
  true,
);
assert.equal(
  esPermisoHijoDePadre('ventas.solicitudes.ver', 'ventas.ver'),
  true,
);

console.log('permisos-roles-ui.check: ok');
