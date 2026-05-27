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
