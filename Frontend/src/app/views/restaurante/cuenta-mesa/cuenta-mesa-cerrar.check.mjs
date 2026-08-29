/**
 * SP-2158: cerrar mesa sin factura (Supervisor, Administrador, Ventas).
 * Run: node Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa-cerrar.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const ts = fs.readFileSync(path.join(dir, 'cuenta-mesa.component.ts'), 'utf8');
const html = fs.readFileSync(path.join(dir, 'cuenta-mesa.component.html'), 'utf8');
const mapTs = fs.readFileSync(path.resolve(dir, '../restaurante.component.ts'), 'utf8');
const mapHtml = fs.readFileSync(path.resolve(dir, '../restaurante.component.html'), 'utf8');
const rolesUtil = fs.readFileSync(path.resolve(dir, '../restaurante-roles.util.ts'), 'utf8');
const service = fs.readFileSync(
  path.resolve(dir, '../../../services/restaurante.service.ts'),
  'utf8'
);
const authz = fs.readFileSync(
  path.resolve(
    dir,
    '../../../../../../Backend/app/Services/Restaurante/RestauranteAutorizacionService.php'
  ),
  'utf8'
);
const controller = fs.readFileSync(
  path.resolve(
    dir,
    '../../../../../../Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php'
  ),
  'utf8'
);

assert.match(ts, /puedeCerrarMesa\(\)/);
assert.match(ts, /cerrarMesa\(\)/);
assert.match(ts, /cerrarSesion/);
assert.match(ts, /puedeCerrarMesaRestaurante/);
assert.match(html, /puedeCerrarMesa\(\)/);
assert.match(html, /Cerrar mesa/);
assert.match(mapTs, /cerrarMesaDesdeMapa/);
assert.match(mapHtml, /cerrarMesaDesdeMapa/);
assert.match(mapHtml, /Cerrar mesa/);
assert.match(rolesUtil, /puedeCerrarMesaRestaurante/);
assert.match(service, /cerrarSesion\(sesionId/);
assert.match(authz, /usuarioPuedeCerrarMesa/);
assert.match(controller, /usuarioPuedeCerrarMesa/);

console.log('cuenta-mesa-cerrar.check: ok');
