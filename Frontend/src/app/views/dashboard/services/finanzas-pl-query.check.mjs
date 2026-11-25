/**
 * Smoke: query P&L año completo no envía mes=0 (Go responde "mes inválido").
 * Run: node Frontend/src/app/views/dashboard/services/finanzas-pl-query.check.mjs
 */
import assert from 'node:assert/strict';

function buildPlQueryString(q) {
  let p = `empresa=1&anio=${q.anio}&periodo=${q.periodo}&base=${q.base}&comparacion=${q.comparacion}`;
  if (q.periodo === 'mes') {
    if (q.mes < 1 || q.mes > 12) {
      throw new Error('mes inválido en consulta P&L');
    }
    p += `&mes=${q.mes}`;
  }
  return p;
}

const anio = buildPlQueryString({
  anio: 2026,
  mes: 0,
  periodo: 'anio_completo',
  base: 'gerencial',
  comparacion: 'mes_anterior',
});
assert.doesNotMatch(anio, /[?&]mes=/);

const mes = buildPlQueryString({
  anio: 2026,
  mes: 9,
  periodo: 'mes',
  base: 'gerencial',
  comparacion: 'mes_anterior',
});
assert.match(mes, /&mes=9/);

console.log('finanzas-pl-query.check: ok');
