/**
 * Costo unitario y total de línea a partir de cuerpoDocumento (MH / importación DTE).
 */
export function costoUnitarioDesdeLineaDte(item: {
  precioUni?: number | string | null;
  cantidad?: number | string | null;
  ventaGravada?: number | string | null;
  ventaExenta?: number | string | null;
  ventaNoSuj?: number | string | null;
}): number {
  const cantidad = parseFloat(String(item.cantidad ?? 0)) || 0;
  const precioUni = parseFloat(String(item.precioUni ?? 0)) || 0;
  if (precioUni > 0) {
    return precioUni;
  }

  const subtotalLinea =
    (parseFloat(String(item.ventaGravada ?? 0)) || 0) +
    (parseFloat(String(item.ventaExenta ?? 0)) || 0) +
    (parseFloat(String(item.ventaNoSuj ?? 0)) || 0);

  if (cantidad > 0 && subtotalLinea > 0) {
    return subtotalLinea / cantidad;
  }

  return 0;
}

export function descuentoDesdeLineaDte(item: {
  montoDescu?: number | string | null;
  descuento?: number | string | null;
  cantidad?: number | string | null;
  precioUni?: number | string | null;
  ventaGravada?: number | string | null;
  ventaExenta?: number | string | null;
  ventaNoSuj?: number | string | null;
}): number {
  const explicito =
    parseFloat(String(item.montoDescu ?? item.descuento ?? 0)) || 0;
  if (explicito > 0) {
    return explicito;
  }

  const cantidad = parseFloat(String(item.cantidad ?? 0)) || 0;
  const precioUni = parseFloat(String(item.precioUni ?? 0)) || 0;
  const neto =
    (parseFloat(String(item.ventaGravada ?? 0)) || 0) +
    (parseFloat(String(item.ventaExenta ?? 0)) || 0) +
    (parseFloat(String(item.ventaNoSuj ?? 0)) || 0);

  if (cantidad > 0 && precioUni > 0 && neto > 0) {
    const bruto = cantidad * precioUni;
    if (bruto > neto + 0.00001) {
      return bruto - neto;
    }
  }

  return 0;
}

function round2(n: number): number {
  return parseFloat((Number.isFinite(n) ? n : 0).toFixed(2));
}

/**
 * Una línea guardada en modo varios ítems. El gasto de un solo monto queda con
 * cantidad 1 y precio = subtotal; eso no es desglose.
 */
export function lineaGastoTieneDesglose(d: {
  cantidad?: number | string | null;
  precio_unitario?: number | string | null;
  sub_total?: number | string | null;
} | null | undefined): boolean {
  const cant = parseFloat(String(d?.cantidad ?? 0)) || 0;
  const precio = parseFloat(String(d?.precio_unitario ?? 0)) || 0;
  const sub = parseFloat(String(d?.sub_total ?? 0)) || 0;
  if (Math.abs(cant - 1) > 0.0001) {
    return true;
  }
  return Math.abs(cant * precio - sub) > 0.009;
}

/** Hueco entre cantidad × precio y el subtotal neto. 0 si el subtotal ya es el bruto. */
export function descuentoImplicitoLinea(
  cantidad: number | string | null | undefined,
  precio: number | string | null | undefined,
  subTotal: number | string | null | undefined
): number {
  const cant = parseFloat(String(cantidad ?? 0)) || 0;
  const precioN = parseFloat(String(precio ?? 0)) || 0;
  const sub = parseFloat(String(subTotal ?? 0)) || 0;
  return round2(Math.max(0, cant * precioN - sub));
}

/**
 * Descuento de una línea de El Salvador que el formulario puede volver a calcular
 * (subtotal = cantidad × precio − descuento) sin mover la base ya importada.
 * Crédito fiscal: el precio viene sin IVA y la base es neta. Factura: el precio
 * suele incluir IVA, así que solo entra si montoDescu cae justo sobre esa base.
 */
function descuentoElSalvador(
  cantidad: number,
  precio: number,
  montoDescu: number | string | null | undefined,
  base: number,
  tipoDte: string | undefined
): number {
  const descXml = round2(parseFloat(String(montoDescu ?? 0)) || 0);
  const gap = descuentoImplicitoLinea(cantidad, precio, base);
  if (tipoDte === '03') {
    if (descXml > 0 && Math.abs(gap - descXml) <= 0.02) {
      return gap;
    }
    if (descXml <= 0 && gap > 0) {
      return gap;
    }
    return 0;
  }
  if (descXml > 0 && Math.abs(cantidad * precio - descXml - base) <= 0.02) {
    return gap > 0 ? gap : descXml;
  }
  return 0;
}

/**
 * Montos de una línea de gasto importada.
 * Costa Rica: la base es el neto después del descuento y el impuesto se suma encima.
 * El Salvador conserva el total del DTE y solo rellena el descuento cuando cuadra.
 */
export function montosLineaGastoDesdeDte(
  item: {
    cantidad?: number | string | null;
    precioUni?: number | string | null;
    ventaGravada?: number | string | null;
    ventaExenta?: number | string | null;
    ventaNoSuj?: number | string | null;
    montoDescu?: number | string | null;
    compra?: number | string | null;
  },
  opts: { ivaRate: number; precioSinImpuesto: boolean; tipoDte?: string }
): {
  cantidad: number;
  precio_unitario: number;
  descuento: number;
  sub_total: number;
  iva: number;
  total: number;
  tipo_gravado: 'gravada' | 'exenta' | 'no_sujeta';
} {
  const cant = parseFloat(String(item.cantidad ?? '')) || 1;
  const precio = parseFloat(String(item.precioUni ?? '')) || 0;
  const gravada = parseFloat(String(item.ventaGravada ?? '')) || 0;
  const exenta = parseFloat(String(item.ventaExenta ?? '')) || 0;
  const noSuj = parseFloat(String(item.ventaNoSuj ?? '')) || 0;
  const compra = parseFloat(String(item.compra ?? '')) || 0;
  const rate = opts.ivaRate > 0 ? opts.ivaRate : 0;

  if (opts.precioSinImpuesto) {
    const neto = gravada + exenta + noSuj;
    const descXml = parseFloat(String(item.montoDescu ?? 0)) || 0;
    const sub =
      neto > 0 ? round2(neto) : round2(Math.max(0, cant * precio - descXml));
    const descuento = descuentoImplicitoLinea(cant, precio, sub);
    const esExenta = exenta > 0 && gravada <= 0;
    const esNoSuj = !esExenta && noSuj > 0 && gravada <= 0;
    const esGravada = !esExenta && !esNoSuj;
    const iva = esGravada ? round2(sub * rate) : 0;
    return {
      cantidad: cant,
      precio_unitario: precio,
      descuento,
      sub_total: sub,
      iva,
      total: round2(sub + iva),
      tipo_gravado: esGravada ? 'gravada' : esExenta ? 'exenta' : 'no_sujeta',
    };
  }

  const montoItem = gravada || compra || cant * precio;
  let sub = 0;
  let iva = 0;
  let total = 0;
  if (opts.tipoDte === '03') {
    sub = montoItem;
    iva = sub * rate;
    total = sub + iva;
  } else {
    total = montoItem;
    sub = rate > 0 ? total / (1 + rate) : total;
    iva = total - sub;
  }
  const subTotal = round2(sub);
  return {
    cantidad: cant,
    precio_unitario: precio,
    descuento: descuentoElSalvador(cant, precio, item.montoDescu, subTotal, opts.tipoDte),
    sub_total: subTotal,
    iva: round2(iva),
    total: round2(total),
    tipo_gravado: iva > 0 ? 'gravada' : 'no_sujeta',
  };
}

export function totalLineaDesdeDte(item: {
  precioUni?: number | string | null;
  cantidad?: number | string | null;
  ventaGravada?: number | string | null;
  ventaExenta?: number | string | null;
  ventaNoSuj?: number | string | null;
  montoDescu?: number | string | null;
}): number {
  const cantidad = parseFloat(String(item.cantidad ?? 0)) || 0;
  const descuento = parseFloat(String(item.montoDescu ?? 0)) || 0;
  const subtotalLinea =
    (parseFloat(String(item.ventaGravada ?? 0)) || 0) +
    (parseFloat(String(item.ventaExenta ?? 0)) || 0) +
    (parseFloat(String(item.ventaNoSuj ?? 0)) || 0);

  if (subtotalLinea > 0) {
    return subtotalLinea;
  }

  const costo = costoUnitarioDesdeLineaDte(item);
  return Math.max(0, cantidad * costo - descuento);
}

export interface LotePendienteDte {
  numero_lote: string;
  cantidad: number;
  fecha_fabricacion: string | null;
  fecha_vencimiento: string | null;
}

const LOTE_EN_DESCRIPCION =
  /^(.*?)\s*\|\s*Lote:\s*(.+?)\s+Cantidad Lote:\s*([0-9]+(?:[.,][0-9]+)?)(?:\s*\|\s*Fecha fabricación:\s*(\d{4}-\d{2}-\d{2}))?(?:\s*\|\s*Fecha Caducidad:\s*(\d{4}-\d{2}-\d{2}))?\s*$/iu;

/** Lote que algunos emisores (Mercantil Farmacéutica) meten en la descripción de la línea. */
export function lotePendienteDesdeDescripcion(
  descripcion: string | null | undefined
): LotePendienteDte | null {
  const texto = String(descripcion ?? '').trim();
  const match = texto.match(LOTE_EN_DESCRIPCION);
  if (!match) {
    return null;
  }
  const numero = match[2].trim();
  if (!numero) {
    return null;
  }
  return {
    numero_lote: numero,
    cantidad: parseFloat(match[3].replace(',', '.')) || 0,
    fecha_fabricacion: match[4] || null,
    fecha_vencimiento: match[5] || null,
  };
}

export function descripcionProductoSinLote(descripcion: string | null | undefined): string {
  const lote = lotePendienteDesdeDescripcion(descripcion);
  if (!lote) {
    return String(descripcion ?? '').trim();
  }
  const texto = String(descripcion ?? '').trim();
  const corte = texto.toLowerCase().indexOf('| lote:');
  return corte > 0 ? texto.slice(0, corte).trim() : texto;
}
