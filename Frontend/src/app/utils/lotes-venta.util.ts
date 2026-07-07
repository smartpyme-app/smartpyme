export function factorConversionDetalle(detalle: any): number {
    return parseFloat(String(detalle?.factor_conversion ?? 1)) || 1;
}

export function totalAsignadoUnidadesLotes(lotes: any[]): number {
    return lotes.reduce((sum, lote) => {
        return sum + (parseFloat(String(lote.cantidad_asignada ?? 0)) || 0);
    }, 0);
}

export function asignacionLotesExcedeStock(lotes: any[]): boolean {
    return lotes.some((lote) => {
        const asignada = parseFloat(String(lote.cantidad_asignada ?? 0)) || 0;
        const stock = parseFloat(String(lote.stock_unidades ?? lote.stock ?? 0)) || 0;
        return asignada > stock + 0.0001;
    });
}

export function cantidadBaseDesdeDetalle(detalle: any): number {
    const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
    return cantidad * factorConversionDetalle(detalle);
}

export function stockBaseAUnidadesDetalle(stockBase: number | string, detalle: any): number {
    const stock = parseFloat(String(stockBase)) || 0;
    const factor = factorConversionDetalle(detalle);
    return factor > 0 ? stock / factor : stock;
}

export function autoDistribuirCantidadesLotes(
    lotes: any[],
    cantidadBaseRequerida: number,
    detalle: any
): void {
    let pendiente = cantidadBaseRequerida;
    const factor = factorConversionDetalle(detalle);

    for (const lote of lotes) {
        lote.cantidad_asignada = 0;
    }

    for (const lote of lotes) {
        if (pendiente <= 0.0001) {
            break;
        }
        const stockBase = parseFloat(String(lote.stock)) || 0;
        if (stockBase <= 0) {
            continue;
        }
        const tomarBase = Math.min(stockBase, pendiente);
        lote.cantidad_asignada = factor > 0 ? tomarBase / factor : tomarBase;
        pendiente -= tomarBase;
    }
}

export function totalAsignadoBaseLotes(lotes: any[], detalle: any): number {
    const factor = factorConversionDetalle(detalle);
    return lotes.reduce((sum, lote) => {
        const cantidad = parseFloat(String(lote.cantidad_asignada ?? 0)) || 0;
        return sum + cantidad * factor;
    }, 0);
}

export function textoResumenLotesDetalle(detalle: any): string {
    if (detalle?.lotes_asignados?.length) {
        const factor = factorConversionDetalle(detalle);
        return detalle.lotes_asignados
            .map((item: any) => {
                const cantidadBase = parseFloat(String(item.cantidad)) || 0;
                const cantidad = factor > 0 ? cantidadBase / factor : cantidadBase;
                const numero = item.numero_lote || item.lote?.numero_lote || `#${item.lote_id}`;
                return `${numero} (${formatCantidadLote(cantidad)})`;
            })
            .join(', ');
    }
    if (detalle?.lote_id) {
        return detalle.lote?.numero_lote || 'N/A';
    }
    return 'Distribuir lotes';
}

export function formatCantidadLote(value: number): string {
    return Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/\.?0+$/, '');
}

export function limpiarAsignacionLotesDetalle(detalle: any): void {
    detalle.lotes_asignados = null;
    detalle.lote_id = null;
    detalle.lote = null;
}
