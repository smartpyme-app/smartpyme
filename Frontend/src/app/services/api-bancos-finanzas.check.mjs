/**
 * Smoke: bancos en Finanzas y cuentas contables solo con contabilidad.
 * Run: node Frontend/src/app/services/api-bancos-finanzas.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');

const api = fs.readFileSync(path.join(root, 'src/app/services/api.service.ts'), 'utf8');
assert.match(api, /usarCuentasBancarias\(contabilidadHabilitada: boolean\)/);
assert.match(api, /mostrarBancosEnFinanzas\(contabilidadHabilitada: boolean\)/);

const impuestosHtml = fs.readFileSync(path.join(root, 'src/app/views/ventas/impuestos/impuestos.component.html'), 'utf8');
assert.match(impuestosHtml, /@if \(contabilidadHabilitada\)/);
assert.match(impuestosHtml, /Configuración Contable/);

const retencionesHtml = fs.readFileSync(path.join(root, 'src/app/views/ventas/retenciones/retenciones.component.html'), 'utf8');
assert.match(retencionesHtml, /@if \(contabilidadHabilitada\)/);

const formasHtml = fs.readFileSync(path.join(root, 'src/app/views/ventas/formas-de-pago/formas-de-pago.component.html'), 'utf8');
assert.match(formasHtml, /usarCuentasBancarias\(contabilidadHabilitada\)/);

const cuentaHtml = fs.readFileSync(path.join(root, 'src/app/views/contabilidad/bancos/cuentas/cuenta/cuenta.component.html'), 'utf8');
assert.match(cuentaHtml, /@if \(contabilidadHabilitada\)/);

function usarCuentasBancarias(contabilidad, bancos) {
  return contabilidad || bancos;
}
function mostrarBancosEnFinanzas(contabilidad, bancos) {
  return !contabilidad && bancos;
}
assert.equal(usarCuentasBancarias(false, false), false);
assert.equal(usarCuentasBancarias(false, true), true);
assert.equal(usarCuentasBancarias(true, false), true);
assert.equal(mostrarBancosEnFinanzas(false, true), true);
assert.equal(mostrarBancosEnFinanzas(true, true), false);
assert.equal(mostrarBancosEnFinanzas(false, false), false);

console.log('api-bancos-finanzas.check: ok');
