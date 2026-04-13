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
  const raw =
    row['codigo'] ??
    row['Codigo'] ??
    row['code'] ??
    row['cabys'] ??
    row['codigoCabys'] ??
    row['codigoCABYS'] ??
    row['CodigoCabys'] ??
    '';

  if (typeof raw === 'number' && Number.isFinite(raw)) {
    const n = Math.trunc(Math.abs(raw));

    return String(n).replace(/\D/g, '').padStart(13, '0').slice(-13);
  }

  return String(raw).replace(/\D/g, '');
}

/** CABYS son 13 dígitos; la API a veces devuelve número o cadena sin ceros a la izquierda. */
function normalizeCabys13Digits(digits: string): string {
  if (digits.length === 0) {
    return '';
  }
  if (digits.length >= 13) {
    return digits.slice(0, 13);
  }

  return digits.padStart(13, '0');
}

function pickDescripcion(row: Record<string, unknown>): string {
  const raw =
    row['descripcion'] ??
    row['Descripcion'] ??
    row['description'] ??
    row['nombre'] ??
    row['Nombre'] ??
    row['texto'] ??
    row['Texto'] ??
    row['detalle'] ??
    row['Detalle'] ??
    '';

  return String(raw).trim();
}

/** Extrae el array de filas CABYS (Hacienda suele devolver { cabys, cantidad, total }; a veces PascalCase o data). */
function extractCabysRows(body: unknown): Record<string, unknown>[] {
  if (body == null) {
    return [];
  }

  if (Array.isArray(body)) {
    return body as Record<string, unknown>[];
  }

  if (typeof body !== 'object') {
    return [];
  }

  const tryRowsFrom = (o: Record<string, unknown>): Record<string, unknown>[] | null => {
    const keys = [
      'cabys',
      'Cabys',
      'CABYS',
      'items',
      'Items',
      'data',
      'Data',
      'resultados',
      'results',
      'lista',
    ] as const;
    for (const k of keys) {
      const v = o[k];
      if (!Array.isArray(v)) {
        continue;
      }
      if (v.length === 0) {
        return [];
      }
      const first = v[0];
      if (first && typeof first === 'object' && !Array.isArray(first)) {
        return v as Record<string, unknown>[];
      }
    }

    return null;
  };

  const o = body as Record<string, unknown>;
  let rows = tryRowsFrom(o);
  if (rows === null && o['data'] && typeof o['data'] === 'object' && !Array.isArray(o['data'])) {
    rows = tryRowsFrom(o['data'] as Record<string, unknown>);
  }
  if (rows === null) {
    const found = firstObjectArray(o);
    rows = found ?? [];
  }

  return rows;
}

export function mapCabysApiResponseToOptions(body: unknown): CabysSelectOption[] {
  const rows = extractCabysRows(body);

  const out: CabysSelectOption[] = [];

  for (const row of rows) {
    if (!row || typeof row !== 'object') {
      continue;
    }
    const digits = pickCodigo(row);
    const codigo = normalizeCabys13Digits(digits);
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
