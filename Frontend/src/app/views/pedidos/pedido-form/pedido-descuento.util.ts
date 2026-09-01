/** Descuento de línea de pedido: $ = total de línea; % = de cantidad * precio (sin IVA). */

export function descuentoLineaPedido(
  cantidad: number,
  precio: number,
  isMonto: boolean,
  valor: number
): { descuento: number; descuento_porcentaje: number } {
  const v = Number(valor) || 0;
  if (isMonto) {
    return { descuento: v, descuento_porcentaje: 0 };
  }
  const qty = Number(cantidad) || 0;
  const precioN = Number(precio) || 0;
  return {
    descuento: Number((qty * precioN * (v / 100)).toFixed(4)),
    descuento_porcentaje: v,
  };
}

export function esDescuentoMontoPedido(descuentoPorcentaje: number | null | undefined): boolean {
  return !(Number(descuentoPorcentaje) > 0);
}

export function camposDescuentoFacturaDesdePedido(opts: {
  descuento: number;
  descuento_porcentaje?: number;
  descuento_is_monto?: boolean;
  cantidad: number;
  ivaPct?: number;
  montoConIva?: boolean;
}): {
  descuento: number;
  descuento_porcentaje: number;
  descuento_is_monto: boolean;
  descuento_monto: number;
} {
  const desc = Number(opts.descuento) || 0;
  const pct = Number(opts.descuento_porcentaje) || 0;
  const isMonto = opts.descuento_is_monto != null ? !!opts.descuento_is_monto : esDescuentoMontoPedido(pct);

  if (!isMonto && pct > 0) {
    return {
      descuento: desc,
      descuento_porcentaje: pct,
      descuento_is_monto: false,
      descuento_monto: 0,
    };
  }

  const cant = Number(opts.cantidad) || 0;
  const iva = Number(opts.ivaPct) || 0;
  const factor = opts.montoConIva && iva > 0 ? 1 + iva / 100 : 1;
  const perUnit = cant > 0 ? desc / cant : 0;

  return {
    descuento: desc,
    descuento_porcentaje: 0,
    descuento_is_monto: true,
    descuento_monto: Number((perUnit * factor).toFixed(4)),
  };
}
