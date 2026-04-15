/**
 * Normaliza la respuesta de GET /api/fe-cr/contribuyente (cuerpo de /fe/ae de Hacienda CR).
 * La forma exacta del JSON puede variar; se contemplan convenciones habituales.
 */

export interface ContribuyenteActividadOption {
  codigo: string;
  descripcion: string;
  label: string;
}

function pickActividadesArray(root: Record<string, unknown>): unknown[] | null {
  /** Hacienda CR /fe/ae suele devolver `actividades` en la raíz del JSON. */
  const keys = [
    'actividades',
    'actividadesEconomicas',
    'actividades_economicas',
    'ActividadesEconomicas',
    'Actividades',
  ] as const;
  for (const k of keys) {
    const v = root[k];
    if (Array.isArray(v)) {
      return v;
    }
  }

  return null;
}

export function mapContribuyenteAeResponseToActividades(body: unknown): ContribuyenteActividadOption[] {
  if (body === null || typeof body !== 'object') {
    return [];
  }

  let root = body as Record<string, unknown>;
  const data = root['data'];
  if (data !== null && typeof data === 'object' && !Array.isArray(data)) {
    root = data as Record<string, unknown>;
  }
  const contrib = root['contribuyente'];
  if (contrib !== null && typeof contrib === 'object' && !Array.isArray(contrib)) {
    const inner = pickActividadesArray(contrib as Record<string, unknown>);
    if (inner?.length) {
      return mapActividadRows(inner);
    }
  }

  const arr = pickActividadesArray(root);
  if (!arr?.length) {
    return [];
  }

  return mapActividadRows(arr);
}

function mapActividadRows(arr: unknown[]): ContribuyenteActividadOption[] {
  const out: ContribuyenteActividadOption[] = [];
  for (const row of arr) {
    if (row === null || typeof row !== 'object') {
      continue;
    }
    const r = row as Record<string, unknown>;
    const codRaw =
      r['codigo'] ??
      r['Codigo'] ??
      r['cod'] ??
      r['codigoActividad'] ??
      r['codActividad'] ??
      r['id'] ??
      '';
    const codigo = String(codRaw).trim();
    if (codigo === '') {
      continue;
    }
    const descRaw =
      r['descripcion'] ??
      r['Descripcion'] ??
      r['nombre'] ??
      r['Nombre'] ??
      r['descripcionActividad'] ??
      '';
    const descripcion = String(descRaw).trim();
    const label = descripcion !== '' ? `${codigo} — ${descripcion}` : codigo;
    out.push({ codigo, descripcion, label });
  }

  return out;
}

