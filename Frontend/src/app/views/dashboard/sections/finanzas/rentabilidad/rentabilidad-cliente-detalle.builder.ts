import {
  RentabilidadClienteDetalle,
  RentabilidadClienteFila,
  RentabilidadMontoPct,
} from './rentabilidad.model';

const PRODUCTOS_CLIENTE = [
  'Lámina galvanizada 12 ft',
  'Cemento Holcim 42.5 kg',
  'Tubería PVC 4"',
  'Pintura Sherwin 5 gal',
  'Tornillería y fijaciones',
  'Cerámica piso 45×45',
  'Taladro DeWalt 20V',
  'Otros productos',
];

const PESOS_PRODUCTO = [32, 22, 14, 11, 9, 6, 4, 2];

const SUCURSALES = [
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

function pctDe(monto: number, base: number): number {
  if (!base) {
    return 0;
  }
  return Math.round((monto / base) * 1000) / 10;
}

function obraDesdeCliente(fila: RentabilidadClienteFila): string {
  if (fila.nombre.includes('Meléndez')) {
    return 'Residencial Los Almendros';
  }
  if (fila.nombre.includes('Clínica')) {
    return 'Remodelación Clínica Santa Tecla';
  }
  if (fila.nombre.includes('Valle')) {
    return 'Bodega San Miguel';
  }
  if (fila.pagoContado) {
    return 'Venta de mostrador';
  }
  return `Proyecto ${fila.nombre.split(' ')[0]}`;
}

function facturasDesdeCliente(
  fila: RentabilidadClienteFila,
  seed: number,
): RentabilidadClienteDetalle['facturas'] {
  if (!fila.leDebe || fila.pagoContado) {
    return [];
  }
  const principal = Math.round(fila.leDebe * 0.55);
  const secundaria = fila.leDebe - principal;
  const facturas = [
    {
      codigo: `F-${1100 + (seed % 200)}`,
      fecha: fila.pagoLento ? '20 sep' : '12 oct',
      monto: principal,
      nota: fila.pagoLento ? `+${(fila.pagoDias ?? 0) - (fila.pagoPactado ?? 30)} días` : 'al día',
      notaAlerta: fila.pagoLento,
    },
  ];
  if (secundaria > 80) {
    facturas.push({
      codigo: `F-${1180 + (seed % 80)}`,
      fecha: '28 oct',
      monto: secundaria,
      nota: `${Math.max(0, (fila.pagoDias ?? 0) - 10)} días`,
      notaAlerta: false,
    });
  }
  return facturas;
}

function productosDesdeCliente(fila: RentabilidadClienteFila): RentabilidadClienteDetalle['productos'] {
  let restante = fila.compro;
  return PRODUCTOS_CLIENTE.map((producto, index) => {
    const peso = PESOS_PRODUCTO[index] ?? 2;
    const compro =
      index === PRODUCTOS_CLIENTE.length - 1
        ? restante
        : Math.min(restante, Math.round((fila.compro * peso) / 100));
    restante -= compro;
    if (!compro) {
      return null;
    }
    const deCada100 = Math.max(
      8,
      Math.min(55, fila.deCada100 + (index % 3) * 4 - (fila.deCada100Bajo ? 2 : 6)),
    );
    const normal = Math.min(55, deCada100 + 8 + (index % 4) * 2);
    return {
      producto,
      compro,
      deCada100,
      deCada100Bajo: deCada100 < normal - 4,
      normalDeCada100: normal,
    };
  }).filter((row): row is NonNullable<typeof row> => row !== null);
}

function puenteDesdeCliente(
  fila: RentabilidadClienteFila,
  descuentoPct: number,
  descuentoMonto: number,
): RentabilidadMontoPct[] {
  const ventasLista = Math.round(fila.compro * (1 + descuentoPct / 100));
  const mercaderia = -(fila.compro - fila.ganancia);
  const esperar = fila.pagoLento && !fila.pagoContado ? Math.round(fila.ganancia * 0.22) : 0;
  const leQueda = fila.ganancia - esperar;

  const pasos: RentabilidadMontoPct[] = [
    { etiqueta: 'Ventas a precio de lista', monto: ventasLista, pct: pctDe(ventasLista, fila.compro) },
    {
      etiqueta: `Descuentos (${descuentoPct}% promedio)`,
      monto: -descuentoMonto,
      pct: -pctDe(descuentoMonto, fila.compro),
    },
    { etiqueta: 'Ventas netas', monto: fila.compro, pct: 100 },
    { etiqueta: 'Mercadería', monto: mercaderia, pct: pctDe(mercaderia, fila.compro) },
    { etiqueta: 'Ganancia', monto: fila.ganancia, pct: pctDe(fila.ganancia, fila.compro) },
  ];
  if (esperar > 0) {
    pasos.push({
      etiqueta: 'Esperar su pago',
      monto: -esperar,
      pct: -pctDe(esperar, fila.compro),
    });
  }
  pasos.push({
    etiqueta: 'Le queda',
    monto: leQueda,
    pct: pctDe(leQueda, fila.compro),
  });
  return pasos;
}

export function detalleClienteDesdeFila(fila: RentabilidadClienteFila): RentabilidadClienteDetalle {
  if (fila.detalle) {
    return fila.detalle;
  }

  const seed = semilla(fila.nombre);
  const descuentoPct = fila.deCada100Bajo ? 6 + (seed % 5) : 2 + (seed % 4);
  const descuentoMonto = Math.round(fila.compro * (descuentoPct / 100));
  const esperarPago = fila.pagoLento && !fila.pagoContado ? 2 + (seed % 4) : 0;
  const mercaderia =
    100 - fila.deCada100 - fila.alFinalQuedan - esperarPago > 40
      ? 100 - fila.deCada100 - fila.alFinalQuedan - esperarPago
      : 58 + (seed % 20);

  return {
    obra: obraDesdeCliente(fila),
    deCada100Promedio: 36,
    alFinalQuedan: fila.alFinalQuedan,
    descuentoPct,
    descuentoMonto,
    cxcVencido: fila.leDebe ? Math.round(fila.leDebe * (0.35 + (seed % 25) / 100)) : 0,
    comparacionEste: {
      mercaderia,
      esperarPago,
      leQueda: fila.alFinalQuedan,
    },
    comparacionTodos: { mercaderia: 64, esperarPago: 0, leQueda: 36 },
    puente: puenteDesdeCliente(fila, descuentoPct, descuentoMonto),
    facturas: facturasDesdeCliente(fila, seed),
    productos: productosDesdeCliente(fila),
    sucursales: SUCURSALES.map((s) => ({
      etiqueta: s.etiqueta,
      monto: Math.round((fila.compro * s.pct) / 100),
      pct: s.pct,
    })),
  };
}
