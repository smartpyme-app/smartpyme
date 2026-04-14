import { HttpErrorResponse } from '@angular/common/http';

/**
 * Obtiene un texto útil del error HTTP al emitir FE Costa Rica (422, 500, red, etc.).
 */
export function mensajeErrorHttpFeCr(err: unknown): string {
  if (err == null) {
    return 'Error al emitir comprobante electrónico.';
  }
  if (typeof err === 'string') {
    return err;
  }

  let payload: unknown = err;

  if (err instanceof HttpErrorResponse) {
    payload = err.error;
    if (typeof payload === 'string') {
      const t = payload.trim();
      if (t.startsWith('{')) {
        try {
          payload = JSON.parse(t) as unknown;
        } catch {
          return t.length > 500 ? `${t.slice(0, 500)}…` : t;
        }
      } else if (t.startsWith('<')) {
        return `Error del servidor (${err.status || '?'}). Revise logs del backend o la consola del navegador (F12).`;
      } else {
        return t.length > 500 ? `${t.slice(0, 500)}…` : t;
      }
    }
  }

  const extracted = extractMessageFromPayload(payload);
  if (extracted) {
    return extracted;
  }

  const e = err as Record<string, unknown>;
  const nested = extractMessageFromPayload(e['error']);
  if (nested) {
    return nested;
  }

  const msg = e['message'];
  if (typeof msg === 'string' && !msg.startsWith('Http failure response')) {
    return msg;
  }

  if (err instanceof HttpErrorResponse && err.status >= 500) {
    return `Error del servidor (${err.status}). ${err.statusText || ''}`.trim();
  }

  return 'Error al emitir comprobante electrónico.';
}

function extractMessageFromPayload(body: unknown): string | null {
  if (body == null) {
    return null;
  }
  if (typeof body === 'string') {
    const t = body.trim();
    if (!t || t.startsWith('<')) {
      return null;
    }
    return t.length > 800 ? `${t.slice(0, 800)}…` : t;
  }
  if (typeof body !== 'object') {
    return null;
  }
  const b = body as Record<string, unknown>;

  if (typeof b['message'] === 'string' && b['message'].trim()) {
    return String(b['message']);
  }
  if (typeof b['error'] === 'string' && b['error'].trim()) {
    return String(b['error']);
  }
  if (b['error'] && typeof b['error'] === 'object') {
    const inner = b['error'] as Record<string, unknown>;
    if (typeof inner['message'] === 'string') {
      return String(inner['message']);
    }
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

  return null;
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

  let body: Record<string, unknown> | undefined;
  if (err instanceof HttpErrorResponse && err.error && typeof err.error === 'object') {
    body = err.error as Record<string, unknown>;
  } else {
    const e = err as { error?: Record<string, unknown> };
    body = e?.error;
  }

  if (body && body['documento'] !== undefined && body['documento'] !== null) {
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

/** Texto para mostrar al usuario desde el reject de emitir FE (string o payload con message). */
export function mensajeEmitirFeCrParaUsuario(err: unknown): string {
  if (typeof err === 'string') {
    return err;
  }
  if (err && typeof err === 'object' && 'message' in err && typeof (err as { message: unknown }).message === 'string') {
    return (err as { message: string }).message;
  }
  return mensajeErrorHttpFeCr(err);
}
