/**
 * Run: node Frontend/src/app/views/ventas/cotizaciones/cotizaciones-crear-ventas-limitado.check.mjs
 *
 * Ventas limitado crea con ventas.cotizaciones.crear y edita con ventas.cotizaciones.editar.
 * Editar e Imprimir del listado dependen solo del permiso, no del tipo de usuario.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const html = fs.readFileSync(path.join(dir, 'cotizaciones.component.html'), 'utf8');
const lineas = html.split('\n');

const condicion = lineas.find((line) => line.includes("ventas.cotizaciones.crear"));
assert.ok(condicion, 'falta la condición del botón de cotización');
assert.match(condicion, /restriccionesCotizacionesVendedoresActivas/);
assert.doesNotMatch(condicion, /isVentasLimitado/);

const editar = lineas.find((line) => line.includes('puedeEditarCotizacion()'));
assert.ok(editar, 'falta la condición de Editar');
assert.doesNotMatch(editar, /isVentasLimitado/);

const imprimir = lineas.find((line) => line.includes('puedeImprimirFilaCotizacion('));
assert.ok(imprimir, 'falta la condición de Imprimir');
assert.doesNotMatch(imprimir, /isVentasLimitado/);

function puedeAbrir({ esEdicion, esVentasLimitado, permisos, restriccionEmpresa }) {
  if (!esVentasLimitado) return true;
  const permiso = esEdicion ? 'ventas.cotizaciones.editar' : 'ventas.cotizaciones.crear';
  return permisos.includes(permiso) && !restriccionEmpresa;
}

const vl = { esVentasLimitado: true, restriccionEmpresa: false };
assert.equal(puedeAbrir({ ...vl, esEdicion: false, permisos: ['ventas.cotizaciones.crear'] }), true);
assert.equal(puedeAbrir({ ...vl, esEdicion: false, permisos: [] }), false);
assert.equal(puedeAbrir({ ...vl, esEdicion: true, permisos: ['ventas.cotizaciones.crear'] }), false);
assert.equal(puedeAbrir({ ...vl, esEdicion: true, permisos: ['ventas.cotizaciones.editar'] }), true);
assert.equal(puedeAbrir({ ...vl, esEdicion: true, permisos: ['ventas.cotizaciones.editar'], restriccionEmpresa: true }), false);

for (const rel of [
  '../facturacion/facturacion-tienda/facturacion.component.ts',
  '../facturacion/facturacion-tienda-v2/facturacion-v2.component.ts',
]) {
  const src = fs.readFileSync(path.join(dir, rel), 'utf8');
  assert.match(src, /esEdicion \? 'ventas\.cotizaciones\.editar' : 'ventas\.cotizaciones\.crear'/, rel);
  assert.match(src, /isVentasLimitado\(\) && !puedeGuardar/, rel);
  for (const linea of src.split('\n').filter((l) => l.includes('isVentasLimitado()') && l.includes("router.url.includes('/cotizacion/')"))) {
    const i = src.indexOf(linea);
    assert.match(src.slice(i, i + 200), /ventas\.cotizaciones\.editar/, `${rel}: bloqueo de edición sin permiso`);
  }
}

console.log('cotizaciones-crear-ventas-limitado.check: ok');
