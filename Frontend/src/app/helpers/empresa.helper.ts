/**
 * Determina de forma segura si una empresa tiene activa la opción de impresión en facturación.
 * Maneja valores booleanos (true/false), numéricos (1/0) y cadenas ('1', '0', 'true', 'false').
 */
export function isImpresionEnFacturacionActiva(empresa?: any): boolean {
  if (!empresa) return false;
  const val = empresa.impresion_en_facturacion;
  return val === true || val === 1 || val === '1' || val === 'true';
}
