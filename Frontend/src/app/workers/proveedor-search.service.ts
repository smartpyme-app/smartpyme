import { Injectable } from '@angular/core';
import { Observable, Subject } from 'rxjs';

/**
 * Servicio para manejar búsquedas de proveedores usando Web Workers
 */
@Injectable({
  providedIn: 'root'
})
export class ProveedorSearchService {
  private worker: Worker | null = null;
  private messageId = 0;
  private pendingRequests = new Map<string, Subject<any>>();
  private workerInitializing = false;

  constructor() {
    // Inicialización lazy del worker - se crea cuando se necesita
  }

  private initializeWorker(): void {
    if (this.worker || this.workerInitializing || typeof Worker === 'undefined') {
      return;
    }

    this.workerInitializing = true;

    try {
      // En desarrollo, los workers pueden tener problemas de CORS
      // Por ahora, deshabilitamos workers y usamos fallback
      // Se pueden habilitar en producción si es necesario
      const useWorkers = false; // Cambiar a true cuando se configure correctamente para producción
      
      if (useWorkers) {
        // Intentar cargar el worker usando la sintaxis moderna
        if (typeof import.meta !== 'undefined' && import.meta.url) {
          this.worker = new Worker(
            new URL('./proveedor-search.worker', import.meta.url),
            { type: 'module' }
          );

          this.worker.onmessage = ({ data }) => {
            const { id, result, error } = data;
            const subject = this.pendingRequests.get(id);
            if (subject) {
              if (error) {
                subject.error(new Error(error));
              } else {
                subject.next(result);
                subject.complete();
              }
              this.pendingRequests.delete(id);
            }
          };

          this.worker.onerror = (error) => {
            console.error('Error en Web Worker:', error);
            this.pendingRequests.forEach((subject) => {
              subject.error(error);
            });
            this.pendingRequests.clear();
            this.worker = null;
            this.workerInitializing = false;
          };
        }
      } else {
        // No usar workers, usar fallback directamente
        this.worker = null;
        this.workerInitializing = false;
      }
    } catch (error) {
      console.warn('No se pudo inicializar Web Worker, usando fallback:', error);
      this.worker = null;
      this.workerInitializing = false;
    }
  }

  /**
   * Busca un proveedor usando múltiples criterios
   */
  searchProveedor(proveedor: any, proveedores: any[]): Observable<any> {
    return this.sendMessage<any>({
      type: 'SEARCH_PROVEEDOR',
      proveedor,
      proveedores
    });
  }

  /**
   * Busca un proveedor por NIT
   */
  searchNit(nit: string, proveedores: any[]): Observable<any> {
    return this.sendMessage<any>({
      type: 'SEARCH_NIT',
      nit,
      proveedores
    });
  }

  /**
   * Busca un proveedor por NRC
   */
  searchNrc(nrc: string, proveedores: any[]): Observable<any> {
    return this.sendMessage<any>({
      type: 'SEARCH_NRC',
      nrc,
      proveedores
    });
  }

  /**
   * Busca un proveedor por DUI
   */
  searchDui(dui: string, proveedores: any[]): Observable<any> {
    return this.sendMessage<any>({
      type: 'SEARCH_DUI',
      dui,
      proveedores
    });
  }

  /**
   * Busca un proveedor por nombre
   */
  searchNombre(nombre: string, proveedores: any[]): Observable<any> {
    return this.sendMessage<any>({
      type: 'SEARCH_NOMBRE',
      nombre,
      proveedores
    });
  }

  private sendMessage<T>(message: any): Observable<T> {
    const subject = new Subject<T>();
    const id = `msg_${++this.messageId}_${Date.now()}`;
    
    this.pendingRequests.set(id, subject);

    // Inicializar worker si no está creado
    if (!this.worker && !this.workerInitializing) {
      this.initializeWorker();
    }

    if (this.worker) {
      this.worker.postMessage({ ...message, id });
    } else {
      // Fallback si Web Workers no están disponibles
      setTimeout(() => {
        try {
          const result = this.fallbackSearch(message) as T;
          subject.next(result);
          subject.complete();
        } catch (error) {
          subject.error(error);
        }
        this.pendingRequests.delete(id);
      }, 0);
    }

    return subject.asObservable();
  }

  /**
   * Fallback para cuando Web Workers no están disponibles
   */
  private fallbackSearch(message: any): any {
    const { type, proveedores, proveedor, nit, nrc, dui, nombre } = message;

    switch (type) {
      case 'SEARCH_PROVEEDOR':
        return this.fallbackSearchProveedor(proveedor, proveedores);
      case 'SEARCH_NIT':
        return proveedores.find((p: any) => p.nit === nit || p.nit == nit) || null;
      case 'SEARCH_NRC':
        return proveedores.find((p: any) => p.ncr === nrc || p.ncr == nrc) || null;
      case 'SEARCH_DUI':
        return proveedores.find((p: any) => p.dui === dui || p.dui == dui) || null;
      case 'SEARCH_NOMBRE':
        return proveedores.find((p: any) =>
          p.nombre_empresa === nombre ||
          p.nombre_empresa == nombre ||
          p.nombre === nombre ||
          p.nombre == nombre
        ) || null;
      default:
        return null;
    }
  }

  private fallbackSearchProveedor(proveedor: any, proveedores: any[]): any {
    let proveedorEncontrado: any = null;

    if (proveedor.nit) {
      proveedorEncontrado = proveedores.find((p: any) => p.nit === proveedor.nit || p.nit == proveedor.nit);
      if (proveedorEncontrado) return proveedorEncontrado;
    }

    if (proveedor.nrc && !proveedorEncontrado) {
      proveedorEncontrado = proveedores.find((p: any) => p.ncr === proveedor.nrc || p.ncr == proveedor.nrc);
      if (proveedorEncontrado) return proveedorEncontrado;
    }

    if (proveedor.dui && !proveedorEncontrado) {
      proveedorEncontrado = proveedores.find((p: any) => p.dui === proveedor.dui || p.dui == proveedor.dui);
      if (proveedorEncontrado) return proveedorEncontrado;
    }

    if (!proveedorEncontrado && proveedor.nombre) {
      proveedorEncontrado = proveedores.find((p: any) =>
        p.nombre_empresa === proveedor.nombre ||
        p.nombre_empresa == proveedor.nombre ||
        p.nombre === proveedor.nombre ||
        p.nombre == proveedor.nombre
      );
    }

    return proveedorEncontrado || null;
  }

  /**
   * Limpia recursos cuando el servicio se destruye
   */
  destroy(): void {
    if (this.worker) {
      this.worker.terminate();
      this.worker = null;
    }
    this.pendingRequests.clear();
  }
}

