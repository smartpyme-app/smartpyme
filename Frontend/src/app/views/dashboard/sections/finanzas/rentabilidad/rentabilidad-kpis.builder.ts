import { detalleProyectoDesdeFila } from './rentabilidad-proyecto-detalle.builder';
import { kpisDesdeResumen } from './rentabilidad-kpis.resumen';
import {
  RentabilidadClienteFila,
  RentabilidadHorizonteId,
  RentabilidadKpi,
  RentabilidadKpiTono,
  RentabilidadProductoTablaFila,
  RentabilidadProveedorFila,
  RentabilidadProyectoFila,
} from './rentabilidad.model';

export { kpisDesdeResumen };

export interface RentabilidadKpiContexto {
  horizonte: RentabilidadHorizonteId;
  formatMonto: (value: number) => string;
  formatPrecio: (value: number) => string;
  formatPct: (value: number, digits?: number) => string;
  textoInventario: (fila: RentabilidadProductoTablaFila) => string;
}

/** El API ya agrega mes, trimestre o año. No multiplicar otra vez. */
export function factorHorizonte(_horizonte: RentabilidadHorizonteId): number {
  return 1;
}

function etiquetaHorizonte(horizonte: RentabilidadHorizonteId): string {
  if (horizonte === 'trimestre') {
    return 'trim.';
  }
  if (horizonte === 'anio') {
    return 'año';
  }
  return 'mes';
}

function escala(monto: number, horizonte: RentabilidadHorizonteId): number {
  return Math.round(monto * factorHorizonte(horizonte));
}

function tonoMargen(pct: number): RentabilidadKpiTono {
  if (pct < 0) {
    return 'rojo';
  }
  if (pct < 15) {
    return 'naranja';
  }
  return 'azul';
}

function tonoProveedorValor(
  tono: RentabilidadProveedorFila['ganaPorDolarTono'],
): RentabilidadKpiTono {
  if (tono === 'rojo' || tono === 'naranja') {
    return tono;
  }
  return 'azul';
}

export function kpisDesdeProducto(
  fila: RentabilidadProductoTablaFila,
  ctx: RentabilidadKpiContexto,
): RentabilidadKpi[] {
  const h = ctx.horizonte;
  const suf = etiquetaHorizonte(h);
  const ventas = escala(fila.ventas, h);
  const utilidad = escala(fila.utilidad, h);
  const margen = fila.margenPct ?? 0;

  return [
    {
      titulo: `Ventas — ${suf}`,
      valor: ctx.formatMonto(ventas),
      tono: 'azul',
      linea: fila.categoria,
      sublinea: `n.º ${fila.puesto} en ventas`,
    },
    {
      titulo: 'Margen',
      valor: fila.margenPct === null ? '—' : ctx.formatPct(margen),
      tono: fila.margenBajo ? 'rojo' : tonoMargen(margen),
      linea: ctx.formatMonto(utilidad),
    },
    {
      titulo: `Unidades — ${suf}`,
      valor: String(Math.round(fila.unidades * factorHorizonte(h))),
      tono: 'azul',
      sublinea:
        fila.ventasVsPct === null
          ? undefined
          : `${fila.ventasVsPct >= 0 ? '+' : ''}${fila.ventasVsPct}% vs. año`,
    },
    {
      titulo: 'Inventario',
      valor: ctx.textoInventario(fila),
      tono: fila.inventario.tipo === 'sinVenta' ? 'naranja' : 'azul',
    },
    {
      titulo: 'Costo vs. hace 6 meses',
      valor:
        fila.costoVsPct === null
          ? '—'
          : `${fila.costoVsPct > 0 ? '+' : ''}${fila.costoVsPct?.toFixed(1)}%`,
      tono: fila.costoAlerta ? 'rojo' : 'azul',
      sublinea:
        fila.precioVsPct === null
          ? undefined
          : `precio ${fila.precioVsPct >= 0 ? '+' : ''}${fila.precioVsPct}%`,
    },
  ];
}

export function kpisDesdeCliente(
  fila: RentabilidadClienteFila,
  ctx: RentabilidadKpiContexto,
): RentabilidadKpi[] {
  const h = ctx.horizonte;
  const suf = etiquetaHorizonte(h);

  return [
    {
      titulo: `Total ventas — ${suf}`,
      valor: ctx.formatMonto(escala(fila.compro, h)),
      tono: 'azul',
      sublinea: `${fila.parteVentasPct}% de sus ventas`,
    },
    {
      titulo: `Ganancia — ${suf}`,
      valor: ctx.formatMonto(escala(fila.ganancia, h)),
      tono: fila.deCada100Bajo ? 'naranja' : 'azul',
    },
    {
      titulo: 'De cada $100',
      valor: ctx.formatPrecio(fila.deCada100),
      tono: fila.deCada100Bajo ? 'rojo' : 'azul',
      sublinea: `al final quedan ${ctx.formatPrecio(fila.alFinalQuedan)}`,
    },
    {
      titulo: 'Le debe',
      valor: fila.leDebe === null ? 'contado' : ctx.formatMonto(escala(fila.leDebe, h)),
      tono: fila.pagoLento ? 'naranja' : 'azul',
      sublinea: fila.pagoContado
        ? 'paga al contado'
        : `${fila.pagoDias} días (pactó ${fila.pagoPactado})`,
    },
    {
      titulo: 'Última compra',
      valor: fila.ultimaCompra,
      tono: fila.ultimaCompraAlerta ? 'naranja' : 'azul',
    },
  ];
}

export function kpisDesdeProveedor(
  fila: RentabilidadProveedorFila,
  ctx: RentabilidadKpiContexto,
): RentabilidadKpi[] {
  const h = ctx.horizonte;
  const suf = etiquetaHorizonte(h);

  return [
    {
      titulo: `Le compró — ${suf}`,
      valor: ctx.formatMonto(escala(fila.compro, h)),
      tono: fila.dependenciaAlta ? 'naranja' : 'azul',
      sublinea: `${fila.parteComprasPct}% de sus compras`,
    },
    {
      titulo: 'Gana por $1 comprado',
      valor: ctx.formatPrecio(fila.ganaPorDolar),
      tono: tonoProveedorValor(fila.ganaPorDolarTono),
      sublinea: fila.detalle
        ? `hace 6 meses ${ctx.formatPrecio(fila.detalle.ganaPorDolar6m)}`
        : undefined,
    },
    {
      titulo: `Ganó al vender — ${suf}`,
      valor: ctx.formatMonto(escala(fila.ganoAlVender, h)),
      tono: 'azul',
    },
    {
      titulo: 'Su precio en 6 meses',
      valor: `${fila.precio6mPct > 0 ? '+' : ''}${fila.precio6mPct.toFixed(1)}%`,
      tono: tonoProveedorValor(fila.precio6mTono),
    },
    {
      titulo: 'Días para pagarle',
      valor: String(fila.diasPago),
      tono: tonoProveedorValor(fila.diasPagoTono),
    },
  ];
}

export function kpisDesdeProyecto(
  fila: RentabilidadProyectoFila,
  ctx: RentabilidadKpiContexto,
): RentabilidadKpi[] {
  const det = detalleProyectoDesdeFila(fila);
  const h = ctx.horizonte;
  const facturado = escala(det.facturado, h);
  const porCobrar = escala(fila.porCobrar, h);

  return [
    {
      titulo: 'Contrato',
      valor: ctx.formatMonto(fila.contrato),
      tono: 'azul',
      sublinea: `por facturar ${ctx.formatMonto(escala(det.porFacturar, h))}`,
    },
    {
      titulo: 'Facturado',
      valor: ctx.formatMonto(facturado),
      tono: 'azul',
      sublinea: `${fila.facturadoPct}% del contrato`,
    },
    {
      titulo: 'Margen a la fecha',
      valor: ctx.formatPct(fila.margenFechaPct),
      tono: fila.margenFechaBajo ? 'rojo' : 'azul',
      sublinea: `planeaba ${ctx.formatPct(fila.margenPlaneadoPct)}`,
    },
    {
      titulo: 'Por cobrar',
      valor: ctx.formatMonto(porCobrar),
      tono: porCobrar > 2000 ? 'naranja' : 'azul',
    },
    {
      titulo: 'Su dinero en la obra',
      valor:
        fila.dineroEnObra === null ? '—' : ctx.formatMonto(escala(fila.dineroEnObra, h)),
      tono: fila.dineroEnObraAlerta ? 'rojo' : 'azul',
      sublinea: `pagó ${ctx.formatMonto(escala(det.pagadoObra, h))}`,
    },
  ];
}
