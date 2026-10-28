/** Menú ⋮ ventas/cotizaciones: permisos granulares (roles custom / ventas limitado). */

export function puedeVerDetalleVenta(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.ver') || hasPermission('ventas.registros.ver');
}

export function puedeImprimirVenta(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.editar') || hasPermission('ventas.registros.ver');
}

export function puedePagarVenta(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.abonos.crear') || hasPermission('ventas.editar');
}

export function puedeDevolucionVenta(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.devoluciones.crear');
}

export function puedeVerDteVenta(_hasPermission: (perm: string) => boolean): boolean {
    // ponytail: temporal — menú emitir/ver DTE; reactivar ventas.facturacion_electronica.emitir cuando roles estén asignados.
    return true;
}

export function puedeEditarVenta(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.editar');
}

export function puedeFacturarDesdeCotizacion(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.crear');
}

export function puedeVerDetalleCotizacion(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.cotizaciones.ver') || hasPermission('ventas.cotizaciones.editar');
}

export function puedeEditarCotizacion(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.cotizaciones.editar');
}

export function puedeImprimirCotizacion(hasPermission: (perm: string) => boolean): boolean {
    return puedeVerDetalleCotizacion(hasPermission);
}

export function puedeAnularVenta(hasPermission: (perm: string) => boolean): boolean {
    const anular = hasPermission('ventas.anular');
    const eliminar = hasPermission('ventas.eliminar');
    return anular || eliminar;
}

export function puedeCrearVenta(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.crear');
}

/** NC/ND y devolución física comparten permiso de crear devolución. */
export function puedeNotaCreditoDebitoVenta(hasPermission: (perm: string) => boolean): boolean {
    return hasPermission('ventas.devoluciones.crear');
}
