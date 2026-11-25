import assert from 'node:assert/strict';
import { alternarOrden, ordenarFilas } from './finanzas-table-sort.helper.ts';

// 2-state toggle (proveedores)
let st = { col: null, dir: 'asc' };
st = alternarOrden(st, 'compro');
assert.deepEqual(st, { col: 'compro', dir: 'asc' });
st = alternarOrden(st, 'compro');
assert.deepEqual(st, { col: 'compro', dir: 'desc' });
st = alternarOrden(st, 'compro');
assert.deepEqual(st, { col: 'compro', dir: 'asc' });

// 3-state cycle with reset (P&L)
let pl = { col: null, dir: 'asc' };
pl = alternarOrden(pl, 'mes', true);
assert.deepEqual(pl, { col: 'mes', dir: 'asc' });
pl = alternarOrden(pl, 'mes', true);
assert.deepEqual(pl, { col: 'mes', dir: 'desc' });
pl = alternarOrden(pl, 'mes', true);
assert.deepEqual(pl, { col: null, dir: 'asc' });

// Nulls stay at the bottom in both asc and desc
const rows = [
  { name: 'Beta', val: null },
  { name: 'Álamo', val: 30 },
  { name: 'Cero', val: 10 },
];
assert.deepEqual(
  ordenarFilas(rows, 'asc', (r) => r.val).map((r) => r.name),
  ['Cero', 'Álamo', 'Beta'],
);
assert.deepEqual(
  ordenarFilas(rows, 'desc', (r) => r.val).map((r) => r.name),
  ['Álamo', 'Cero', 'Beta'],
);
assert.deepEqual(
  ordenarFilas(rows, 'asc', (r) => r.name).map((r) => r.name),
  ['Álamo', 'Beta', 'Cero'],
);

console.log('finanzas-table-sort.helper.check: ok');
