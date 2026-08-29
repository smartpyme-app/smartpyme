import { Observable, of, throwError } from 'rxjs';
import { catchError, map } from 'rxjs/operators';

import { ApiService } from '@services/api.service';

/** Valida PIN de supervisor (users.codigo) — mismo flujo que facturación. */
export function validarCodigoSupervisor(api: ApiService, codigo: string): Observable<void> {
  const pin = String(codigo ?? '').trim();
  if (!pin) {
    return throwError(() => ({ error: 'Ingrese el código de supervisor.' }));
  }
  return api.store('usuario-validar', { codigo: pin }).pipe(map(() => undefined));
}

export function mensajeErrorCierreMesa(err: any): string {
  const body = err?.error ?? err;
  if (typeof body === 'string') {
    return body;
  }
  return body?.error ?? body?.message ?? 'No se pudo cerrar la mesa.';
}

export function requiereCodigoSupervisorEnError(err: any): boolean {
  const body = err?.error ?? err;
  return !!body?.requiere_codigo_supervisor;
}

/** Si el error pide supervisor, devuelve el código ingresado; si no, null. */
export function codigoSupervisorSiRequerido(
  api: ApiService,
  err: any,
  codigoIngresado: string | null | undefined
): Observable<string | null> {
  if (!requiereCodigoSupervisorEnError(err) || !codigoIngresado) {
    return of(null);
  }
  return validarCodigoSupervisor(api, codigoIngresado).pipe(map(() => String(codigoIngresado).trim()));
}
