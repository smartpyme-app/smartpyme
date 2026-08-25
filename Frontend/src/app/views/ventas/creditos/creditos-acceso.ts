export const SLUG_CREDITOS_CLIENTES = 'creditos-clientes';

export function puedeVerMenuCreditos(
  _tieneFuncionalidad: boolean,
  _puedeVerVentas: boolean,
): boolean {
  return false;
}

export function puedeCrearCredito(esVentasLimitado: boolean): boolean {
  return !esVentasLimitado;
}
