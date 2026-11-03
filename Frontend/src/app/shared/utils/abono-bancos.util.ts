import { ApiService } from '@services/api.service';
import { Observable } from 'rxjs';

export function normalizeBancosListResponse(raw: unknown): any[] {
  if (Array.isArray(raw)) {
    return raw;
  }
  if (raw && typeof raw === 'object' && Array.isArray((raw as { data?: unknown }).data)) {
    return (raw as { data: any[] }).data;
  }
  return [];
}

/** @deprecated Usar api.loadBancosParaAbono() */
export function loadBancosParaAbono(api: ApiService): Observable<any[]> {
  return new Observable((subscriber) => {
    api.loadBancosParaAbono().subscribe({
      next: ({ bancos }) => {
        subscriber.next(bancos);
        subscriber.complete();
      },
      error: (err) => subscriber.error(err),
    });
  });
}
