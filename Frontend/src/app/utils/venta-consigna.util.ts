export function esVentaPorConsigna(venta: { consigna?: boolean; estado?: string } | null | undefined): boolean {
    return !!venta?.consigna || venta?.estado === 'Consigna';
}

export function sincronizarFlagConsignaVenta(venta: { consigna?: boolean; estado?: string } | null | undefined): void {
    if (venta && venta.estado === 'Consigna') {
        venta.consigna = true;
    }
}

export function aplicarEstadoConsignaEnVenta(
    venta: { consigna?: boolean; estado?: string; condicion?: string; credito?: boolean } | null | undefined
): void {
    if (!esVentaPorConsigna(venta)) {
        return;
    }
    venta!.estado = 'Consigna';
    venta!.credito = true;
    venta!.condicion = 'Crédito';
}
