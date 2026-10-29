import {
  RentabilidadProductoDetalle,
  RentabilidadProductoTablaFila,
} from './rentabilidad.model';

const VACIO: RentabilidadProductoDetalle = {
  proveedor: '',
  utilidadAnio: 0,
  costoUnitario: 0,
  costoUnitarioVsPct: 0,
  precioUnitario: 0,
  precioUnitarioVsPct: 0,
  inventarioValor: 0,
  precioCostoLabels: [],
  precioSerie: [],
  costoSerie: [],
  sucursales: [],
  clientes: [],
  vendidoConDescuentoPct: 0,
  vendidoConDescuentoMonto: 0,
};

export function detalleProductoDesdeFila(
  fila: RentabilidadProductoTablaFila,
): RentabilidadProductoDetalle {
  return fila.detalle ?? VACIO;
}
