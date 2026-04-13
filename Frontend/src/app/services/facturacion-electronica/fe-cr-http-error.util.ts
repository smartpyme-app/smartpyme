/**
 * Obtiene un texto útil del error HTTP al emitir FE Costa Rica (Laravel 422, etc.).
 */
export function mensajeErrorHttpFeCr(err: unknown): string {
  if (err == null) {
    return 'Error al emitir comprobante electrónico.';
  }
  if (typeof err === 'string') {
    return err;
  }
  const e = err as Record<string, unknown>;
  const body = e['error'] as unknown;

  if (typeof body === 'string') {
    return body;
  }
  if (body && typeof body === 'object') {
    const b = body as Record<string, unknown>;
    if (typeof b['error'] === 'string') {
      return b['error'] as string;
    }
    if (typeof b['message'] === 'string') {
      return b['message'] as string;
    }
    const errors = b['errors'];
    if (errors && typeof errors === 'object') {
      const vals = Object.values(errors as Record<string, unknown>);
      for (const v of vals) {
        if (Array.isArray(v) && v.length > 0 && typeof v[0] === 'string') {
          return v[0] as string;
        }
        if (typeof v === 'string') {
          return v;
        }
      }
    }
  }
  const msg = e['message'];
  if (typeof msg === 'string' && !msg.startsWith('Http failure response')) {
    return msg;
  }

  return 'Error al emitir comprobante electrónico.';
}

/** Datos extra cuando el backend devuelve 422 con `documento` (payload intentado). */
export interface FeCrErrorEmisionPayload {
  message: string;
  documento: unknown;
  clave?: string | null;
  detalle_estado?: unknown;
  /** XML generado para Hacienda (etiquetas XSD en español), sin firma digital. */
  xml_comprobante?: string | null;
  /** XML firmado si el fallo fue después de firmar (p. ej. red); puede ser muy largo. */
  xml_comprobante_firmado?: string | null;
}

/**
 * Si la respuesta 422 incluye el JSON del comprobante intentado, lo devuelve estructurado;
 * si no, solo el mensaje como string.
 */
export function errorEmisionFeCr(err: unknown): string | FeCrErrorEmisionPayload {
  const message = mensajeErrorHttpFeCr(err);
  const body = (err as { error?: Record<string, unknown> })?.error;
  if (body && typeof body === 'object' && body['documento'] !== undefined && body['documento'] !== null) {
    return {
      message,
      documento: body['documento'],
      clave: (body['clave'] as string | null | undefined) ?? null,
      detalle_estado: body['detalle_estado'],
      xml_comprobante: (body['xml_comprobante'] as string | null | undefined) ?? null,
      xml_comprobante_firmado: (body['xml_comprobante_firmado'] as string | null | undefined) ?? null,
    };
  }

  return message;
}
