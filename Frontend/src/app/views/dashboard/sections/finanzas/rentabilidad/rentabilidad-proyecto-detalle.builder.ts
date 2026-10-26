import {
  RentabilidadProyectoBarTono,
  RentabilidadProyectoDetalle,
  RentabilidadProyectoFila,
  RentabilidadProyectoPresupuestoFila,
} from './rentabilidad.model';

const CATEGORIAS_GASTO = [
  { nombre: 'Materiales', pctGasto: 72 },
  { nombre: 'Mano de obra', pctGasto: 16 },
  { nombre: 'Subcontratos', pctGasto: 6 },
  { nombre: 'Fletes y entregas', pctGasto: 4 },
  { nombre: 'Alquiler de equipo', pctGasto: 2 },
];

const PRESUP_PCT = [
  { categoria: 'Materiales', share: 0.715 },
  { categoria: 'Mano de obra', share: 0.177 },
  { categoria: 'Subcontratos', share: 0.057 },
  { categoria: 'Fletes y entregas', share: 0.041 },
  { categoria: 'Alquiler de equipo', share: 0.016 },
];

function semilla(nombre: string): number {
  let h = 0;
  for (const ch of nombre) {
    h = (h * 31 + ch.charCodeAt(0)) | 0;
  }
  return Math.abs(h);
}

function barTonoUsado(usadoPct: number, gastadoPct: number): 'azul' | 'naranja' {
  return usadoPct > 80 || gastadoPct > usadoPct + 5 ? 'naranja' : 'azul';
}

function presupuestoFilas(
  presupuestoTotal: number,
  gastadoTotal: number,
  gastadoPct: number,
): RentabilidadProyectoPresupuestoFila[] {
  let gastadoRestante = gastadoTotal;
  return PRESUP_PCT.map((row, index) => {
    const presupuesto = Math.round(presupuestoTotal * row.share);
    const gastado =
      index === PRESUP_PCT.length - 1
        ? gastadoRestante
        : Math.min(gastadoRestante, Math.round(gastadoTotal * row.share));
    gastadoRestante -= gastado;
    const disponible = Math.max(0, presupuesto - gastado);
    const usadoPct = presupuesto ? Math.round((gastado / presupuesto) * 100) : 0;
    return {
      categoria: row.categoria,
      presupuesto,
      gastado,
      disponible,
      usadoPct,
      usadoBarTono: barTonoUsado(usadoPct, gastadoPct),
    };
  });
}

function materialesDesdeProyecto(
  fila: RentabilidadProyectoFila,
  gastado: number,
  seed: number,
): RentabilidadProyectoDetalle['materiales'] {
  const matShare = 0.72;
  const matGasto = Math.round(gastado * matShare);
  if (matGasto < 800) {
    return [
      {
        material: 'Materiales varios',
        cotizadoTexto: `—`,
        compradoTexto: ctxFormatMonto(matGasto),
        cantPct: null,
        difPrecio: null,
      },
    ];
  }
  const items = [
    'Lámina galvanizada 12 ft',
    'Cemento Gris Holcim 50 kg',
    'Tubería PVC 2"',
    'Accesorios y misceláneos',
  ];
  const shares = [0.52, 0.24, 0.12, 0.12];
  let rest = matGasto;
  return items.map((material, i) => {
    const monto =
      i === items.length - 1 ? rest : Math.min(rest, Math.round(matGasto * shares[i]));
    rest -= monto;
    if (i < 3) {
      const uds = 40 + (seed % 120) + i * 30;
      const cot = 14 + i * 2 + (seed % 3);
      const comp = cot + (fila.margenFechaBajo ? 1.2 : 0.4);
      const dif = Math.round(uds * (comp - cot));
      return {
        material,
        cotizadoTexto: `${uds} uds · $${cot}/u · ${ctxFormatMonto(uds * cot)}`,
        compradoTexto: `${uds + (i === 1 ? -8 : 0)} uds · $${comp.toFixed(2)}/u · ${ctxFormatMonto(monto)}`,
        cantPct: 92 + (i % 3) * 3,
        cantPctAlto: i === 2 && fila.margenFechaBajo,
        difPrecio: dif > 0 ? dif : null,
      };
    }
    return {
      material,
      cotizadoTexto: null,
      compradoTexto: ctxFormatMonto(monto),
      cantPct: null,
      difPrecio: null,
    };
  });
}

/** ponytail: mock text only — no empresa currency helper here */
function ctxFormatMonto(n: number): string {
  return `$${n.toLocaleString('en-US')}`;
}

function serieAcumulada(final: number, pasos: number): number[] {
  const out: number[] = [];
  for (let i = 0; i < pasos; i++) {
    const t = (i + 1) / pasos;
    out.push(Math.round(final * (0.55 + t * 0.45)));
  }
  out[pasos - 1] = final;
  return out;
}

export function detalleProyectoDesdeFila(fila: RentabilidadProyectoFila): RentabilidadProyectoDetalle {
  if (fila.detalle) {
    return fila.detalle;
  }

  const seed = semilla(fila.nombre);
  const facturado = Math.round(fila.contrato * (fila.facturadoPct / 100));
  const presupuestoTotal = Math.round(fila.contrato * (1 - fila.margenPlaneadoPct / 100));
  const gastado = Math.round(presupuestoTotal * (fila.presupuestoGastadoPct / 100));
  const utilidadFecha = facturado - gastado;
  const cobrado = Math.max(0, facturado - fila.porCobrar);
  const utilidadPlaneada = fila.contrato - presupuestoTotal;
  const contratoCobradoPct = fila.contrato
    ? Math.round((cobrado / fila.contrato) * 100)
    : 0;

  const cobradoBarTono: RentabilidadProyectoBarTono =
    contratoCobradoPct < 50 ? 'verde' : 'verde';

  return {
    porFacturar: fila.contrato - facturado,
    facturado,
    cobrado,
    gastado,
    pagadoObra: gastado,
    utilidadFecha,
    contratoCobradoPct,
    avances: [
      { etiqueta: 'Contrato facturado', pct: fila.facturadoPct, barTono: 'azul' },
      {
        etiqueta: 'Presupuesto gastado',
        pct: fila.presupuestoGastadoPct,
        barTono: fila.presupuestoBarTono,
      },
      { etiqueta: 'Contrato cobrado', pct: contratoCobradoPct, barTono: cobradoBarTono },
    ],
    resultado: [
      { concepto: 'Venta', planeado: fila.contrato, fecha: facturado, formato: 'monto' },
      { concepto: 'Costo', planeado: presupuestoTotal, fecha: gastado, formato: 'monto' },
      {
        concepto: 'Utilidad',
        planeado: utilidadPlaneada,
        fecha: utilidadFecha,
        formato: 'monto',
        fechaBajo: fila.margenFechaBajo,
      },
      {
        concepto: 'Margen',
        planeado: fila.margenPlaneadoPct,
        fecha: fila.margenFechaPct,
        formato: 'pct',
        fechaBajo: fila.margenFechaBajo,
      },
    ],
    costosCategoria: CATEGORIAS_GASTO.map((c) => ({
      nombre: c.nombre,
      pctGasto: c.pctGasto,
      monto: Math.round((gastado * c.pctGasto) / 100),
    })),
    presupuesto: presupuestoFilas(
      presupuestoTotal,
      gastado,
      fila.presupuestoGastadoPct,
    ),
    presupuestoTotal: {
      presupuesto: presupuestoTotal,
      gastado,
      disponible: Math.max(0, presupuestoTotal - gastado),
      usadoPct: fila.presupuestoGastadoPct,
    },
    materiales: materialesDesdeProyecto(fila, gastado, seed),
    chartLabels: ['Jul', 'Ago', 'Sep', 'Oct'],
    chartGastado: serieAcumulada(gastado, 4),
    chartFacturado: serieAcumulada(facturado, 4),
    chartCobrado: serieAcumulada(cobrado, 4),
  };
}
