import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const src = fs.readFileSync(
  path.join(path.dirname(fileURLToPath(import.meta.url)), 'precios-usuario-venta.util.ts'),
  'utf8',
);
assert.match(src, /precioVisibleParaUsuario/);
assert.match(src, /filtrarPreciosPorUsuario/);
assert.match(src, /id_usuario/);

console.log('precios-usuario-venta.util.check: ok');
