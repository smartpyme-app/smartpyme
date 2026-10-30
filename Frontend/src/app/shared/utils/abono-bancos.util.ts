import { ApiService } from '@services/api.service';
import { Observable, map } from 'rxjs';

/** Cuentas bancarias (módulo bancos) o catálogo / bancos activos de la empresa. */
export function loadBancosParaAbono(api: ApiService): Observable<any[]> {
  if (api.isModuloBancos()) {
    return api.getAll('banco/cuentas/list').pipe(map(normalizeList));
  }
  return api.getAll('bancos/list').pipe(map(normalizeList));
}

function normalizeList(raw: unknown): any[] {
  if (Array.isArray(raw)) {
    return raw;
  }
  if (raw && typeof raw === 'object' && Array.isArray((raw as { data?: unknown }).data)) {
    return (raw as { data: any[] }).data;
  }
  return [];
}
