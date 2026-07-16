/**
 * Impuestos especiales de turismo (5%) distintos del IVA (codigo MH 20).
 */
export function filtrarImpuestosTurismo(impuestos: any[] | null | undefined): any[] {
  return (impuestos || []).filter(
    (impuesto: any) =>
      Number(impuesto?.porcentaje) === 5 &&
      String(impuesto?.codigo_mh ?? '') !== '20'
  );
}

export function empresaTieneImpuestoTurismo(
  impuestos: any[] | null | undefined
): boolean {
  return filtrarImpuestosTurismo(impuestos).length > 0;
}
