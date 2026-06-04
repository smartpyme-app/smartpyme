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
