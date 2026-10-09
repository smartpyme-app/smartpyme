/**
 * Run: node Frontend/src/app/services/permission-isadmin.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const file = path.join(path.dirname(fileURLToPath(import.meta.url)), 'permission.service.ts');
const src = fs.readFileSync(file, 'utf8');

const isAdminBlock = src.slice(src.indexOf('isAdmin(): boolean'), src.indexOf('isAdminCreate(): boolean'));
assert.match(isAdminBlock, /role === 'usuario_supervisor'/);
assert.match(isAdminBlock, /usuario_supervisor_limitado/);
assert.doesNotMatch(isAdminBlock, /usuario_citas/);

console.log('permission-isadmin.check: ok');
