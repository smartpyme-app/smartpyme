/**
 * Run: node Frontend/src/app/views/ventas/cotizaciones/cotizaciones-crear-ventas-limitado.check.mjs
 *
 * Ventas limitada con ventas.cotizaciones.crear ve el botón. Sin permiso, o en edición, no entra.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const html = fs.readFileSync(path.join(dir, 'cotizaciones.component.html'), 'utf8');
const condicion = html.split('\n').find((line) => line.includes("ventas.cotizaciones.crear"));
assert.ok(condicion, 'falta la condición del botón de cotización');
assert.match(condicion, /restriccionesCotizacionesVendedoresActivas/);
assert.doesNotMatch(condicion, /isVentasLimitado/);

function puedeAbrirCrear({ esEdicion, esVentasLimitado, tienePermiso, restriccionEmpresa }) {
  if (!esVentasLimitado) return true;
  const puedeCrear = tienePermiso && !restriccionEmpresa;
  return !(esEdicion || !puedeCrear);
}

assert.equal(puedeAbrirCrear({
  esEdicion: false, esVentasLimitado: true, tienePermiso: true, restriccionEmpresa: false,
}), true);
assert.equal(puedeAbrirCrear({
  esEdicion: false, esVentasLimitado: true, tienePermiso: false, restriccionEmpresa: false,
}), false);
assert.equal(puedeAbrirCrear({
  esEdicion: true, esVentasLimitado: true, tienePermiso: true, restriccionEmpresa: false,
}), false);

const v1 = fs.readFileSync(
  path.join(dir, '../facturacion/facturacion-tienda/facturacion.component.ts'),
  'utf8'
);
assert.match(v1, /isVentasLimitado\(\) && \(esEdicion \|\| !puedeCrear\)/);

console.log('cotizaciones-crear-ventas-limitado.check: ok');
