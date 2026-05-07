/**
 * Cuerpo JSON típico de recepción DTE (MH El Salvador).
 */
function lineasDesdeCuerpoMh(body: any): string[] {
  const list: string[] = [];
  if (!body || typeof body !== 'object') {
    return list;
  }
  const obs = body.observaciones;
  if (Array.isArray(obs)) {
    for (const o of obs) {
      const s = o != null ? String(o).trim() : '';
      if (s !== '') {
        list.push(s);
      }
    }
  }
  const desc = body.descripcionMsg;
  if (desc != null && String(desc).trim() !== '') {
    list.push(String(desc).trim());
  }
  return list;
}

function esComoHttpError(o: any): boolean {
  return o && typeof o === 'object' && typeof o.status === 'number' && 'error' in o;
}

/**
 * Convierte cualquier valor que venga de firma, recepción o anulación MH en líneas de texto.
 * Usar siempre el error completo (HttpErrorResponse, cuerpo JSON, string, array).
 */
export function normalizarErroresHacienda(raw: any): string[] {
  if (raw == null || raw === '') {
    return [];
  }
  if (raw instanceof Error && raw.message?.trim()) {
    return [raw.message.trim()];
  }
  if (typeof raw === 'string') {
    const t = raw.trim();
    return t ? [t] : [];
  }
  if (Array.isArray(raw)) {
    return raw.map((x) => String(x ?? '').trim()).filter((s) => s !== '');
  }
  if (typeof raw !== 'object') {
    return [];
  }

  // HttpClient error (status + body en .error)
  if (esComoHttpError(raw)) {
    const body = raw.error;
    if (typeof body === 'string') {
      const t = body.trim();
      return t ? [t] : [];
    }
    if (body && typeof body === 'object') {
      const lines = lineasDesdeCuerpoMh(body);
      if (lines.length > 0) {
        return lines;
      }
      if (body.mensaje != null && String(body.mensaje).trim() !== '') {
        return [String(body.mensaje).trim()];
      }
    }
    return [];
  }

  // Cuerpo MH plano (respuesta 200 con estado RECHAZADO, etc.)
  let lines = lineasDesdeCuerpoMh(raw);
  if (lines.length > 0) {
    return lines;
  }

  if (raw.error && typeof raw.error === 'object' && !Array.isArray(raw.error)) {
    lines = lineasDesdeCuerpoMh(raw.error);
    if (lines.length > 0) {
      return lines;
    }
  }

  if (raw.mensaje != null && String(raw.mensaje).trim() !== '') {
    return [String(raw.mensaje).trim()];
  }
  if (raw.message != null && typeof raw.message === 'string' && raw.message.trim() !== '') {
    return [raw.message.trim()];
  }

  return [];
}

/** @deprecated Usar normalizarErroresHacienda; se mantiene por compatibilidad con imports antiguos */
export function lineasErrorRecepcionMh(body: any): string[] {
  return lineasDesdeCuerpoMh(body);
}
