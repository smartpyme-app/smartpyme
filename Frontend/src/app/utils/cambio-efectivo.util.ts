/**
 * Vuelto: efectivo recibido menos lo que se debe cobrar en efectivo.
 * En pago simple el monto a cobrar incluye la propina.
 * En pago mixto se usa la parte asignada a efectivo.
 */
export function calcularCambioEfectivo(params: {
  montoPago: unknown;
  total: unknown;
  propina?: unknown;
  formaPago?: string;
  efectivo?: unknown;
}): string {
  const raw = params.montoPago;
  if (raw === null || raw === undefined || raw === '') {
    return '';
  }
  const recibido = parseFloat(String(raw)) || 0;
  const totalVenta = parseFloat(String(params.total ?? 0)) || 0;
  const propina = parseFloat(String(params.propina ?? 0)) || 0;
  const totalACobrar = totalVenta + propina;
  const enMultiple = params.formaPago === 'Multiple';
  const parteEfectivo = parseFloat(String(params.efectivo ?? 0)) || 0;
  const aCobrarEfectivo = enMultiple && parteEfectivo > 0 ? parteEfectivo : totalACobrar;
  return (recibido - aCobrarEfectivo).toFixed(2);
}
