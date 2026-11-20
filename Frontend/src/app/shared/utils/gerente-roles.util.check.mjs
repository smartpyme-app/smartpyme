/** Run: node Frontend/src/app/shared/utils/gerente-roles.util.check.mjs */
import assert from 'node:assert/strict';

const SLUGS = new Set(['gerente_ventas', 'gerente_compras', 'gerente_operaciones']);

function esRolGerente(roleSlug, tipo) {
  if (roleSlug && SLUGS.has(roleSlug)) {
    return true;
  }
  const t = (tipo ?? '').trim().toLowerCase();
  return t.startsWith('gerente');
}

assert.equal(esRolGerente('gerente_compras', ''), true);
assert.equal(esRolGerente('admin', 'Gerente ventas'), true);
assert.equal(esRolGerente('usuario_ventas', 'Ventas'), false);

// Permiso menú inteligencia: gerente debe estar en la allowlist (lógica en permission.service).
function puedeVerInteligencia(roleSlug, tipo) {
  return (
  esRolGerente(roleSlug, tipo) ||
    roleSlug === 'admin' ||
    roleSlug === 'super_admin' ||
    roleSlug === 'contador_superior'
  );
}
assert.equal(puedeVerInteligencia('gerente_ventas', ''), true);

console.log('gerente-roles.util.check.mjs: OK');