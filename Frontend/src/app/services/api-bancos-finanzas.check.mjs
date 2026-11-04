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

const cuentasListHtml = fs.readFileSync(
  path.join(root, 'src/app/views/contabilidad/bancos/cuentas/cuentas.component.html'),
  'utf8',
);
assert.match(cuentasListHtml, /finanzas\.bancos\.crear/);
assert.match(cuentasListHtml, /finanzas\.bancos\.editar/);
assert.doesNotMatch(cuentasListHtml, /finanzas\.cuentas\.(crear|editar)/);

const permService = fs.readFileSync(path.join(root, 'src/app/services/permission.service.ts'), 'utf8');
assert.match(permService, /finanzas\.cuentas\./);
assert.match(permService, /finanzas\.bancos\./);

function usarCuentasBancarias(contabilidad, bancos) {
  return contabilidad || bancos;
}
function mostrarBancosEnFinanzas(contabilidad, bancos) {
  return !contabilidad && bancos;
}
/** Selects de abono/pago: solo el switch de módulo, no contabilidad. */
function usarCuentasEnSelectPago(moduloBancos) {
  return moduloBancos === true;
}
assert.equal(usarCuentasBancarias(false, false), false);
assert.equal(usarCuentasBancarias(false, true), true);
assert.equal(usarCuentasBancarias(true, false), true);
assert.equal(mostrarBancosEnFinanzas(false, true), true);
assert.equal(mostrarBancosEnFinanzas(true, true), false);
assert.equal(mostrarBancosEnFinanzas(false, false), false);
assert.equal(usarCuentasEnSelectPago(false), false);
assert.equal(usarCuentasEnSelectPago(true), true);

assert.match(api, /usarCuentasBancariasEnPagos\(\)[\s\S]{0,120}return this\.isModuloBancos\(\)/);
assert.match(api, /loadBancosParaAbono\(\)[\s\S]{0,200}const usarCuentas = this\.isModuloBancos\(\)/);

const ventasTs = fs.readFileSync(path.join(root, 'src/app/views/ventas/ventas.component.ts'), 'utf8');
const comprasTs = fs.readFileSync(path.join(root, 'src/app/views/compras/compras.component.ts'), 'utf8');
const factCompraTs = fs.readFileSync(path.join(root, 'src/app/views/compras/facturacion/facturacion-compra.component.ts'), 'utf8');
assert.match(ventasTs, /loadBancosParaAbono\(\)/);
assert.match(comprasTs, /loadBancosParaAbono\(\)/);
assert.match(factCompraTs, /loadBancosParaAbono\(\)|isModuloBancos\(\)/);
assert.doesNotMatch(ventasTs, /getAll\('banco\/cuentas\/list'\)/);
assert.doesNotMatch(comprasTs, /getAll\('banco\/cuentas\/list'\)/);

const ventasHtml = fs.readFileSync(path.join(root, 'src/app/views/ventas/ventas.component.html'), 'utf8');
const comprasHtml = fs.readFileSync(path.join(root, 'src/app/views/compras/compras.component.html'), 'utf8');
const factCompraHtml = fs.readFileSync(path.join(root, 'src/app/views/compras/facturacion/facturacion-compra.component.html'), 'utf8');
const gastoHtml = fs.readFileSync(path.join(root, 'src/app/views/compras/gastos/gasto/gasto.component.html'), 'utf8');
const gastosHtml = fs.readFileSync(path.join(root, 'src/app/views/compras/gastos/gastos.component.html'), 'utf8');
for (const [name, html] of [
  ['ventas', ventasHtml],
  ['compras', comprasHtml],
  ['facturacion-compra', factCompraHtml],
]) {
  assert.match(html, /isModuloBancos\(\)/, `${name} bank select must branch on modulo bancos`);
  assert.match(html, /banco\.nombre/, `${name} must keep old catalog labels`);
}
assert.match(gastoHtml, /isModuloBancos\(\)/);
assert.match(gastosHtml, /isModuloBancos\(\)/);

console.log('api-bancos-finanzas.check: ok');
