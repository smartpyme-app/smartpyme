/**
 * Normaliza la respuesta de GET /api/fe-cr/cabys (cuerpo tal cual devuelve Hacienda).
 * La forma exacta del JSON puede variar; se intentan varias convenciones habituales.
 */

export interface CabysSelectOption {
  codigo: string;
  descripcion: string;
  label: string;
  /** Tarifa de impuesto del catálogo CABYS (p. ej. 1, 2, 4, 13). */
  impuestoTarifa: number | null;
}

/**
 * Convierte el campo `impuesto` / `tax` de Hacienda al porcentaje de impuesto del producto.
 * En CABYS suele venir el % directo (1, 2, 4, 13); a veces códigos de tarifa FE (08 → 13, 10 → 0).
 */
export function cabysImpuestoTarifaToPorcentaje(raw: unknown): number | null {
  if (raw === null || raw === undefined || raw === '') {
    return null;
  }

  const n = Number(raw);
  if (!Number.isFinite(n)) {
    return null;
  }

  const codigoTarifaFe: Record<number, number> = {
    8: 13,
    10: 0,
    3: 2,
  };
  if (Object.prototype.hasOwnProperty.call(codigoTarifaFe, n)) {
    return codigoTarifaFe[n];
  }

  const tarifasCr = [0, 1, 2, 4, 13];
  if (tarifasCr.includes(n)) {
    return n;
  }

  if (n >= 0 && n <= 100) {
    return n;
  }

  return null;
}

function pickImpuestoTarifa(row: Record<string, unknown>): number | null {
  const raw =
    row['impuesto'] ??
    row['Impuesto'] ??
    row['tax'] ??
    row['Tax'] ??
    row['tarifa'] ??
    row['Tarifa'] ??
    null;

  return cabysImpuestoTarifaToPorcentaje(raw);
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
    const impuestoTarifa = pickImpuestoTarifa(row);
    out.push({
      codigo,
      descripcion,
      label: descripcion ? `${codigo} — ${descripcion}` : `${codigo}`,
      impuestoTarifa,
    });
  }

  return out;
}
