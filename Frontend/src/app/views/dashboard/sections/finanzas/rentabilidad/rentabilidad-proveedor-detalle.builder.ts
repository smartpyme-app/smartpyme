import {
  RentabilidadProveedorDetalle,
  RentabilidadProveedorFila,
  RentabilidadProveedorResumenFila,
} from './rentabilidad.model';

const PRODUCTO_POR_PROVEEDOR: Record<string, string> = {
  'Holcim El Salvador': 'Cemento Holcim 42.5 kg',
  'Aceros de Centroamérica': 'Lámina galvanizada 12 ft',
  'Sherwin Williams Dist.': 'Pintura Sherwin 5 gal',
  'Distribuidora DeWalt / Truper': 'Taladro DeWalt 20V',
  'Plásticos Modernos (PVC)': 'Tubería PVC 4"',
};

const COTIZACION_ALTERNA: Record<string, { fuente: string; vsPct: number }> = {
  'Holcim El Salvador': { fuente: 'Cemex', vsPct: -2.4 },
  'Aceros de Centroamérica': { fuente: 'Ferromax', vsPct: -5.7 },
  'Sherwin Williams Dist.': { fuente: 'PPG Comex', vsPct: -4.1 },
  'Distribuidora DeWalt / Truper': { fuente: 'Makita Dist.', vsPct: -3.2 },
  'Plásticos Modernos (PVC)': { fuente: 'Tigre', vsPct: -6.5 },
};

const CLIENTES_PROV = [
  'Constructora Meléndez',
  'Público general (mostrador)',
  'Obras del Valle S.A.',
  'Ing. R. Portillo',
  'Ferre-Express (reventa)',
  'Otros clientes a crédito (14)',
];

const PESOS_CLIENTE = [28, 24, 18, 12, 10, 8];

function semilla(nombre: string): number {
  let h = 0;
  for (const ch of nombre) {
    h = (h * 31 + ch.charCodeAt(0)) | 0;
  }
  return Math.abs(h);
}

function ventasDesdeCompra(fila: RentabilidadProveedorFila): number {
  return fila.compro + fila.ganoAlVender;
}

function resumenDesdeFila(fila: RentabilidadProveedorFila): RentabilidadProveedorResumenFila[] {
  const mesCompra = fila.compro;
  const mesVenta = ventasDesdeCompra(fila);
  const mesUtilidad = fila.ganoAlVender;
  const margenMes = mesVenta ? (mesUtilidad / mesVenta) * 100 : 0;
  const factorAnual = 9.6;

  return [
    {
      concepto: 'Compras al proveedor',
      anual: Math.round(mesCompra * factorAnual),
      promedio: Math.round(mesCompra * 1.07),
      mes: mesCompra,
      formato: 'monto',
    },
    {
      concepto: 'Ventas de sus productos',
      anual: Math.round(mesVenta * factorAnual),
      promedio: Math.round(mesVenta * 1.06),
      mes: mesVenta,
      formato: 'monto',
    },
    {
      concepto: 'Utilidad',
      anual: Math.round(mesUtilidad * factorAnual),
      promedio: Math.round(mesUtilidad * 1.05),
      mes: mesUtilidad,
      formato: 'monto',
      anualTono: 'verde',
    },
    {
      concepto: 'Margen',
      anual: Math.round((margenMes + 2) * 10) / 10,
      promedio: 0,
      mes: Math.round(margenMes * 10) / 10,
      formato: 'pct',
      mesTono: margenMes < 18 ? 'rojo' : 'normal',
    },
  ];
}

function chartDesdeFila(fila: RentabilidadProveedorFila): {
  labels: string[];
  compras: number[];
  ventas: number[];
  margen: number[];
} {
  const labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep'];
  const mes = fila.compro;
  const ventaMes = ventasDesdeCompra(fila);
  const margenFin = ventaMes ? (fila.ganoAlVender / ventaMes) * 100 : 20;
  const drift = fila.precio6mPct > 5 ? -0.7 : fila.precio6mPct < 0 ? 0.25 : -0.15;

  const compras: number[] = [];
  const ventas: number[] = [];
  const margen: number[] = [];
  for (let i = 0; i < labels.length; i++) {
    const t = 0.82 + (i / (labels.length - 1)) * 0.18;
    compras.push(Math.round(mes * t * (0.98 + (i % 3) * 0.01)));
    ventas.push(Math.round(ventaMes * t * (0.99 + (i % 2) * 0.01)));
    margen.push(Math.round((margenFin - drift * (labels.length - 1 - i)) * 10) / 10);
  }
  compras[labels.length - 1] = mes;
  ventas[labels.length - 1] = ventaMes;
  margen[labels.length - 1] = Math.round(margenFin * 10) / 10;
  return { labels, compras, ventas, margen };
}

function costosProducto(fila: RentabilidadProveedorFila, seed: number): {
  hoy: number;
  hace6m: number;
  margenPct: number;
} {
  const ventaMes = ventasDesdeCompra(fila);
  const margenPct = ventaMes ? (fila.ganoAlVender / ventaMes) * 100 : 22;
  const precioRef = 12 + (seed % 80) / 10;
  const costoHoy = Math.round(precioRef * (1 - margenPct / 100) * 100) / 100;
  const factor6m = 1 - fila.precio6mPct / 100;
  const costo6m = Math.round((costoHoy * factor6m) * 100) / 100;
  return { hoy: costoHoy, hace6m: costo6m, margenPct: Math.round(margenPct * 10) / 10 };
}

export function detalleProveedorDesdeFila(
  fila: RentabilidadProveedorFila,
): RentabilidadProveedorDetalle {
  if (fila.detalle) {
    return fila.detalle;
  }

  const seed = semilla(fila.nombre);
  const productoNombre = PRODUCTO_POR_PROVEEDOR[fila.nombre] ?? 'Producto principal';
  const costos = costosProducto(fila, seed);
  const chart = chartDesdeFila(fila);
  const cotizacion = COTIZACION_ALTERNA[fila.nombre] ?? {
    fuente: 'Otro distribuidor',
    vsPct: -4,
  };
  const otraCotizacionMonto =
    Math.round(costos.hoy * (1 + cotizacion.vsPct / 100) * 100) / 100;

  let ventasRestante = ventasDesdeCompra(fila);
  const clientes = CLIENTES_PROV.map((nombre, index) => {
    const peso = PESOS_CLIENTE[index] ?? 8;
    const ventas =
      index === CLIENTES_PROV.length - 1
        ? ventasRestante
        : Math.min(ventasRestante, Math.round((ventasDesdeCompra(fila) * peso) / 100));
    ventasRestante -= ventas;
    const margenPct = Math.max(
      12,
      Math.min(38, costos.margenPct + (index % 4) * 2 - (index === 0 ? 6 : 0)),
    );
    return {
      nombre,
      ventas,
      margenPct,
      margenBajo: margenPct < 18,
    };
  }).filter((c) => c.ventas > 0);

  const porPagar =
    fila.diasPago <= 21
      ? [
          { fecha: '16 oct', monto: Math.round(fila.compro * 0.55) },
          { fecha: '4 nov', monto: Math.round(fila.compro * 0.28) },
        ]
      : [{ fecha: '30 oct', monto: Math.round(fila.compro * 0.72) }];

  return {
    ganaPorDolar6m: Math.round(fila.ganaPorDolar * (1 + (fila.precio6mPct > 0 ? 0.15 : -0.05)) * 100) / 100,
    costoLamina: costos.hoy,
    costoLaminaVs6mPct: fila.precio6mPct,
    precioVentaVs6mPct: Math.max(-2, 3 - fila.precio6mPct / 3),
    diasPagoPromedioOtros: 32,
    otraCotizacionMonto,
    otraCotizacionFuente: cotizacion.fuente,
    otraCotizacionVsPct: cotizacion.vsPct,
    resumen: resumenDesdeFila(fila),
    chartLabels: chart.labels,
    chartCompras: chart.compras,
    chartVentas: chart.ventas,
    chartMargen: chart.margen,
    productoNombre,
    productoCompras: fila.compro,
    productoCostoHoy: costos.hoy,
    productoCosto6m: costos.hace6m,
    productoMargenPct: costos.margenPct,
    porPagar,
    clientes,
  };
}
