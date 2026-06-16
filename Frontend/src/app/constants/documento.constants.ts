export const FACTURA_REMISION = 'Factura de remisión';

export const TIPOS_DOCUMENTO_COMPRA_SIN_IVA_FISCAL = [FACTURA_REMISION] as const;

export function esDocumentoCompraSinIvaFiscal(tipo: string | null | undefined): boolean {
    return TIPOS_DOCUMENTO_COMPRA_SIN_IVA_FISCAL.includes(tipo as typeof FACTURA_REMISION);
}
