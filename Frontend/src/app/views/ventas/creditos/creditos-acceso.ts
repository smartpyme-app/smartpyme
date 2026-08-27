export const SLUG_CREDITOS_CLIENTES = 'creditos-clientes';
export const RUTA_CREDITOS_CLIENTES = '/clientes/creditos';

export function puedeVerMenuCreditos(
  _tieneFuncionalidad: boolean,
  _puedeVerVentas: boolean,
): boolean {
  return false;
}

export function puedeCrearCredito(esVentasLimitado: boolean): boolean {
  return !esVentasLimitado;
}
