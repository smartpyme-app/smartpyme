import { Injectable } from '@angular/core';
import { HttpResponse } from '@angular/common/http';

interface CacheEntry {
  response: HttpResponse<any>;
  timestamp: number;
  ttl: number;
}

@Injectable({
  providedIn: 'root'
})
export class HttpCacheService {
  private cache = new Map<string, CacheEntry>();
  private readonly DEFAULT_TTL = 5 * 60 * 1000; // 5 minutos por defecto

  /**
   * Obtiene una respuesta del cache si existe y no ha expirado
   */
  get(url: string, params?: any): HttpResponse<any> | null {
    const key = this.generateCacheKey(url, params);
    const entry = this.cache.get(key);

    if (!entry) {
      return null;
    }

    // Verificar si el cache ha expirado
    const now = Date.now();
    if (now - entry.timestamp > entry.ttl) {
      this.cache.delete(key);
      return null;
    }

    return entry.response;
  }

  /**
   * Guarda una respuesta en el cache
   */
  set(url: string, response: HttpResponse<any>, ttl?: number, params?: any): void {
    const key = this.generateCacheKey(url, params);
    const cacheTTL = ttl || this.getTTLForUrl(url);

    this.cache.set(key, {
      response: response.clone(),
      timestamp: Date.now(),
      ttl: cacheTTL
    });
  }

  /**
   * Elimina una entrada específica del cache
   */
  delete(url: string, params?: any): void {
    const key = this.generateCacheKey(url, params);
    this.cache.delete(key);
  }

  /**
   * Invalida todas las entradas del cache que coincidan con un patrón de URL
   */
  invalidatePattern(pattern: string): void {
    const keysToDelete: string[] = [];
    
    this.cache.forEach((_, key) => {
      if (key.includes(pattern)) {
        keysToDelete.push(key);
      }
    });

    keysToDelete.forEach(key => this.cache.delete(key));
  }

  /**
   * Limpia todo el cache
   */
  clear(): void {
    this.cache.clear();
  }

  /**
   * Obtiene el tamaño del cache
   */
  size(): number {
    return this.cache.size;
  }

  /**
   * Genera una clave única para el cache basada en URL y parámetros
   */
  private generateCacheKey(url: string, params?: any): string {
    if (!params || Object.keys(params).length === 0) {
      return url;
    }

    // Ordenar parámetros para asegurar consistencia
    const sortedParams = Object.keys(params)
      .sort()
      .map(key => `${key}=${JSON.stringify(params[key])}`)
      .join('&');

    return `${url}?${sortedParams}`;
  }

  /**
   * Determina el TTL (Time To Live) basado en el tipo de endpoint
   */
  private getTTLForUrl(url: string): number {
    // Endpoints de listas de referencia - cache más largo
    if (url.includes('/list') || url.includes('/list-nombre')) {
      return 10 * 60 * 1000; // 10 minutos
    }

    // Endpoints de datos estáticos o constantes
    if (url.includes('/constants') || url.includes('/modules')) {
      return 30 * 60 * 1000; // 30 minutos
    }

    // Endpoints de datos que cambian frecuentemente - cache corto
    if (url.includes('/ventas') || url.includes('/compras')) {
      return 1 * 60 * 1000; // 1 minuto
    }

    // Por defecto, 5 minutos
    return this.DEFAULT_TTL;
  }

  /**
   * Limpia entradas expiradas del cache
   */
  cleanExpired(): void {
    const now = Date.now();
    const keysToDelete: string[] = [];

    this.cache.forEach((entry, key) => {
      if (now - entry.timestamp > entry.ttl) {
        keysToDelete.push(key);
      }
    });

    keysToDelete.forEach(key => this.cache.delete(key));
  }
}

