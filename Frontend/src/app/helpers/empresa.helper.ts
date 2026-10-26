/**
 * Determina de forma segura si una empresa tiene activa la opción de impresión en facturación.
 * Maneja valores booleanos (true/false), numéricos (1/0) y cadenas ('1', '0', 'true', 'false').
 */
export function isImpresionEnFacturacionActiva(empresa?: any): boolean {
  if (!empresa) return false;
  return isFlagActivo(empresa.impresion_en_facturacion);
}

export function isFacturacionElectronicaActiva(empresa?: any): boolean {
  if (!empresa) return false;
  return isFlagActivo(empresa.facturacion_electronica);
}

/** Documento Ticket: no se emite comprobante electrónico a Hacienda/MH. */
export function esVentaTicketSinEmisionFe(nombreDocumento?: string | null): boolean {
  return String(nombreDocumento ?? '').trim() === 'Ticket';
}

export function ventaEsTicketSinEmisionFe(venta?: {
  nombre_documento?: string | null;
  tipo_documento?: string | null;
} | null): boolean {
  return (
    esVentaTicketSinEmisionFe(venta?.nombre_documento) ||
    esVentaTicketSinEmisionFe(venta?.tipo_documento)
  );
}

/** Imprime y, si hay FE, emite DTE. Sin el checkbox de impresión no se emite DTE. */
export function debeEmitirDteAlFacturar(empresa?: any, nombreDocumento?: string | null): boolean {
  if (esVentaTicketSinEmisionFe(nombreDocumento)) {
    return false;
  }
  return isImpresionEnFacturacionActiva(empresa) && isFacturacionElectronicaActiva(empresa);
}

export function debeImprimirTrasFacturar(empresa?: any, debeImprimirVenta?: boolean): boolean {
  return isImpresionEnFacturacionActiva(empresa) && !!debeImprimirVenta;
}

function isFlagActivo(val: unknown): boolean {
  return val === true || val === 1 || val === '1' || val === 'true';
}
