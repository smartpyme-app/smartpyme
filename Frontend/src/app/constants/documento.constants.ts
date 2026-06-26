export const FACTURA_REMISION = 'Factura de remisión';

export const TIPOS_DOCUMENTO_COMPRA_SIN_IVA_FISCAL = [FACTURA_REMISION] as const;

export function esDocumentoCompraSinIvaFiscal(tipo: string | null | undefined): boolean {
    return TIPOS_DOCUMENTO_COMPRA_SIN_IVA_FISCAL.includes(tipo as typeof FACTURA_REMISION);
}

export function esVentaConsignaRemision(
    venta: { consigna?: boolean; estado?: string } | null | undefined
): boolean {
    return !!venta?.consigna || venta?.estado === 'Consigna';
}
