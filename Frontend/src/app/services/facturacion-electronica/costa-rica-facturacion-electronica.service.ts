import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

/**
 * FE Costa Rica: factura 01, tiquete 04, nota crédito 03 (devolución), nota débito 02.
 */
@Injectable({ providedIn: 'root' })
export class CostaRicaFacturacionElectronicaService {
  constructor(private readonly api: ApiService) {}

  /**
   * Factura / Crédito fiscal → 01; Ticket / Tiquete → 04.
   */
  emitirComprobanteVenta(venta: any): Promise<any> {
    const doc = (venta.nombre_documento || '').toLowerCase();
    const isTicket = doc.includes('ticket') || doc.includes('tiquete');
    const endpoint = isTicket ? 'emitirFeCrTiqueteVenta' : 'emitirFeCrVenta';
    return this.postEmisionVenta(endpoint, venta);
  }

  /** @deprecated Usar emitirComprobanteVenta */
  emitirFacturaVenta(venta: any): Promise<any> {
    return this.emitirComprobanteVenta(venta);
  }

  emitirNotaCreditoDevolucion(devolucion: any): Promise<any> {
    return new Promise((resolve, reject) => {
      this.api.store('emitirFeCrNotaCreditoDevolucion', { id: devolucion.id }).subscribe({
        next: (res: any) => {
          if (res?.devolucion) {
            Object.assign(devolucion, res.devolucion);
          }
          if (res?.aceptada) {
            resolve(devolucion);
            return;
          }
          const msg =
            typeof res?.detalle_estado?.messages === 'string'
              ? res.detalle_estado.messages
              : 'El comprobante no fue aceptado por Hacienda.';
          reject({ message: msg, devolucion });
        },
        error: (err) => {
          const m = err?.error?.error ?? err?.message ?? err;
          reject(typeof m === 'string' ? m : 'Error al emitir nota de crédito electrónica.');
        },
      });
    });
  }

  /** Nota de débito 02 referenciando factura 01 aceptada. */
  emitirNotaDebitoVenta(ventaId: number, motivo: string, montoLinea: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.api
        .store('emitirFeCrNotaDebitoVenta', {
          id: ventaId,
          motivo: motivo || '',
          monto_linea: montoLinea,
        })
        .subscribe({
          next: (res: any) => {
            const venta = res?.venta;
            if (res?.aceptada) {
              resolve(venta);
              return;
            }
            const msg =
              typeof res?.detalle_estado?.messages === 'string'
                ? res.detalle_estado.messages
                : 'La nota de débito no fue aceptada por Hacienda.';
            reject({ message: msg, venta });
          },
          error: (err) => {
            const m = err?.error?.error ?? err?.message ?? err;
            reject(typeof m === 'string' ? m : 'Error al emitir nota de débito electrónica.');
          },
        });
    });
  }

  consultarEstadoVenta(ventaId: number): Observable<any> {
    return this.api.store('consultarFeCrVenta', { id: ventaId });
  }

  private postEmisionVenta(endpoint: string, venta: any): Promise<any> {
    return new Promise((resolve, reject) => {
      this.api.store(endpoint, { id: venta.id }).subscribe({
        next: (res: any) => {
          if (res?.venta) {
            Object.assign(venta, res.venta);
          }
          if (res?.aceptada) {
            resolve(venta);
            return;
          }
          const msg =
            typeof res?.detalle_estado?.messages === 'string'
              ? res.detalle_estado.messages
              : 'El comprobante no fue aceptado por Hacienda. Revise la respuesta en los datos de la venta.';
          reject({ message: msg, venta });
        },
        error: (err) => {
          const m = err?.error?.error ?? err?.message ?? err;
          reject(typeof m === 'string' ? m : 'Error al emitir comprobante electrónico.');
        },
      });
    });
  }
}
