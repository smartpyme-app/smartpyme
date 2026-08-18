export const PREFERENCIAS_GRUPOS = [
    'modulos',
    'documentos',
    'facturacion',
    'inventario',
    'permisos',
    'cuenta',
] as const;

export type PreferenciasGrupo = typeof PREFERENCIAS_GRUPOS[number];

export function resolverGrupoPreferencias(
    slug: string | null | undefined,
    puedeVerPermisos: boolean,
): PreferenciasGrupo {
    if (slug === 'permisos' && !puedeVerPermisos) {
        return 'modulos';
    }
    if (slug && (PREFERENCIAS_GRUPOS as readonly string[]).includes(slug)) {
        return slug as PreferenciasGrupo;
    }
    return 'modulos';
}
