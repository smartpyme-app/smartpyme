/** Menú solicitudes: padre tiene `licencia`; hijas solo `es_empresa_hija`. */
export function perteneceOrganizacionLicencia(
    empresa: { licencia?: unknown; es_empresa_hija?: boolean; es_empresa_padre?: boolean } | null | undefined,
): boolean {
    if (!empresa) {
        return false;
    }
    return !!(empresa.licencia || empresa.es_empresa_hija || empresa.es_empresa_padre);
}
