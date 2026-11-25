/**
 * Roles personalizados: layout debe mostrar sidebar si hay permisos efectivos.
 * Run: node Frontend/src/app/services/permission-effective-sidebar.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const layout = fs.readFileSync(path.join(root, 'src/app/layout/layout.component.html'), 'utf8');
const permissionSvc = fs.readFileSync(
  path.join(root, 'src/app/services/permission.service.ts'),
  'utf8',
);

assert.match(layout, /hasEffectivePermissions\(\)/);
assert.match(permissionSvc, /hasEffectivePermissions\(\)/);
console.log('permission-effective-sidebar.check.mjs: ok');