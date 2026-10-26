import {
  RentabilidadProductoDetalle,
  RentabilidadProductoTablaFila,
} from './rentabilidad.model';

const PROVEEDOR_POR_CATEGORIA: Record<string, string> = {
  Construcción: 'Aceros de Centroamérica',
  Acabados: 'Sherwin Williams Dist.',
  Fontanería: 'Plásticos Modernos (PVC)',
  Ferretería: 'Distribuidora DeWalt / Truper',
  Herramientas: 'Distribuidora DeWalt / Truper',
  Especiales: 'Importadora regional',
};

const CLIENTES_PLANTILLA = [
  'Público general (mostrador)',
  'Constructora Meléndez',
  'Obras del Valle S.A.',
  'Ing. R. Portillo',
  'Ferre-Express (reventa)',
  'Otros clientes a crédito (14)',
];

const PCT_SUCURSALES = [
  { etiqueta: 'Centro', pct: 42 },
  { etiqueta: 'Santa Tecla', pct: 34 },
  { etiqueta: 'San Miguel', pct: 24 },
];

function semilla(nombre: string): number {
  let h = 0;
  for (const ch of nombre) {
    h = (h * 31 + ch.charCodeAt(0)) | 0;
  }
  return Math.abs(h);
}

function seriePrecioCosto(
  precioFinal: number,
  costoFinal: number,
  costoVsPct: number,
): { precio: number[]; costo: number[] } {
  const pasos = 7;
  const factorCosto = 1 + costoVsPct / 100;
  const costoInicio = costoFinal / factorCosto;
  const precioInicio = precioFinal * 0.98;
  const precio: number[] = [];
  const costo: number[] = [];
  for (let i = 0; i < pasos; i++) {
    const t = i / (pasos - 1);
    precio.push(Math.round((precioInicio + (precioFinal - precioInicio) * t) * 100) / 100);
    costo.push(Math.round((costoInicio + (costoFinal - costoInicio) * t) * 100) / 100);
  }
  return { precio, costo };
}

function detalleProductoInactivo(
  fila: RentabilidadProductoTablaFila,
): RentabilidadProductoDetalle {
  const inv =
    fila.inventario.tipo === 'sinVenta'
      ? fila.inventario.monto
      : fila.inventario.tipo === 'agota'
        ? 420
        : 680;
  return {
    proveedor: PROVEEDOR_POR_CATEGORIA[fila.categoria] ?? 'Proveedor varios',
    utilidadAnio: 0,
    costoUnitario: 0,
    costoUnitarioVsPct: 0,
    precioUnitario: 0,
    precioUnitarioVsPct: 0,
    inventarioValor: inv,
    precioCostoLabels: ["Sep '25", 'Nov', 'Ene', 'Mar', 'May', 'Jul', 'Sep'],
    precioSerie: [0, 0, 0, 0, 0, 0, 0],
    costoSerie: [0, 0, 0, 0, 0, 0, 0],
    sucursales: PCT_SUCURSALES.map((s) => ({ etiqueta: s.etiqueta, monto: 0, pct: s.pct })),
    clientes: [],
    vendidoConDescuentoPct: 0,
    vendidoConDescuentoMonto: 0,
  };
}

export function detalleProductoDesdeFila(
  fila: RentabilidadProductoTablaFila,
): RentabilidadProductoDetalle {
  if (fila.detalle) {
    return fila.detalle;
  }
  if (!fila.ventas || !fila.unidades) {
    return detalleProductoInactivo(fila);
  }

  const seed = semilla(fila.nombre);
  const margenPct = fila.margenPct ?? 18;
  const precioUnitario = Math.round((fila.ventas / fila.unidades) * 100) / 100;
  const costoUnitario =
    Math.round(precioUnitario * (1 - margenPct / 100) * 100) / 100;
  const costoVs = fila.costoVsPct ?? 4 + (seed % 5);
  const precioVs = fila.precioVsPct ?? 2;
  const margenAnio = fila.margenAnioPct ?? margenPct;
  const utilidadAnio = Math.round(fila.ventas * 1.08 * (margenAnio / 100));

  const { precio, costo } = seriePrecioCosto(precioUnitario, costoUnitario, costoVs);

  const sucursales = PCT_SUCURSALES.map((s) => ({
    etiqueta: s.etiqueta,
    monto: Math.round((fila.ventas * s.pct) / 100),
    pct: s.pct,
  }));

  const pesos = [28, 22, 18, 12, 10, 10];
  let unidadesRestantes = fila.unidades;
  const clientes = CLIENTES_PLANTILLA.map((nombre, index) => {
    const peso = pesos[index] ?? 5;
    const unidades =
      index === CLIENTES_PLANTILLA.length - 1
        ? unidadesRestantes
        : Math.min(unidadesRestantes, Math.round((fila.unidades * peso) / 100));
    unidadesRestantes -= unidades;
    const descuentoPct =
      index === 1 || index === 4 ? 8 + (seed % 4) : index === 0 ? null : 3 + (index % 4);
    const precioCli =
      descuentoPct === null
        ? precioUnitario * 1.02
        : precioUnitario * (1 - (descuentoPct ?? 0) / 100);
    const margenCli = Math.max(8, margenPct - (descuentoPct ?? 0) * 0.9 + (index % 3) * 2);
    return {
      nombre,
      unidades,
      precio: Math.round(precioCli * 100) / 100,
      descuentoPct,
      margenPct: Math.round(margenCli * 10) / 10,
      margenBajo: margenCli < (margenAnio - 2),
    };
  });

  const vendidoConDescuentoPct = fila.margenBajo ? 58 + (seed % 15) : 32 + (seed % 20);
  const vendidoConDescuentoMonto = Math.round(
    (fila.utilidad * vendidoConDescuentoPct) / 100,
  );

  return {
    proveedor: PROVEEDOR_POR_CATEGORIA[fila.categoria] ?? 'Proveedor varios',
    utilidadAnio,
    costoUnitario,
    costoUnitarioVsPct: costoVs,
    precioUnitario,
    precioUnitarioVsPct: precioVs,
    inventarioValor:
      fila.inventario.tipo === 'sinVenta'
        ? fila.inventario.monto
        : Math.round(costoUnitario * fila.unidades * 0.15),
    precioCostoLabels: ["Sep '25", 'Nov', 'Ene', 'Mar', 'May', 'Jul', 'Sep'],
    precioSerie: precio,
    costoSerie: costo,
    sucursales,
    clientes,
    vendidoConDescuentoPct,
    vendidoConDescuentoMonto,
  };
}

/** ponytail: cada fila activa debe poder armar detalle */
export function detalleProductoBuilderSelfCheck(
  filas: RentabilidadProductoTablaFila[],
): boolean {
  return filas.every((fila) => detalleProductoDesdeFila(fila).proveedor.length > 0);
}
