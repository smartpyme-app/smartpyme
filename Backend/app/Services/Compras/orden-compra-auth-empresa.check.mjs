/**
 * Run: node Backend/app/Services/Compras/orden-compra-auth-empresa.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const file = path.join(path.dirname(fileURLToPath(import.meta.url)), 'OrdenCompraService.php');
const src = fs.readFileSync(file, 'utf8');

assert.match(src, /debeSolicitarAutorizacionOrdenCompra/);
assert.match(src, /where\('users\.id_empresa', \$user->id_empresa\)/);

console.log('orden-compra-auth-empresa.check: ok');
