export const SLUG_CREDITOS_CLIENTES = 'creditos-clientes';

export function puedeVerMenuCreditos(
  tieneFuncionalidad: boolean,
  puedeVerVentas: boolean,
): boolean {
  return tieneFuncionalidad && puedeVerVentas;
}

export function puedeCrearCredito(esVentasLimitado: boolean): boolean {
  return !esVentasLimitado;
}
