/**
 * Smoke: reglas IVA separado y abonos en cartera en config de contabilidad.
 * Run: node Frontend/src/app/views/contabilidad/configuracion/contabilidad-configuracion.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const html = fs.readFileSync(path.join(dir, 'contabilidad-configuracion.component.html'), 'utf8');
const ts = fs.readFileSync(path.join(dir, 'contabilidad-configuracion.component.ts'), 'utf8');

assert.match(html, /separar_cuentas_iva/);
assert.match(html, /abonos_en_cartera/);
assert.match(html, /id_cuenta_iva_ventas_cf/);
assert.match(html, /id_cuenta_iva_compras_cf/);
assert.match(html, /@if \(configuracion\.separar_cuentas_iva\)/);
assert.match(ts, /separar_cuentas_iva = !!/);
assert.match(ts, /abonos_en_cartera = !!/);

console.log('contabilidad-configuracion.check: ok');
