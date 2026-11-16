import { kpisDesdeResumen, resumenDesdeFilas } from './rentabilidad-kpis.resumen.ts';

const cards = kpisDesdeResumen(
  {
    mesEtiqueta: 'sep',
    comparacionEtiqueta: 'sep 2025',
    utilidadOperativa: 2590,
    utilidadOperativaPrev: 2760,
    utilidadOperativaVsPct: -6.2,
    margenOperativo: 6.8,
    margenOperativoPrev: 7.9,
    margenBruto: 36.1,
    margenBrutoPrev: 37.2,
    sucursalesPerdida: 1,
    sucursalesTotal: 3,
    peorSucursal: 'San Miguel',
    peorSucursalUtilidad: -140,
  },
  (n) => (n < 0 ? `-$${Math.abs(n)}` : `$${n}`),
  (n) => `${n.toFixed(1)}%`,
);

if (cards.length !== 4) throw new Error('deben ser 4 cards');
if (cards[0].titulo !== 'Utilidad operativa · sep') throw new Error(cards[0].titulo);
if (cards[0].sublinea !== '↓ -6.2% vs. sep 2025 · $2760') throw new Error(cards[0].sublinea);
if (cards[1].sublinea !== '↓ -1.1 pts vs. sep 2025 · 7.9%') throw new Error(cards[1].sublinea);
if (cards[2].valor !== '36.1%') throw new Error(cards[2].valor);
if (cards[3].valor !== '1 de 3' || !cards[3].alerta) throw new Error(cards[3].valor);
if (cards[3].sublinea !== 'San Miguel · -$140') throw new Error(cards[3].sublinea);

const una = resumenDesdeFilas(
  [
    {
      nombre: 'San Miguel',
      ventas: 1000,
      gastos: 340,
      utilidad: -140,
      margenPct: -14,
      tendenciaAlcista: false,
      tendencia: [],
      cxc: 0,
      cxcAlerta: false,
      cxp: 0,
      ventasVsAnioPct: 0,
      detalle: {
        cxcVencido: 0,
        cxpVencido: 0,
        puente: [
          { etiqueta: 'Ventas', monto: 1000, pct: 100 },
          { etiqueta: 'Costo', monto: 800, pct: 80 },
          { etiqueta: 'Gastos', monto: 340, pct: 34 },
          { etiqueta: 'Utilidad', monto: -140, pct: -14 },
        ],
        productos: [],
        clientes: [],
        devolucionesTotal: 0,
        devoluciones: [],
      },
    },
  ],
  'sep',
  'sep 2025',
);
if (!una || una.sucursalesTotal !== 1 || una.sucursalesPerdida !== 1) throw new Error('fila sucursal');
if (una.margenBruto !== 20) throw new Error(String(una.margenBruto));
const cardsSuc = kpisDesdeResumen(una, (n) => `$${n}`, (n) => `${n.toFixed(1)}%`);
if (cardsSuc.length !== 4 || cardsSuc[3].valor !== '1 de 1') throw new Error(cardsSuc[3].valor);
console.log('rentabilidad-kpis.builder.check: ok');
