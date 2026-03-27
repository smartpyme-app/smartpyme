/**
 * Normaliza la respuesta de GET /api/fe-cr/cabys (cuerpo tal cual devuelve Hacienda).
 * La forma exacta del JSON puede variar; se intentan varias convenciones habituales.
 */

export interface CabysSelectOption {
  codigo: string;
  descripcion: string;
  label: string;
}

function firstObjectArray(body: Record<string, unknown>): Record<string, unknown>[] | null {
  for (const v of Object.values(body)) {
    if (!Array.isArray(v) || v.length === 0) {
      continue;
    }
    const first = v[0];
    if (first && typeof first === 'object' && !Array.isArray(first)) {
      return v as Record<string, unknown>[];
    }
  }

  return null;
}

function pickCodigo(row: Record<string, unknown>): string {
  const raw = row['codigo'] ?? row['code'] ?? row['cabys'] ?? row['Codigo'] ?? '';

  return String(raw).replace(/\D/g, '');
}

function pickDescripcion(row: Record<string, unknown>): string {
  const raw =
    row['descripcion'] ??
    row['description'] ??
    row['nombre'] ??
    row['Descripcion'] ??
    '';

  return String(raw).trim();
}

export function mapCabysApiResponseToOptions(body: unknown): CabysSelectOption[] {
  if (body == null) {
    return [];
  }

  let rows: Record<string, unknown>[] = [];

  if (Array.isArray(body)) {
    rows = body as Record<string, unknown>[];
  } else if (typeof body === 'object') {
    const o = body as Record<string, unknown>;
    const direct = ['cabys', 'items', 'data', 'resultados', 'results', 'lista'] as const;
    for (const k of direct) {
      const v = o[k];
      if (Array.isArray(v)) {
        rows = v as Record<string, unknown>[];
        break;
      }
    }
    if (rows.length === 0 && o['data'] && typeof o['data'] === 'object') {
      const inner = o['data'] as Record<string, unknown>;
      if (Array.isArray(inner['cabys'])) {
        rows = inner['cabys'] as Record<string, unknown>[];
      }
    }
    if (rows.length === 0) {
      const found = firstObjectArray(o);
      if (found) {
        rows = found;
      }
    }
  }

  const out: CabysSelectOption[] = [];

  for (const row of rows) {
    if (!row || typeof row !== 'object') {
      continue;
    }
    const digits = pickCodigo(row);
    const codigo = digits.length >= 13 ? digits.slice(0, 13) : digits;
    if (codigo.length !== 13) {
      continue;
    }
    const descripcion = pickDescripcion(row);
    out.push({
      codigo,
      descripcion,
      label: descripcion ? `${codigo} — ${descripcion}` : `${codigo}`,
    });
  }

  return out;
}
