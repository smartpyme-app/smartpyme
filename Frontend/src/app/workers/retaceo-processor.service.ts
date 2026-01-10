import { Injectable } from '@angular/core';
import { Observable, Subject } from 'rxjs';

/**
 * Servicio para procesar datos de retaceo usando Web Workers
 */
@Injectable({
  providedIn: 'root'
})
export class RetaceoProcessorService {
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
            new URL('./retaceo-processor.worker', import.meta.url),
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
   * Procesa gastos del retaceo
   */
  processGastos(gastos: any[]): Observable<{ [tipo: string]: { lista: any[]; seleccionados: number[] } }> {
    return this.sendMessage({
      type: 'PROCESS_GASTOS',
      gastos
    });
  }

  /**
   * Procesa distribución del retaceo
   */
  processDistribucion(distribucion: any[]): Observable<any[]> {
    return this.sendMessage({
      type: 'PROCESS_DISTRIBUCION',
      distribucion
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
          const result = this.fallbackProcess(message) as T;
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
  private fallbackProcess(message: any): any {
    const { type, gastos, distribucion } = message;

    switch (type) {
      case 'PROCESS_GASTOS':
        return this.fallbackProcessGastos(gastos);
      case 'PROCESS_DISTRIBUCION':
        return distribucion.map((item: any) => ({
          ...item,
          cantidad: parseFloat(item.cantidad || 0),
          costo_original: parseFloat(item.costo_original || 0),
          valor_fob: parseFloat(item.valor_fob || 0),
          porcentaje_distribucion: parseFloat(item.porcentaje_distribucion || 0),
          porcentaje_dai: parseFloat(item.porcentaje_dai || 0),
          monto_transporte: parseFloat(item.monto_transporte || 0),
          monto_seguro: parseFloat(item.monto_seguro || 0),
          monto_dai: parseFloat(item.monto_dai || 0),
          monto_otros: parseFloat(item.monto_otros || 0),
          costo_landed: parseFloat(item.costo_landed || 0),
          costo_retaceado: parseFloat(item.costo_retaceado || 0),
        }));
      default:
        return null;
    }
  }

  private fallbackProcessGastos(gastos: any[]): any {
    const gastosMap: any = {};

    gastos.forEach((gasto: any) => {
      const tipo = gasto.tipo_gasto;

      if (!gastosMap[tipo]) {
        gastosMap[tipo] = {
          lista: [],
          seleccionados: []
        };
      }

      const gastoObj = {
        id: gasto.id,
        id_retaceo: gasto.id_retaceo,
        id_gasto: gasto.id_gasto,
        tipo_gasto: tipo,
        monto: parseFloat(gasto.monto || 0)
      };

      gastosMap[tipo].lista.push(gastoObj);
      gastosMap[tipo].seleccionados.push(gasto.id_gasto);
    });

    return gastosMap;
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

