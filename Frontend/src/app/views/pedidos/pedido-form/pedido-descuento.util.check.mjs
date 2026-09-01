/**
 * Pedidos: descuento en $ (total de línea) o % (sobre cantidad * precio).
 * Run: node --experimental-strip-types Frontend/src/app/views/pedidos/pedido-form/pedido-descuento.util.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  camposDescuentoFacturaDesdePedido,
  descuentoLineaPedido,
  esDescuentoMontoPedido,
} from './pedido-descuento.util.ts';

const dir = path.dirname(fileURLToPath(import.meta.url));
const formHtml = fs.readFileSync(path.join(dir, 'pedido-form.component.html'), 'utf8');
const formTs = fs.readFileSync(path.join(dir, 'pedido-form.component.ts'), 'utf8');

const dinero = descuentoLineaPedido(2, 10, true, 3);
assert.equal(dinero.descuento, 3);
assert.equal(dinero.descuento_porcentaje, 0);

const pct = descuentoLineaPedido(2, 10, false, 10);
assert.equal(pct.descuento, 2);
assert.equal(pct.descuento_porcentaje, 10);

assert.equal(esDescuentoMontoPedido(0), true);
assert.equal(esDescuentoMontoPedido(10), false);

const facturaPct = camposDescuentoFacturaDesdePedido({
  descuento: 2,
  descuento_porcentaje: 10,
  cantidad: 2,
  ivaPct: 13,
  montoConIva: true,
});
assert.equal(facturaPct.descuento_is_monto, false);
assert.equal(facturaPct.descuento_porcentaje, 10);
assert.equal(facturaPct.descuento_monto, 0);

const facturaDineroV2 = camposDescuentoFacturaDesdePedido({
  descuento: 2,
  descuento_porcentaje: 0,
  cantidad: 2,
  ivaPct: 13,
  montoConIva: true,
});
assert.equal(facturaDineroV2.descuento_is_monto, true);
assert.equal(facturaDineroV2.descuento_porcentaje, 0);
assert.equal(facturaDineroV2.descuento_monto, 1.13);

const facturaDineroV1 = camposDescuentoFacturaDesdePedido({
  descuento: 2,
  descuento_porcentaje: 0,
  cantidad: 2,
  ivaPct: 13,
  montoConIva: false,
});
assert.equal(facturaDineroV1.descuento_monto, 1);

assert.match(formHtml, /descuento_is_monto/);
assert.match(formHtml, /descuento_porcentaje/);
assert.match(formTs, /descuentoLineaPedido/);
assert.match(formTs, /descuento_porcentaje/);

const facturacionV1 = fs.readFileSync(
  path.resolve(dir, '../../ventas/facturacion/facturacion-tienda/facturacion.component.ts'),
  'utf8'
);
const facturacionV2 = fs.readFileSync(
  path.resolve(dir, '../../ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.ts'),
  'utf8'
);
assert.match(facturacionV1, /camposDescuentoFacturaDesdePedido/);
assert.match(facturacionV2, /camposDescuentoFacturaDesdePedido/);

console.log('pedido-descuento.util.check: ok');
