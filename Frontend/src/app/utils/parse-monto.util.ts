/**
 * Parsea el texto de un input de monto (dólares, no centavos).
 * Acepta punto o coma decimal; no reinterpreta "25.00" como 0.25.
 */
export function parseMontoInput(raw: unknown): number | null {
  if (raw === null || raw === undefined) {
    return null;
  }
  if (typeof raw === 'number') {
    return Number.isFinite(raw) ? raw : null;
  }
  const s = String(raw).trim();
  if (s === '' || s === '.' || s === ',' || s === '-' || s === '-.' || s === '-,') {
    return null;
  }
  const normalized = s.replace(',', '.');
  if (!/^-?\d*\.?\d*$/.test(normalized)) {
    return null;
  }
  const n = parseFloat(normalized);
  return Number.isFinite(n) ? n : null;
}
