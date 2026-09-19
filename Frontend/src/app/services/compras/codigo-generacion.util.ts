export function normalizarCodigoGeneracion(codigo: unknown): string {
  return String(codigo ?? '').trim().toUpperCase();
}

export function codigoGeneracionDesdeDte(dte: unknown): string {
  const ident = (dte as { identificacion?: { codigoGeneracion?: unknown; clave?: unknown } } | null)
    ?.identificacion;
  return normalizarCodigoGeneracion(ident?.codigoGeneracion ?? ident?.clave);
}

export function mensajeErrorDocumentoImport(err: unknown): string {
  const e = err as { error?: { error?: string }; message?: string } | string | null;
  if (typeof e === 'string' && e.trim()) {
    return e;
  }
  if (e && typeof e === 'object') {
    return e.error?.error || e.message || 'No se pudo interpretar el documento electrónico.';
  }
  return 'No se pudo interpretar el documento electrónico.';
}

export function esErrorCodigoGeneracionDuplicado(err: unknown): boolean {
  return /c[oó]digo de generaci[oó]n/i.test(mensajeErrorDocumentoImport(err));
}

export function codigoGeneracionYaUsadoEnLote(
  codigo: string,
  usados: Iterable<string>
): boolean {
  const n = normalizarCodigoGeneracion(codigo);
  if (!n) {
    return false;
  }
  for (const u of usados) {
    if (normalizarCodigoGeneracion(u) === n) {
      return true;
    }
  }
  return false;
}

export const TITULO_CODIGO_GENERACION_DUPLICADO = 'Documento duplicado';
