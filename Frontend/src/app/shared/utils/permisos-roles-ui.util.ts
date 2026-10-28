/** SP-2339: permisos eliminar ocultos en UI; anular es la acción visible. */

export function permisoEliminarOculto(name: string): boolean {
  return typeof name === 'string' && name.endsWith('.eliminar');
}

export function etiquetaPermisoRoles(name: string): string {
  if (!name) {
    return '';
  }
  const segmento = name.split('.').pop() ?? name;
  if (segmento === 'anular') {
    return 'Anular';
  }
  if (segmento === 'ver_costos') {
    return 'Ver costos';
  }
  if (segmento === 'emitir') {
    return 'Emitir';
  }
  if (segmento.startsWith('generar_')) {
    return 'Generar partida (' + segmento.replace('generar_', '') + ')';
  }

  return segmento.replace(/_/g, ' ');
}

export type VerboPermiso = 'ver' | 'crear' | 'editar' | 'anular';

export function parsePermisoJerarquia(name: string): {
  modulo: string;
  submodulo: string | null;
  verbo: VerboPermiso | null;
} | null {
  if (!name || permisoEliminarOculto(name)) {
    return null;
  }

  const partes = name.split('.');
  if (partes.length < 2) {
    return null;
  }

  const verboRaw = partes[partes.length - 1];
  const verbos: VerboPermiso[] = ['ver', 'crear', 'editar', 'anular'];
  if (!verbos.includes(verboRaw as VerboPermiso)) {
    return null;
  }

  const verbo = verboRaw as VerboPermiso;
  if (partes.length === 2) {
    return { modulo: partes[0], submodulo: null, verbo };
  }

  return {
    modulo: partes[0],
    submodulo: partes.slice(1, -1).join('.'),
    verbo,
  };
}

export function permisoPadreJerarquia(name: string): string | null {
  const info = parsePermisoJerarquia(name);
  if (!info || !info.submodulo) {
    return null;
  }

  return `${info.modulo}.${info.verbo}`;
}

/** Hijos del permiso de módulo (mismo verbo en submódulos). */
export function esPermisoHijoDePadre(hijo: string, padre: string): boolean {
  const h = parsePermisoJerarquia(hijo);
  const p = parsePermisoJerarquia(padre);
  if (!h || !p || !h.submodulo || p.submodulo !== null) {
    return false;
  }

  return h.modulo === p.modulo && h.verbo === p.verbo;
}
