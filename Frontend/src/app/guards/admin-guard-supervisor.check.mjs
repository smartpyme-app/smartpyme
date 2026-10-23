/**
 * Run: node Frontend/src/app/guards/admin-guard-supervisor.check.mjs
 *
 * Supervisor limitado entra a ventas/compras/gastos por isAdmin(), no por isAdminRole().
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const file = path.join(path.dirname(fileURLToPath(import.meta.url)), 'admin.guard.ts');
const src = fs.readFileSync(file, 'utf8');

const general = src.slice(
  src.indexOf('isAdmin() cubre Supervisor Limitado'),
  src.indexOf('Si no tiene permisos')
);

assert.ok(general.length > 0, 'falta la rama general del guard');
assert.match(general, /isAdminRole\(\)/);
assert.match(general, /isAdmin\(\)/);
assert.match(general, /admin\.acceder/);

function allowsOperationalRoute({ isAdminRole, isAdmin, adminAcceder }) {
  return isAdminRole || isAdmin || adminAcceder;
}

assert.equal(
  allowsOperationalRoute({ isAdminRole: false, isAdmin: true, adminAcceder: false }),
  true,
  'supervisor limitado (isAdmin) debe entrar a ventas/compras/gastos'
);
assert.equal(
  allowsOperationalRoute({ isAdminRole: false, isAdmin: false, adminAcceder: false }),
  false
);

console.log('admin-guard-supervisor.check: ok');
