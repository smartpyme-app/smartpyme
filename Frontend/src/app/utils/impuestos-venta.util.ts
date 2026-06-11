export type TipoGravadoVenta = 'gravada' | 'exenta' | 'no_sujeta';

export function redondearMoneda(n: number): number {
  return Math.round(n * 100) / 100;
}

export function redondear4(n: number): number {
  return Math.round(n * 10000) / 10000;
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

  const subTotalSinIva = redondear4(cantidad * precioSinIva);
  const totalSinIva = redondear4(subTotalSinIva - descuento);

  detalle.sub_total = subTotalSinIva.toFixed(4);
  detalle.total = totalSinIva.toFixed(4);

  const factorIva = pct > 0 ? 1 + pct / 100 : 1;
  const precioConIva = pct > 0 ? precioSinIva * factorIva : precioSinIva;
  const preservePrecioIva = options?.preservePrecioIva ?? false;
  if (!preservePrecioIva) {
    if (pct > 0) {
      detalle.precio_iva = redondear4(precioConIva).toFixed(4);
    } else if (detalle.precio_iva == null || detalle.precio_iva === '') {
      detalle.precio_iva = precioSinIva.toFixed(4);
    }
  }
  const descuentoConIva = pct > 0 ? descuento * factorIva : descuento;
  const totalConIva = redondearMoneda(cantidad * precioConIva - descuentoConIva);

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
  const suma = (detalles || []).reduce((acc, d) => {
    const totalIva = parseFloat(String(d?.total_iva ?? ''));
    if (Number.isFinite(totalIva)) {
      return acc + totalIva;
    }
    const gravada = parseFloat(String(d?.gravada ?? 0)) || 0;
    const exenta = parseFloat(String(d?.exenta ?? 0)) || 0;
    const noSujeta = parseFloat(String(d?.no_sujeta ?? 0)) || 0;
    const iva = parseFloat(String(d?.iva ?? 0)) || 0;
    return acc + gravada + exenta + noSujeta + iva;
  }, 0);
  return redondearMoneda(suma);
}

/**
 * Subtotal de encabezado de venta: por línea round(precio×cantidad−descuento, 2) y luego suma.
 * Misma regla que BD y DTE MH (evita drift al sumar detalle.total vía SumPipe).
 */
export function sumarSubTotalEncabezadoVenta(detalles: any[]): number {
  const suma = (detalles || []).reduce((acc, d) => {
    const precio = parseFloat(String(d?.precio ?? 0)) || 0;
    const cantidad = parseFloat(String(d?.cantidad ?? 0)) || 0;
    const descuento = parseFloat(String(d?.descuento ?? 0)) || 0;
    const linea = Math.round((precio * cantidad - descuento) * 100) / 100;
    return acc + linea;
  }, 0);
  return Math.round(suma * 100) / 100;
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

function pctIgual(a: number, b: number): boolean {
  return Math.abs(Number(a) - Number(b)) < 0.01;
}

/** Copia impuestos del producto al detalle (varios impuestos en paralelo sobre la base). */
export function copiarImpuestosProductoAlDetalle(
  detalle: any,
  producto: any,
  empresaIva: unknown
): void {
  if (Array.isArray(producto?.impuestos) && producto.impuestos.length > 0) {
    detalle.impuestos = producto.impuestos.map((i: any) => ({
      id: i.id,
      nombre: i.nombre,
      porcentaje: Number(i.porcentaje),
      codigo_mh: i.codigo_mh,
    }));
    const suma = detalle.impuestos.reduce(
      (s: number, i: any) => s + Number(i.porcentaje || 0),
      0
    );
    detalle.porcentaje_impuesto =
      suma > 0
        ? suma
        : normalizarPorcentajeImpuestoDetalle(producto.porcentaje_impuesto, empresaIva);
    return;
  }
  detalle.impuestos = [];
}

/**
 * Acumula montos en venta.impuestos[] por tasa.
 * Si el detalle tiene impuestos[] (multi-impuesto), reparte gravada × cada tasa.
 * Si no, usa porcentaje_impuesto legacy (un solo impuesto).
 */
export function acumularMontosImpuestosVenta(
  ventaImpuestos: any[],
  detalles: any[],
  cobrarImpuestos: boolean,
  empresaIva: number
): void {
  if (!ventaImpuestos?.length) {
    return;
  }

  ventaImpuestos.forEach((imp: any) => {
    imp.monto = 0;
  });

  if (!cobrarImpuestos) {
    return;
  }

  const porcentajesImpuestos = ventaImpuestos.map((i: any) => Number(i.porcentaje));
  const pctDetalleLegacy = (d: any) =>
    resolverPorcentajeImpuestoVenta(d.porcentaje_impuesto, empresaIva, true);

  let ivaSinAsignar = 0;

  (detalles || []).forEach((d: any) => {
    const tipo = (d.tipo_gravado || 'gravada').toLowerCase();
    if (tipo !== 'gravada') {
      return;
    }

    const gravada = parseFloat(d.gravada || 0);
    if (gravada <= 0) {
      return;
    }

    if (Array.isArray(d.impuestos) && d.impuestos.length > 0) {
      d.impuestos.forEach((di: any) => {
        const pct = Number(di.porcentaje) || 0;
        const ventaImp = ventaImpuestos.find(
          (vi: any) =>
            (di.id != null && vi.id === di.id) || pctIgual(vi.porcentaje, pct)
        );
        const montoLinea = parseFloat((gravada * (pct / 100)).toFixed(4));
        if (ventaImp) {
          ventaImp.monto = parseFloat(
            (parseFloat(ventaImp.monto) + montoLinea).toFixed(4)
          );
        } else {
          ivaSinAsignar += montoLinea;
        }
      });
      return;
    }

    const pct = pctDetalleLegacy(d);
    const ventaImp = ventaImpuestos.find((vi: any) => pctIgual(vi.porcentaje, pct));
    const ivaLinea =
      d.iva != null && d.iva !== '' && parseFloat(d.iva) > 0
        ? parseFloat(d.iva)
        : gravada * (pct / 100);

    if (ventaImp) {
      ventaImp.monto = parseFloat(
        (parseFloat(ventaImp.monto) + ivaLinea).toFixed(4)
      );
    } else if (!porcentajesImpuestos.some((p: number) => pctIgual(p, pct))) {
      ivaSinAsignar += ivaLinea;
    }
  });

  if (ivaSinAsignar > 0) {
    const impuestoDestino =
      ventaImpuestos.find((i: any) => pctIgual(Number(i.porcentaje), empresaIva)) ||
      ventaImpuestos[0];
    impuestoDestino.monto = parseFloat(
      (parseFloat(impuestoDestino.monto) + ivaSinAsignar).toFixed(4)
    );
  }

  ventaImpuestos.forEach((imp: any) => {
    imp.monto = parseFloat(Number(imp.monto).toFixed(4));
  });
}
