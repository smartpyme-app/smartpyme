import {
  RentabilidadProveedorDetalle,
  RentabilidadProveedorFila,
} from './rentabilidad.model';

const VACIO: RentabilidadProveedorDetalle = {
  ganaPorDolar6m: 0,
  costoLamina: 0,
  costoLaminaVs6mPct: 0,
  precioVentaVs6mPct: 0,
  diasPagoPromedioOtros: 0,
  otraCotizacionMonto: 0,
  otraCotizacionFuente: '',
  otraCotizacionVsPct: 0,
  resumen: [],
  chartLabels: [],
  chartCompras: [],
  chartVentas: [],
  chartMargen: [],
  productoNombre: '',
  productoCompras: 0,
  productoCostoHoy: 0,
  productoCosto6m: 0,
  productoMargenPct: 0,
  porPagar: [],
  clientes: [],
};

export function detalleProveedorDesdeFila(
  fila: RentabilidadProveedorFila,
): RentabilidadProveedorDetalle {
  return fila.detalle ?? VACIO;
}
