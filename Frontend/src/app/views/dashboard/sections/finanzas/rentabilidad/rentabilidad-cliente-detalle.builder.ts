import {
  RentabilidadClienteDetalle,
  RentabilidadClienteFila,
} from './rentabilidad.model';

const CERO = { mercaderia: 0, esperarPago: 0, leQueda: 0 };

const VACIO: RentabilidadClienteDetalle = {
  obra: '',
  deCada100Promedio: 0,
  alFinalQuedan: 0,
  descuentoPct: 0,
  descuentoMonto: 0,
  cxcVencido: 0,
  comparacionEste: CERO,
  comparacionTodos: CERO,
  puente: [],
  facturas: [],
  productos: [],
  sucursales: [],
};

export function detalleClienteDesdeFila(
  fila: RentabilidadClienteFila,
): RentabilidadClienteDetalle {
  return fila.detalle ?? VACIO;
}
