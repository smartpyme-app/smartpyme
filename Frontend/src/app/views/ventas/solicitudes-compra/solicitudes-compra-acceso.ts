/** Menú solicitudes: padre tiene `licencia`; hijas solo `es_empresa_hija`. */
export function perteneceOrganizacionLicencia(
    empresa: { licencia?: unknown; es_empresa_hija?: boolean; es_empresa_padre?: boolean } | null | undefined,
): boolean {
    if (!empresa) {
        return false;
    }
    return !!(empresa.licencia || empresa.es_empresa_hija || empresa.es_empresa_padre);
}

/** Submenú Licencias (Empresas/Usuarios org.): solo empresa padre con licencia de org. y permiso de ítem visible. */
export function mostrarMenuLicenciasOrganizacion(
    empresa: { licencia?: unknown; es_empresa_padre?: boolean } | null | undefined,
    hasPermission: (perm: string) => boolean,
): boolean {
    if (!empresa?.es_empresa_padre || !empresa.licencia) {
        return false;
    }
    return (
        hasPermission('organizacion.empresas.ver')
        || hasPermission('organizacion.usuarios.ver')
    );
}

/** Solicitudes cross-empresa: licencia de org. + permiso granular (p. ej. asignado a ventas_limitado). */
export function mostrarMenuSolicitudesCompra(
    empresa: { licencia?: unknown; es_empresa_hija?: boolean; es_empresa_padre?: boolean } | null | undefined,
    hasPermission: (perm: string) => boolean,
): boolean {
    if (!perteneceOrganizacionLicencia(empresa)) {
        return false;
    }

    return hasPermission('ventas.solicitudes.ver');
}
