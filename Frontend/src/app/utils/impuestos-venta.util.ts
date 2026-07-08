import Decimal from 'decimal.js';

export type TipoGravadoVenta = 'gravada' | 'exenta' | 'no_sujeta';

export function redondearMoneda(n: number): number {
  return new Decimal(n).toDecimalPlaces(2, Decimal.ROUND_HALF_UP).toNumber();
}

export function redondear4(n: number): number {
  return new Decimal(n).toDecimalPlaces(4, Decimal.ROUND_HALF_UP).toNumber();
}

/**
 * Tipo gravado efectivo por línea.
 * Sin IVA en cabecera, una línea gravada se trata como exenta (no como no_sujeta).
 */
export function resolverTipoGravadoEfectivo(
  detalle: any,
  cobrarImpuestos: boolean,
  pctImpuesto: number
): TipoGravadoVenta {
  const tipo = String(detalle?.tipo_gravado || 'gravada').toLowerCase();
  if (tipo === 'no_sujeta') {
    return 'no_sujeta';
  }
  if (tipo === 'exenta') {
    return 'exenta';
  }
  if (cobrarImpuestos && pctImpuesto > 0) {
    return 'gravada';
  }
  return 'exenta';
}

/**
 * Al activar/desactivar "Con IVA" en cabecera, ajusta tipo_gravado de las líneas.
 * Gravada sin IVA → exenta; al reactivar IVA solo revierte líneas marcadas automáticamente.
 */
export function sincronizarTipoGravadoPorCobroIva(
  detalles: any[],
  cobrarImpuestos: boolean
): void {
  for (const detalle of detalles || []) {
    const tipo = String(detalle?.tipo_gravado || 'gravada').toLowerCase();
    if (!cobrarImpuestos) {
      if (tipo === 'gravada') {
        detalle.tipo_gravado = 'exenta';
        detalle.exenta_por_sin_iva = true;
      }
      continue;
    }
    if (detalle.exenta_por_sin_iva && tipo === 'exenta') {
      detalle.tipo_gravado = 'gravada';
      detalle.exenta_por_sin_iva = false;
    }
  }
}

/** Usuario cambió el tipo manualmente: no revertir a gravada al reactivar IVA. */
export function limpiarExentaPorSinIvaSiTipoManual(detalle: any): void {
  detalle.exenta_por_sin_iva = false;
}

/**
 * Calcula gravada/exenta/no_sujeta, IVA y total con IVA por línea.
 * total_iva se redondea a moneda (2 dec); el IVA cierra por diferencia con la gravada
 * redondeada para que sub_total + IVA coincida con el total al cobrar.
 */
export function calcularMontosLineaDetalle(
  detalle: any,
  cobrarImpuestos: boolean,
  ivaEmpresa: unknown,
  options?: { preservePrecioIva?: boolean }
): void {
  const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
  const precioSinIva = parseFloat(String(detalle?.precio ?? 0)) || 0;
  const descuento = parseFloat(String(detalle?.descuento ?? 0)) || 0;
  const pct = resolverPorcentajeImpuestoVenta(detalle?.porcentaje_impuesto, ivaEmpresa, cobrarImpuestos);
  const tipo = resolverTipoGravadoEfectivo(detalle, cobrarImpuestos, pct);
  detalle.tipo_gravado = tipo;

  const dCantidad = new Decimal(cantidad);
  const dPrecioSinIva = new Decimal(precioSinIva);
  const dDescuento = new Decimal(descuento);
  const dPct = new Decimal(pct);

  const subTotalSinIva = redondear4(dCantidad.times(dPrecioSinIva).toNumber());
  const totalSinIva = redondear4(new Decimal(subTotalSinIva).minus(dDescuento).toNumber());

  detalle.sub_total = subTotalSinIva.toFixed(4);
  detalle.total = totalSinIva.toFixed(4);

  const factorIva = pct > 0 ? new Decimal(1).plus(dPct.div(100)) : new Decimal(1);
  const precioConIva = pct > 0 ? dPrecioSinIva.times(factorIva).toNumber() : precioSinIva;
  const preservePrecioIva = options?.preservePrecioIva ?? false;
  if (!preservePrecioIva) {
    if (pct > 0) {
      detalle.precio_iva = redondear4(precioConIva).toFixed(4);
    } else if (detalle.precio_iva == null || detalle.precio_iva === '') {
      detalle.precio_iva = precioSinIva.toFixed(4);
    }
  }
  const descuentoConIva = pct > 0 ? dDescuento.times(factorIva).toNumber() : descuento;
  const totalConIva = redondearMoneda(
    dCantidad.times(new Decimal(precioConIva)).minus(new Decimal(descuentoConIva)).toNumber()
  );

  detalle.gravada = 0;
  detalle.exenta = 0;
  detalle.no_sujeta = 0;

  switch (tipo) {
    case 'gravada': {
      const gravadaMoneda = redondearMoneda(totalSinIva);
      detalle.gravada = gravadaMoneda;
      detalle.total_iva = totalConIva.toFixed(4);
      detalle.iva = redondear4(totalConIva - gravadaMoneda);
      break;
    }
    case 'exenta': {
      const monto = redondearMoneda(totalSinIva);
      detalle.exenta = monto;
      detalle.total_iva = monto.toFixed(4);
      detalle.iva = 0;
      break;
    }
    case 'no_sujeta': {
      const monto = redondearMoneda(totalSinIva);
      detalle.no_sujeta = monto;
      detalle.total_iva = monto.toFixed(4);
      detalle.iva = 0;
      break;
    }
    default: {
      const _exhaustive: never = tipo;
      return _exhaustive;
    }
  }
}

/** Suma el total con IVA de cada línea (redondeado a moneda por línea). */
export function sumarTotalConIvaEncabezadoVenta(detalles: any[]): number {
  const suma = (detalles || []).reduce((acc: Decimal, d: any) => {
    const totalIva = parseFloat(String(d?.total_iva ?? ''));
    if (Number.isFinite(totalIva)) {
      return acc.plus(new Decimal(totalIva));
    }
    const gravada = parseFloat(String(d?.gravada ?? 0)) || 0;
    const exenta = parseFloat(String(d?.exenta ?? 0)) || 0;
    const noSujeta = parseFloat(String(d?.no_sujeta ?? 0)) || 0;
    const iva = parseFloat(String(d?.iva ?? 0)) || 0;
    return acc.plus(new Decimal(gravada).plus(exenta).plus(noSujeta).plus(iva));
  }, new Decimal(0));
  return redondearMoneda(suma.toNumber());
}

/**
 * Subtotal de encabezado de venta: por línea round(precio×cantidad−descuento, 2) y luego suma.
 * Misma regla que BD y DTE MH (evita drift al sumar detalle.total vía SumPipe).
 */
export function sumarSubTotalEncabezadoVenta(detalles: any[]): number {
  const suma = (detalles || []).reduce((acc: Decimal, d: any) => {
    const precio = new Decimal(parseFloat(String(d?.precio ?? 0)) || 0);
    const cantidad = new Decimal(parseFloat(String(d?.cantidad ?? 0)) || 0);
    const descuento = new Decimal(parseFloat(String(d?.descuento ?? 0)) || 0);
    const linea = redondearMoneda(cantidad.times(precio).minus(descuento).toNumber());
    return acc.plus(new Decimal(linea));
  }, new Decimal(0));
  return redondearMoneda(suma.toNumber());
}

/**
 * Resuelve el % de impuesto a aplicar en una línea de venta.
 * Si el producto/detalle no tiene impuesto configurado (null/undefined/''), usa el IVA de la empresa.
 */
export function resolverPorcentajeImpuestoVenta(
  porcentajeImpuesto: unknown,
  ivaEmpresa: unknown,
  cobrarImpuestos = true
): number {
  if (!cobrarImpuestos) {
    return 0;
  }
  if (porcentajeImpuesto != null && porcentajeImpuesto !== '') {
    return Number(porcentajeImpuesto) || 0;
  }
  return Number(ivaEmpresa ?? 0) || 0;
}

/** Valor a guardar en detalle.porcentaje_impuesto (snapshot al facturar). */
export function normalizarPorcentajeImpuestoDetalle(
  porcentajeImpuesto: unknown,
  ivaEmpresa: unknown
): number | null {
  if (porcentajeImpuesto != null && porcentajeImpuesto !== '') {
    return Number(porcentajeImpuesto);
  }
  const iva = Number(ivaEmpresa ?? 0);
  return iva > 0 ? iva : null;
}
