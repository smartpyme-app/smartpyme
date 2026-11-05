/** Smoke: orden y margen bruto en vista anual P&L. */
function pctMargen(util, netas) {
  if (Math.abs(netas) < 0.01) return null;
  return Math.round((util / netas) * 1000) / 10;
}

const bruta = { meses: [4, 8], totalAnual: 12 };
const netas = { meses: [10, 20], totalAnual: 30 };
if (pctMargen(4, 10) !== 40) throw new Error('margen mes 1');
if (pctMargen(12, 30) !== 40) throw new Error('margen total');
console.log('pl-gerencial-anual.helper.check: ok');
