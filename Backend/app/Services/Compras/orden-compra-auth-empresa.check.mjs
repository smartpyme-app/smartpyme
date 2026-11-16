/**
 * Run: node Backend/app/Services/Compras/orden-compra-auth-empresa.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(dir, '../../../..');
const file = path.join(dir, 'OrdenCompraService.php');
const src = fs.readFileSync(file, 'utf8');

assert.match(src, /debeSolicitarAutorizacionOrdenCompra/);
assert.match(src, /where\('users\.id_empresa', \$user->id_empresa\)/);

const facturacionTs = fs.readFileSync(
  path.join(repoRoot, 'Frontend/src/app/views/compras/facturacion/facturacion-compra.component.ts'),
  'utf8'
);
assert.match(
  facturacionTs,
  /facturar_cotizacion[\s\S]{0,250}this\.apiService\.read\('orden-de-compra\/'/
);
assert.doesNotMatch(
  facturacionTs,
  /if\s*\(\s*this\.facturarCotizacion\s*\)\s*\{\s*this\.apiService\.read\('compra\/'/
);

const comprasControllerPhp = fs.readFileSync(
  path.join(repoRoot, 'Backend/app/Http/Controllers/Api/Compras/ComprasController.php'),
  'utf8'
);
assert.match(comprasControllerPhp, /actualizarDesdeCompra\(\$compra,\s*\$request->detalles\)/);

console.log('orden-compra-auth-empresa.check: ok');
