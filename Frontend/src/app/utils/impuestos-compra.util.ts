import { esEmpresaHonduras } from './impuestos-venta.util';

function tieneTasaExplicita(porcentajeImpuesto: unknown): boolean {
  return porcentajeImpuesto != null && porcentajeImpuesto !== '';
}

function esCeroExplicito(porcentajeImpuesto: unknown): boolean {
  return tieneTasaExplicita(porcentajeImpuesto) && Number(porcentajeImpuesto) === 0;
}

/**
 * % IVA de una línea de compra.
 * - Sin "Con IVA" → 0
 * - Producto sin tasa (null/''/0) → IVA de la empresa
 * - HN con 0 explícito → exento
 */
export function resolverPorcentajeImpuestoCompra(
  porcentajeImpuesto: unknown,
  ivaEmpresa: unknown,
  cobrarImpuestos: boolean,
  paisEmpresa?: unknown
): number {
  if (!cobrarImpuestos) {
    return 0;
  }
  if (esEmpresaHonduras(paisEmpresa) && esCeroExplicito(porcentajeImpuesto)) {
    return 0;
  }
  if (tieneTasaExplicita(porcentajeImpuesto)) {
    const pct = Number(porcentajeImpuesto);
    if (Number.isFinite(pct) && pct > 0) {
      return pct;
    }
  }
  return Number(ivaEmpresa ?? 0) || 0;
}

/** Tasa a copiar del producto a la línea (el checkbox Con IVA se aplica al calcular). */
export function snapshotPorcentajeImpuestoProducto(
  porcentajeImpuesto: unknown,
  ivaEmpresa: unknown,
  paisEmpresa?: unknown
): number {
  return resolverPorcentajeImpuestoCompra(porcentajeImpuesto, ivaEmpresa, true, paisEmpresa);
}

/** En el formulario de producto: preseleccionar IVA de empresa si no hay tasa. */
export function debeUsarIvaEmpresaProducto(
  porcentajeImpuesto: unknown,
  paisEmpresa?: unknown
): boolean {
  if (esEmpresaHonduras(paisEmpresa) && esCeroExplicito(porcentajeImpuesto)) {
    return false;
  }
  if (!tieneTasaExplicita(porcentajeImpuesto)) {
    return true;
  }
  const pct = Number(porcentajeImpuesto);
  return !Number.isFinite(pct) || pct <= 0;
}

function pctIgual(a: number, b: number): boolean {
  return Math.abs(Number(a) - Number(b)) < 0.01;
}

/**
 * Recalcula IVA por línea y montos de cabecera.
 * Primero las líneas (evita usar un iva stale en 0) y después acumula por tasa.
 */
export function aplicarIvaCompra(
  compra: any,
  ivaEmpresa: unknown,
  paisEmpresa?: unknown
): void {
  if (!compra.detalles || !Array.isArray(compra.detalles)) {
    compra.detalles = [];
  }
  if (!compra.impuestos || !Array.isArray(compra.impuestos)) {
    compra.impuestos = [];
  }

  const cobrar = !!compra.cobrar_impuestos;
  const empresaIva = Number(ivaEmpresa ?? 0) || 0;

  for (const detalle of compra.detalles) {
    const totalLinea = parseFloat(detalle.total || 0) || 0;
    const pct = resolverPorcentajeImpuestoCompra(
      detalle.porcentaje_impuesto,
      empresaIva,
      cobrar,
      paisEmpresa
    );
    if (cobrar) {
      detalle.porcentaje_impuesto = pct;
      detalle.iva = totalLinea > 0 ? parseFloat((totalLinea * (pct / 100)).toFixed(4)) : 0;
    } else {
      detalle.iva = 0;
    }
  }

  const porcentajesCatalogo = compra.impuestos.map((impuesto: any) => Number(impuesto.porcentaje));

  compra.impuestos.forEach((impuesto: any) => {
    if (!cobrar) {
      impuesto.monto = 0;
      return;
    }
    const pctImp = Number(impuesto.porcentaje);
    const monto = compra.detalles
      .filter((detalle: any) => pctIgual(pctImp, Number(detalle.porcentaje_impuesto)))
      .reduce((sum: number, detalle: any) => sum + (parseFloat(detalle.iva) || 0), 0);
    impuesto.monto = parseFloat(Number(monto).toFixed(4));
  });

  if (cobrar && compra.detalles.length && compra.impuestos.length) {
    const ivaSinAsignar = compra.detalles
      .filter(
        (detalle: any) =>
          !porcentajesCatalogo.some((pct: number) => pctIgual(pct, Number(detalle.porcentaje_impuesto)))
      )
      .reduce((sum: number, detalle: any) => sum + (parseFloat(detalle.iva) || 0), 0);
    if (ivaSinAsignar > 0) {
      const impuestoDestino =
        compra.impuestos.find((impuesto: any) => pctIgual(Number(impuesto.porcentaje), empresaIva)) ||
        compra.impuestos[0];
      impuestoDestino.monto = parseFloat(
        (parseFloat(impuestoDestino.monto) + ivaSinAsignar).toFixed(4)
      );
    }
  }

  const ivaCatalogo = compra.impuestos.reduce(
    (sum: number, impuesto: any) => sum + (parseFloat(impuesto.monto) || 0),
    0
  );
  if (cobrar && compra.impuestos.length === 0) {
    const ivaLineas = compra.detalles.reduce(
      (sum: number, detalle: any) => sum + (parseFloat(detalle.iva) || 0),
      0
    );
    compra.iva = parseFloat(ivaLineas.toFixed(2)).toFixed(2);
    return;
  }
  compra.iva = parseFloat(ivaCatalogo.toFixed(2)).toFixed(2);
}
