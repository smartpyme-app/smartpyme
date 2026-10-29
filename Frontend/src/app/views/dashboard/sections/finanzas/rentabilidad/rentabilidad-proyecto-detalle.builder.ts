import {
  RentabilidadProyectoDetalle,
  RentabilidadProyectoFila,
} from './rentabilidad.model';

const VACIO: RentabilidadProyectoDetalle = {
  porFacturar: 0,
  facturado: 0,
  cobrado: 0,
  gastado: 0,
  pagadoObra: 0,
  utilidadFecha: 0,
  contratoCobradoPct: 0,
  avances: [],
  resultado: [],
  costosCategoria: [],
  presupuesto: [],
  presupuestoTotal: { presupuesto: 0, gastado: 0, disponible: 0, usadoPct: 0 },
  materiales: [],
  chartLabels: [],
  chartGastado: [],
  chartFacturado: [],
  chartCobrado: [],
};

export function detalleProyectoDesdeFila(
  fila: RentabilidadProyectoFila,
): RentabilidadProyectoDetalle {
  return fila.detalle ?? VACIO;
}
