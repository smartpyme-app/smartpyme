export type TipoGravadoVenta = 'gravada' | 'exenta' | 'no_sujeta';

export function redondearMoneda(n: number): number {
  if (!Number.isFinite(n)) {
    return 0;
  }
  // Evita que 25.4891 (float) caiga en 25.48 por drift binario al redondear.
  const sign = n < 0 ? -1 : 1;
  const cents = Math.round(Math.abs(n) * 100 + 1e-10);
  return (sign * cents) / 100;
}

export function redondear4(n: number): number {
  return Math.round(n * 10000) / 10000;
}

/**
 * Tipo gravado efectivo por línea.
 * Sin IVA en cabecera, una línea gravada se trata como exenta (no como no_sujeta).
 * Exenta manual (usuario) se respeta; exenta automática se recupera si hay IVA.
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
    // Usuario eligió Exenta en el selector: no forzar gravada.
    if (detalle?.exenta_manual) {
      return 'exenta';
    }
    // Auto-exenta (sin IVA reconocido o Con IVA off): recuperar si ahora hay IVA.
    if (cobrarImpuestos && pctImpuesto > 0) {
      detalle.exenta_por_sin_iva = false;
      return 'gravada';
    }
    return 'exenta';
  }
  if (cobrarImpuestos && pctImpuesto > 0) {
    return 'gravada';
  }
  // Gravada sin IVA efectivo → exenta automática (recuperable al reactivar IVA).
  detalle.exenta_por_sin_iva = true;
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
  const tipo = String(detalle?.tipo_gravado || '').toLowerCase();
  detalle.exenta_manual = tipo === 'exenta' || tipo === 'no_sujeta';
}

function pctIgual(a: number, b: number): boolean {
  return Math.abs(Number(a) - Number(b)) < 0.01;
}

/**
 * Tasas IVA regionales además del IVA default de la empresa.
 * HN 15/18, CR 1/2/4/8/13, SV 13, GT 12, BZ 12.5, PA 7, MX 16.
 * El 5% (turismo) se excluye explícitamente en esImpuestoIva.
 */
const TASAS_IVA_REGIONALES = [1, 2, 4, 7, 8, 12, 12.5, 13, 15, 16, 18];

/** Códigos MH CAT-015 de tributos que NO son IVA (p. ej. turismo C8). */
const CODIGOS_MH_NO_IVA = new Set(['C8']);

/**
 * Identifica IVA vs tributos especiales (turismo, etc.).
 * - codigo_mh '20' → IVA (MH El Salvador)
 * - codigo MH especial conocido (C8…) → no IVA
 * - 5% → no IVA (turismo)
 * - resto: IVA si coincide con empresa.iva o tasa regional (HN 15/18, CR 1/2/4/8/13…)
 */
export function esImpuestoIva(
  imp: { codigo_mh?: string | null; porcentaje?: number } | null | undefined,
  ivaEmpresa?: unknown
): boolean {
  if (!imp) return false;
  const codigo = imp.codigo_mh != null ? String(imp.codigo_mh).trim() : '';
  if (codigo === '20') return true;
  if (codigo && CODIGOS_MH_NO_IVA.has(codigo)) return false;

  const pct = Number(imp.porcentaje) || 0;
  if (pct <= 0 || pctIgual(pct, 5)) {
    return false;
  }
  const iva = Number(ivaEmpresa ?? 0) || 0;
  if (iva > 0 && pctIgual(pct, iva)) {
    return true;
  }
  return TASAS_IVA_REGIONALES.some((t) => pctIgual(pct, t));
}

/**
 * Base gravada por línea para impuestos: misma regla que el subtotal de cabecera
 * (round(precio×cantidad−descuento, 2)). El IVA se calcula sobre esta base y se
 * redondea solo al acumular en cabecera, no por línea.
 */
export function baseGravadaLineaImpuesto(detalle: any): number {
  const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
  const precio = parseFloat(String(detalle?.precio ?? 0)) || 0;
  const descuento = parseFloat(String(detalle?.descuento ?? 0)) || 0;
  return redondearMoneda(cantidad * precio - descuento);
}

/** Base para impuestos especiales: gravada o exenta; nunca no_sujeta. */
export function baseParaImpuestosEspeciales(detalle: any): number {
  const tipo = String(detalle?.tipo_gravado || 'gravada').toLowerCase();
  if (tipo === 'no_sujeta') return 0;
  const gravada = parseFloat(detalle?.gravada || 0) || 0;
  if (gravada > 0) return gravada;
  const exenta = parseFloat(detalle?.exenta || 0) || 0;
  return exenta > 0 ? exenta : 0;
}

export function porcentajeIvaDetalle(
  detalle: any,
  ivaEmpresa: unknown,
  cobrarIva: boolean
): number {
  if (!cobrarIva) return 0;
  if (Array.isArray(detalle?.impuestos) && detalle.impuestos.length > 0) {
    const ivas = detalle.impuestos.filter((i: any) => esImpuestoIva(i, ivaEmpresa));
    if (ivas.length === 0) return 0;
    return ivas.reduce((s: number, i: any) => s + (Number(i.porcentaje) || 0), 0);
  }
  const pct = Number(detalle?.porcentaje_impuesto ?? ivaEmpresa ?? 0) || 0;
  if (pct === 5) return 0;
  return pct > 0 ? pct : Number(ivaEmpresa ?? 0) || 0;
}

/**
 * Calcula gravada/exenta/no_sujeta, IVA y total con IVA por línea.
 * gravada/total_iva se redondean a moneda por línea (DTE); detalle.iva queda sin redondear
 * para acumular en cabecera y redondear solo al mostrar.
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
  const pct = porcentajeIvaDetalle(detalle, ivaEmpresa, cobrarImpuestos);
  const tipo = resolverTipoGravadoEfectivo(detalle, cobrarImpuestos, pct);
  detalle.tipo_gravado = tipo;

  const subTotalSinIva = redondear4(cantidad * precioSinIva);
  const totalSinIva = redondear4(subTotalSinIva - descuento);

  detalle.sub_total = subTotalSinIva.toFixed(2);
  detalle.total = totalSinIva.toFixed(2);

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
      detalle.total_iva = totalConIva.toFixed(2);
      detalle.iva = gravadaMoneda * (pct / 100);
      break;
    }
    case 'exenta': {
      const monto = redondearMoneda(totalSinIva);
      detalle.exenta = monto;
      detalle.total_iva = monto.toFixed(2);
      detalle.iva = 0;
      break;
    }
    case 'no_sujeta': {
      const monto = redondearMoneda(totalSinIva);
      detalle.no_sujeta = monto;
      detalle.total_iva = monto.toFixed(2);
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

/** Si el detalle-impuesto tiene id, empareja por el id maestro; si no, por porcentaje. */
function encontrarVentaImpuesto(ventaImpuestos: any[], di: any): any | undefined {
  if (di.id != null) {
    return ventaImpuestos.find(
      (vi: any) => vi.id_impuesto === di.id || vi.id === di.id
    );
  }
  const pct = Number(di.porcentaje) || 0;
  return ventaImpuestos.find((vi: any) => pctIgual(vi.porcentaje, pct));
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
 * Completa impuestos omitidos por cargas legacy usando únicamente los impuestos
 * reales incluidos en el producto relacionado.
 */
export function hidratarImpuestosProductosEnDetalles(
  detalles: any[],
  empresaIva: unknown
): void {
  (detalles || []).forEach((detalle: any) => {
    if (
      (!Array.isArray(detalle?.impuestos) || detalle.impuestos.length === 0) &&
      Array.isArray(detalle?.producto?.impuestos) &&
      detalle.producto.impuestos.length > 0
    ) {
      copiarImpuestosProductoAlDetalle(detalle, detalle.producto, empresaIva);
    }
  });
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

  const porcentajesImpuestos = ventaImpuestos.map((i: any) => Number(i.porcentaje));
  const pctDetalleLegacy = (d: any) =>
    resolverPorcentajeImpuestoVenta(d.porcentaje_impuesto, empresaIva, true);
  const esPctIvaLegacy = (pct: number) => esImpuestoIva({ porcentaje: pct }, empresaIva);

  let ivaSinAsignar = 0;

  (detalles || []).forEach((d: any) => {
    const tipo = (d.tipo_gravado || 'gravada').toLowerCase();

    if (Array.isArray(d.impuestos) && d.impuestos.length > 0) {
      d.impuestos.forEach((di: any) => {
        const pct = Number(di.porcentaje) || 0;
        const ventaImp = encontrarVentaImpuesto(ventaImpuestos, di);

        if (esImpuestoIva(di, empresaIva)) {
          if (!cobrarImpuestos || tipo !== 'gravada') {
            return;
          }
          const base = baseGravadaLineaImpuesto(d);
          if (base <= 0) {
            return;
          }
          const montoLinea = base * (pct / 100);
          if (ventaImp) {
            ventaImp.monto = (parseFloat(String(ventaImp.monto ?? 0)) || 0) + montoLinea;
          } else if (di.id == null) {
            ivaSinAsignar += montoLinea;
          }
          return;
        }

        const base = baseParaImpuestosEspeciales(d);
        if (base <= 0) {
          return;
        }
        const montoLinea = base * (pct / 100);
        if (ventaImp) {
          ventaImp.monto = (parseFloat(String(ventaImp.monto ?? 0)) || 0) + montoLinea;
        }
      });
      return;
    }

    const pct = pctDetalleLegacy(d);
    const ventaImp = ventaImpuestos.find((vi: any) => pctIgual(vi.porcentaje, pct));

    if (esPctIvaLegacy(pct)) {
      if (!cobrarImpuestos || tipo !== 'gravada') {
        return;
      }
      const base = baseGravadaLineaImpuesto(d);
      if (base <= 0) {
        return;
      }
      const ivaLinea =
        d.iva != null && d.iva !== '' && Number.isFinite(parseFloat(d.iva))
          ? parseFloat(d.iva)
          : base * (pct / 100);

      if (ventaImp) {
        ventaImp.monto = (parseFloat(String(ventaImp.monto ?? 0)) || 0) + ivaLinea;
      } else if (!porcentajesImpuestos.some((p: number) => pctIgual(p, pct))) {
        ivaSinAsignar += ivaLinea;
      }
      return;
    }
  });

  if (ivaSinAsignar > 0) {
    const impuestoDestino =
      ventaImpuestos.find((i: any) => pctIgual(Number(i.porcentaje), empresaIva)) ||
      ventaImpuestos[0];
    impuestoDestino.monto =
      (parseFloat(String(impuestoDestino.monto ?? 0)) || 0) + ivaSinAsignar;
  }

  ventaImpuestos.forEach((imp: any) => {
    imp.monto = redondearMoneda(parseFloat(String(imp.monto ?? 0)) || 0);
  });
}

/** Suma IVA por línea sin redondear el impuesto (base ya redondeada como subtotal). */
export function sumarIvaLineasSinRedondeo(
  detalles: any[],
  cobrarImpuestos: boolean,
  empresaIva: number
): number {
  return (detalles || []).reduce((acc, d) => {
    const pct = porcentajeIvaDetalle(d, empresaIva, cobrarImpuestos);
    const tipo = resolverTipoGravadoEfectivo(d, cobrarImpuestos, pct);
    if (!cobrarImpuestos || tipo !== 'gravada') {
      return acc;
    }
    const base = baseGravadaLineaImpuesto(d);
    if (base <= 0) {
      return acc;
    }
    if (d.iva != null && d.iva !== '' && Number.isFinite(parseFloat(d.iva))) {
      return acc + parseFloat(d.iva);
    }
    return acc + base * (pct / 100);
  }, 0);
}

export interface OpcionesTotalEncabezadoVenta {
  empresaIva: number;
  cuentaTerceros?: number;
  ivaPercibido?: number;
  ivaRetenido?: number;
  rentaRetenida?: number;
  descuentoPuntos?: number;
}

/**
 * Total de cabecera alineado con el desglose mostrado en pantalla:
 * subtotal + IVA + tributos especiales + cuenta a terceros + ajustes.
 */
export function sumarTotalEncabezadoVenta(
  detalles: any[],
  ventaImpuestos: any[],
  options: OpcionesTotalEncabezadoVenta
): number {
  const subtotal = sumarSubTotalEncabezadoVenta(detalles);
  const iva = montoIvaDeVentaImpuestos(ventaImpuestos, options.empresaIva);
  const especiales = montoEspecialesDeVentaImpuestos(
    ventaImpuestos,
    options.empresaIva
  );
  const ct = parseFloat(String(options.cuentaTerceros ?? 0)) || 0;
  const perc = parseFloat(String(options.ivaPercibido ?? 0)) || 0;
  const reten = parseFloat(String(options.ivaRetenido ?? 0)) || 0;
  const renta = parseFloat(String(options.rentaRetenida ?? 0)) || 0;
  const pts = parseFloat(String(options.descuentoPuntos ?? 0)) || 0;

  return redondearMoneda(subtotal + iva + especiales + ct + perc - reten - renta - pts);
}

/**
 * IVA de cabecera como diferencia entre total con IVA por línea y subtotal sin IVA.
 * Cierra sub_total + IVA con el total cuando los precios ya incluyen impuesto (facturación v2).
 */
export function calcularIvaResidualEncabezadoVenta(detalles: any[]): number {
  return redondearMoneda(
    sumarTotalConIvaEncabezadoVenta(detalles) - sumarSubTotalEncabezadoVenta(detalles)
  );
}

/**
 * IVA objetivo de cabecera.
 * - v2 (precio con IVA): si |tasa − residual| ≤ $0.01, usa residual (cierra al precio ingresado).
 * - Multi-línea sin IVA: si la diferencia es mayor, usa tasa sobre bases acumuladas.
 */
export function resolverIvaObjetivoEncabezadoVenta(
  detalles: any[],
  cobrarImpuestos: boolean,
  empresaIva: number
): number {
  const ivaPorTasa = redondearMoneda(
    sumarIvaLineasSinRedondeo(detalles, cobrarImpuestos, empresaIva)
  );
  const ivaResidual = calcularIvaResidualEncabezadoVenta(detalles);
  if (Math.abs(ivaPorTasa - ivaResidual) <= 0.01 + 1e-9) {
    return ivaResidual;
  }
  return ivaPorTasa;
}

export function montoIvaDeVentaImpuestos(
  ventaImpuestos: any[],
  empresaIva?: unknown
): number {
  return redondearMoneda(
    (ventaImpuestos || [])
      .filter((imp: any) => esImpuestoIva(imp, empresaIva))
      .reduce(
        (s: number, imp: any) => s + (parseFloat(String(imp?.monto ?? 0)) || 0),
        0
      )
  );
}

export function montoEspecialesDeVentaImpuestos(
  ventaImpuestos: any[],
  empresaIva?: unknown
): number {
  return redondearMoneda(
    (ventaImpuestos || [])
      .filter((imp: any) => !esImpuestoIva(imp, empresaIva))
      .reduce(
        (s: number, imp: any) => s + (parseFloat(String(imp?.monto ?? 0)) || 0),
        0
      )
  );
}

/**
 * Acumula impuestos y, si hay IVA, ajusta el cierre residual solo sobre el IVA
 * (precios con IVA incluido en facturación v2). No apaga tributos especiales
 * cuando cobrarImpuestos es false. Retorna solo el monto de IVA.
 */
export function acumularImpuestosVentaConCierreResidual(
  ventaImpuestos: any[],
  detalles: any[],
  cobrarImpuestos: boolean,
  empresaIva: number
): number {
  if (!ventaImpuestos?.length) {
    return cobrarImpuestos ? calcularIvaResidualEncabezadoVenta(detalles) : 0;
  }

  acumularMontosImpuestosVenta(
    ventaImpuestos,
    detalles,
    cobrarImpuestos,
    empresaIva
  );

  if (!cobrarImpuestos) {
    return montoIvaDeVentaImpuestos(ventaImpuestos, empresaIva);
  }

  const ivaObjetivo = resolverIvaObjetivoEncabezadoVenta(
    detalles,
    cobrarImpuestos,
    empresaIva
  );
  const ivaAcumulado = montoIvaDeVentaImpuestos(ventaImpuestos, empresaIva);
  const delta = redondearMoneda(ivaObjetivo - ivaAcumulado);

  if (Math.abs(delta) >= 0.005) {
    const impuestoDestino =
      ventaImpuestos.find(
        (i: any) =>
          esImpuestoIva(i, empresaIva) && pctIgual(Number(i.porcentaje), empresaIva)
      ) || ventaImpuestos.find((i: any) => esImpuestoIva(i, empresaIva));
    if (impuestoDestino) {
      impuestoDestino.monto = redondearMoneda(
        (parseFloat(String(impuestoDestino.monto ?? 0)) || 0) + delta
      );
    }
  }

  return montoIvaDeVentaImpuestos(ventaImpuestos, empresaIva);
}
