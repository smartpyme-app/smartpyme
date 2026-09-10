export const SLUG_PRESTAMOS_EMPRESA = 'prestamos-empresa';

export function puedeVerMenuPrestamos(
  contabilidadHabilitada: boolean,
  prestamosHabilitada: boolean,
  tienePermisoVer: boolean,
): boolean {
  return contabilidadHabilitada && prestamosHabilitada && tienePermisoVer;
}
