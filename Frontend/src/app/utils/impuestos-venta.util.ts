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

/** Neto / bases intermedias: más precisión que moneda para no sesgar la suma de cabecera. */
export function redondear6(n: number): number {
  if (!Number.isFinite(n)) {
    return 0;
  }
  const sign = n < 0 ? -1 : 1;
  return (sign * Math.round(Math.abs(n) * 1e6 + 1e-10)) / 1e6;
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
 * Base gravada por línea para impuestos: misma regla que el neto de cabecera
 * (precio×cantidad−descuento a 6 decimales). El redondeo a moneda es al acumular.
 */
export function baseGravadaLineaImpuesto(detalle: any): number {
  const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
  const precio = parseFloat(String(detalle?.precio ?? 0)) || 0;
  const descuento = parseFloat(String(detalle?.descuento ?? 0)) || 0;
  return redondear6(cantidad * precio - descuento);
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

/** Temporal: solo HN trata porcentaje_impuesto=0 como exento (sin fallback a IVA empresa). */
export function esEmpresaHonduras(paisEmpresa: unknown): boolean {
  return String(paisEmpresa ?? '').trim().toLowerCase() === 'honduras';
}

export function porcentajeIvaDetalle(
  detalle: any,
  ivaEmpresa: unknown,
  cobrarIva: boolean,
  paisEmpresa?: unknown
): number {
  if (!cobrarIva) return 0;
  if (Array.isArray(detalle?.impuestos) && detalle.impuestos.length > 0) {
    const ivas = detalle.impuestos.filter((i: any) => esImpuestoIva(i, ivaEmpresa));
    if (ivas.length === 0) return 0;
    return ivas.reduce((s: number, i: any) => s + (Number(i.porcentaje) || 0), 0);
  }
  const rawPct = detalle?.porcentaje_impuesto;
  const hasExplicitPct = rawPct != null && rawPct !== '';
  // Temporal HN: 0 explícito = exento. En SV/otros, 0 o vacío sigue cayendo al IVA empresa.
  if (esEmpresaHonduras(paisEmpresa) && hasExplicitPct && Number(rawPct) === 0) {
    return 0;
  }
  const pct = Number(hasExplicitPct ? rawPct : (ivaEmpresa ?? 0)) || 0;
  if (pct === 5) return 0;
  return pct > 0 ? pct : Number(ivaEmpresa ?? 0) || 0;
}

/**
 * Calcula gravada/exenta/no_sujeta, IVA y total con IVA por línea.
 * Neto (gravada/sub_total) a 6 decimales; total_iva a moneda (2).
 * detalle.iva queda sin redondear para acumular en cabecera.
 */
export function calcularMontosLineaDetalle(
  detalle: any,
  cobrarImpuestos: boolean,
  ivaEmpresa: unknown,
  options?: { preservePrecioIva?: boolean; paisEmpresa?: unknown }
): void {
  const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
  const preservePrecioIva = options?.preservePrecioIva ?? false;
  const precioIvaRaw = detalle?.precio_iva;
  const usuarioBorroPrecioIva = preservePrecioIva && (precioIvaRaw === '' || precioIvaRaw === null);
  const precioSinIva = usuarioBorroPrecioIva
    ? 0
    : parseFloat(String(detalle?.precio ?? 0)) || 0;
  const descuento = parseFloat(String(detalle?.descuento ?? 0)) || 0;
  const pct = porcentajeIvaDetalle(
    detalle,
    ivaEmpresa,
    cobrarImpuestos,
    options?.paisEmpresa
  );
  const tipo = resolverTipoGravadoEfectivo(detalle, cobrarImpuestos, pct);
  detalle.tipo_gravado = tipo;

  const subTotalSinIva = redondear6(cantidad * precioSinIva);
  const totalSinIva = redondear6(subTotalSinIva - descuento);

  detalle.sub_total = subTotalSinIva.toFixed(6);
  detalle.total = totalSinIva.toFixed(6);

  const factorIva = pct > 0 ? 1 + pct / 100 : 1;
  const precioIvaExistente = parseFloat(String(precioIvaRaw ?? ''));
  let precioConIva: number;
  if (usuarioBorroPrecioIva) {
    // Sin valor en el input: el precio es 0 (no el neto anterior ni 1).
    detalle.precio = (0).toFixed(6);
    precioConIva = 0;
  } else if (
    preservePrecioIva &&
    Number.isFinite(precioIvaExistente) &&
    String(precioIvaRaw ?? '') !== ''
  ) {
    // Fuente de verdad del monto cobrado: precio_iva (no reconstruir desde precio neto).
    precioConIva = precioIvaExistente;
  } else if (pct > 0) {
    precioConIva = precioSinIva * factorIva;
    detalle.precio_iva = redondear4(precioConIva).toFixed(4);
  } else {
    precioConIva = precioSinIva;
    if (detalle.precio_iva == null || detalle.precio_iva === '') {
      detalle.precio_iva = precioSinIva.toFixed(4);
    }
  }
  const descuentoConIvaExistente = parseFloat(String(detalle?.descuento_con_iva ?? ''));
  const descuentoConIva =
    Number.isFinite(descuentoConIvaExistente) && String(detalle?.descuento_con_iva ?? '') !== ''
      ? descuentoConIvaExistente
      : pct > 0
        ? descuento * factorIva
        : descuento;
  const totalConIva = redondearMoneda(cantidad * precioConIva - descuentoConIva);

  // Persistibles para cotización → factura (misma base que una venta).
  detalle.precio_sin_iva = Number(precioSinIva).toFixed(6);
  detalle.precio_con_iva = redondearMoneda(precioConIva).toFixed(4);

  detalle.gravada = 0;
  detalle.exenta = 0;
  detalle.no_sujeta = 0;

  switch (tipo) {
    case 'gravada': {
      detalle.gravada = totalSinIva;
      detalle.total_iva = totalConIva.toFixed(2);
      detalle.iva = totalSinIva * (pct / 100);
      break;
    }
    case 'exenta': {
      detalle.exenta = totalSinIva;
      detalle.total_iva = redondearMoneda(totalSinIva).toFixed(2);
      detalle.iva = 0;
      break;
    }
    case 'no_sujeta': {
      detalle.no_sujeta = totalSinIva;
      detalle.total_iva = redondearMoneda(totalSinIva).toFixed(2);
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
 * Subtotal de encabezado: suma netos a 6 decimales por línea y redondea a moneda al final.
 * Evita el sesgo de round(2) por línea (p. ej. 19/1.13 → 16.81 en vez de 16.80).
 */
export function sumarSubTotalEncabezadoVenta(detalles: any[]): number {
  const suma = (detalles || []).reduce((acc, d) => {
    const precio = parseFloat(String(d?.precio ?? 0)) || 0;
    const cantidad = parseFloat(String(d?.cantidad ?? 0)) || 0;
    const descuento = parseFloat(String(d?.descuento ?? 0)) || 0;
    return acc + redondear6(precio * cantidad - descuento);
  }, 0);
  return redondearMoneda(suma);
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
  empresaIva: number,
  paisEmpresa?: unknown
): number {
  return (detalles || []).reduce((acc, d) => {
    const pct = porcentajeIvaDetalle(d, empresaIva, cobrarImpuestos, paisEmpresa);
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
  /** Si true y venta.impuestos está vacío, usa IVA residual de líneas (v2 / pedido). */
  cobrarImpuestos?: boolean;
}

/**
 * Descuento en facturación v2: se calcula sobre el precio con IVA (campo de texto)
 * y se convierte a base sin IVA para gravada/DTE.
 */
export function calcularDescuentoDesdePrecioConIva(options: {
  cantidad: number;
  precioConIva: number;
  pctIva: number;
  descuentoPorcentaje?: number;
  descuentoMontoConIva?: number;
}): { descuentoSinIva: number; descuentoConIva: number } {
  const cantidad = Number(options.cantidad) || 0;
  const precioConIva = Number(options.precioConIva) || 0;
  const pctIva = Number(options.pctIva) || 0;
  const pctDesc = Number(options.descuentoPorcentaje) || 0;
  const montoDesc = Number(options.descuentoMontoConIva) || 0;

  let descuentoConIva = 0;
  if (pctDesc) {
    descuentoConIva = cantidad * precioConIva * (pctDesc / 100);
  } else if (montoDesc) {
    descuentoConIva = cantidad * montoDesc;
  }

  const factor = pctIva > 0 ? 1 + pctIva / 100 : 1;
  const descuentoSinIva = pctIva > 0 ? descuentoConIva / factor : descuentoConIva;

  return {
    descuentoConIva: Number(descuentoConIva.toFixed(4)),
    descuentoSinIva: Number(descuentoSinIva.toFixed(4)),
  };
}

/**
 * Suma descuentos en términos con IVA (lo que el usuario ve en el campo Precio / $ descuento).
 * Preferir detalle.descuento_con_iva; si falta, reconstruir desde descuento sin IVA × factor.
 */
export function sumarDescuentoConIvaEncabezadoVenta(
  detalles: any[],
  empresaIva: number,
  cobrarImpuestos: boolean,
  paisEmpresa?: unknown
): number {
  const suma = (detalles || []).reduce((acc, d) => {
    const conIva = parseFloat(String(d?.descuento_con_iva ?? ''));
    if (Number.isFinite(conIva)) {
      return acc + conIva;
    }
    const desc = parseFloat(String(d?.descuento ?? 0)) || 0;
    const pct = porcentajeIvaDetalle(d, empresaIva, cobrarImpuestos, paisEmpresa);
    const factor = pct > 0 ? 1 + pct / 100 : 1;
    return acc + desc * factor;
  }, 0);
  return redondearMoneda(suma);
}

/**
 * Total de cabecera: suma de total_iva por línea (+ tributos especiales y ajustes).
 * No usar sub_total + IVA por tasa: eso descuadra frente a precio_iva/total_iva.
 */
export function sumarTotalEncabezadoVenta(
  detalles: any[],
  ventaImpuestos: any[],
  options: OpcionesTotalEncabezadoVenta
): number {
  const totalLineasConIva = sumarTotalConIvaEncabezadoVenta(detalles);
  const especiales = montoEspecialesDeVentaImpuestos(
    ventaImpuestos,
    options.empresaIva
  );
  const ct = parseFloat(String(options.cuentaTerceros ?? 0)) || 0;
  const perc = parseFloat(String(options.ivaPercibido ?? 0)) || 0;
  const reten = parseFloat(String(options.ivaRetenido ?? 0)) || 0;
  const renta = parseFloat(String(options.rentaRetenida ?? 0)) || 0;
  const pts = parseFloat(String(options.descuentoPuntos ?? 0)) || 0;

  return redondearMoneda(
    totalLineasConIva + especiales + ct + perc - reten - renta - pts
  );
}

/**
 * IVA de cabecera = suma(total_iva) − sub_total (neto).
 * Es la definición cuando el monto cobrado vive en precio_iva/total_iva.
 */
export function calcularIvaResidualEncabezadoVenta(
  detalles: any[],
  descuentoPuntos = 0
): number {
  const totalConIva = sumarTotalConIvaEncabezadoVenta(detalles);
  const ivaLineas = redondearMoneda(
    totalConIva - sumarSubTotalEncabezadoVenta(detalles)
  );
  const pts = redondearMoneda(parseFloat(String(descuentoPuntos ?? 0)) || 0);
  if (pts <= 0 || totalConIva <= 0) {
    return ivaLineas;
  }
  const totalTrasPuntos = Math.max(0, redondearMoneda(totalConIva - pts));
  return redondearMoneda(ivaLineas * (totalTrasPuntos / totalConIva));
}

/**
 * Prepara detalles de una cotización (u otra venta base) para facturar con la misma
 * lógica que una venta nueva: prioriza precio_con_iva/total_iva guardados.
 */
export function prepararDetallesParaFacturarDesdeCotizacion(
  detalles: any[],
  cobrarImpuestos: boolean,
  empresaIva: number,
  options?: { preservePrecioIva?: boolean; paisEmpresa?: unknown }
): void {
  (detalles || []).forEach((detalle: any) => {
    detalle.id = null;

    const precioConIvaGuardado = parseFloat(
      String(detalle?.precio_con_iva ?? detalle?.precio_iva ?? '')
    );
    const totalIvaGuardado = parseFloat(String(detalle?.total_iva ?? ''));
    const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
    const pct = porcentajeIvaDetalle(
      detalle,
      empresaIva,
      cobrarImpuestos,
      options?.paisEmpresa
    );

    if (Number.isFinite(precioConIvaGuardado) && String(detalle?.precio_con_iva ?? detalle?.precio_iva ?? '') !== '') {
      detalle.precio_iva = redondearMoneda(precioConIvaGuardado).toFixed(2);
      if (pct > 0) {
        detalle.precio = (precioConIvaGuardado / (1 + pct / 100)).toFixed(6);
      } else {
        detalle.precio = redondearMoneda(precioConIvaGuardado).toFixed(6);
      }
    } else if (
      Number.isFinite(totalIvaGuardado) &&
      String(detalle?.total_iva ?? '') !== '' &&
      cantidad > 0
    ) {
      const descuentoConIva = parseFloat(String(detalle?.descuento_con_iva ?? 0)) || 0;
      const precioConIva = (totalIvaGuardado + descuentoConIva) / cantidad;
      detalle.precio_iva = redondearMoneda(precioConIva).toFixed(2);
      if (pct > 0) {
        detalle.precio = (precioConIva / (1 + pct / 100)).toFixed(6);
      } else {
        detalle.precio = redondearMoneda(precioConIva).toFixed(6);
      }
    }

    const tipo = (detalle.tipo_gravado && String(detalle.tipo_gravado).toLowerCase()) || 'gravada';
    detalle.tipo_gravado = ['gravada', 'exenta', 'no_sujeta'].includes(tipo) ? tipo : 'gravada';

    calcularMontosLineaDetalle(detalle, cobrarImpuestos, empresaIva, {
      preservePrecioIva: options?.preservePrecioIva ?? !!detalle.precio_iva,
      paisEmpresa: options?.paisEmpresa,
    });
  });
}

/**
 * IVA objetivo de cabecera = suma(total_iva) − sub_total.
 * Así venta.iva queda alineada con los montos cobrados (precio_iva/total_iva).
 */
export function resolverIvaObjetivoEncabezadoVenta(
  detalles: any[],
  _cobrarImpuestos: boolean,
  _empresaIva: number,
  _paisEmpresa?: unknown,
  descuentoPuntos = 0
): number {
  return calcularIvaResidualEncabezadoVenta(detalles, descuentoPuntos);
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
 * Destino del centavo de cierre residual: preferir un IVA que ya tenga monto
 * (la tasa realmente usada en la factura). Si ninguno tiene, cae al IVA empresa.
 */
function elegirImpuestoIvaParaCierreResidual(
  ventaImpuestos: any[],
  empresaIva: number
): any | undefined {
  const ivas = (ventaImpuestos || []).filter((i: any) =>
    esImpuestoIva(i, empresaIva)
  );
  if (!ivas.length) {
    return undefined;
  }

  const conMonto = ivas
    .filter((i: any) => Math.abs(parseFloat(String(i.monto ?? 0)) || 0) >= 0.005)
    .sort(
      (a: any, b: any) =>
        Math.abs(parseFloat(String(b.monto ?? 0)) || 0) -
        Math.abs(parseFloat(String(a.monto ?? 0)) || 0)
    );
  if (conMonto.length) {
    return conMonto[0];
  }

  return (
    ivas.find((i: any) => pctIgual(Number(i.porcentaje), empresaIva)) || ivas[0]
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
  empresaIva: number,
  paisEmpresa?: unknown,
  descuentoPuntos = 0
): number {
  if (!ventaImpuestos?.length) {
    return cobrarImpuestos
      ? calcularIvaResidualEncabezadoVenta(detalles, descuentoPuntos)
      : 0;
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
    empresaIva,
    paisEmpresa,
    descuentoPuntos
  );
  const ivaAcumulado = montoIvaDeVentaImpuestos(ventaImpuestos, empresaIva);
  const delta = redondearMoneda(ivaObjetivo - ivaAcumulado);

  if (Math.abs(delta) >= 0.005) {
    const impuestoDestino = elegirImpuestoIvaParaCierreResidual(
      ventaImpuestos,
      empresaIva
    );
    if (impuestoDestino) {
      impuestoDestino.monto = redondearMoneda(
        (parseFloat(String(impuestoDestino.monto ?? 0)) || 0) + delta
      );
    }
  }

  return montoIvaDeVentaImpuestos(ventaImpuestos, empresaIva);
}
