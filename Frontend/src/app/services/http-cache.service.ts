import { Injectable } from '@angular/core';

/**
 * Caché en memoria para invalidar respuestas HTTP cacheadas (claves de URL o path).
 * Si no hay HttpCache en interceptores, las invalidaciones quedan sin efecto, pero
 * un stub evita fallos de compilación.
 */
@Injectable({ providedIn: 'root' })
export class HttpCacheService {
  private readonly store = new Map<string, unknown>();

  delete(key: string): void {
    this.store.delete(key);
  }

  invalidatePattern(pattern: string): void {
    for (const k of this.store.keys()) {
      if (k.includes(pattern)) {
        this.store.delete(k);
      }
    }
  }
}
