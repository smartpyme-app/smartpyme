import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';
import { errorEmisionFeCr } from './fe-cr-http-error.util';

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
          reject(errorEmisionFeCr(err));
        },
      });
    });
  }

  consultarEstadoVenta(ventaId: number): Observable<any> {
    return this.api.store('consultarFeCrVenta', { id: ventaId });
  }

  /** FEC 08 — compra (documento «Compra electrónica»). */
  emitirFacturaElectronicaCompra(compra: any): Promise<any> {
    return this.postEmisionCompraGasto('emitirFeCrCompra', compra, 'compra');
  }

  /** FEC 08 — gasto/egreso (documento «Compra electrónica»). */
  emitirFacturaElectronicaGasto(gasto: any): Promise<any> {
    return this.postEmisionCompraGasto('emitirFeCrGasto', gasto, 'gasto');
  }

  private postEmisionCompraGasto(
    endpoint: string,
    doc: any,
    kind: 'compra' | 'gasto'
  ): Promise<any> {
    return new Promise((resolve, reject) => {
      this.api.store(endpoint, { id: doc.id }).subscribe({
        next: (res: any) => {
          const key = kind === 'compra' ? 'compra' : 'gasto';
          if (res?.[key]) {
            Object.assign(doc, res[key]);
          }
          if (res?.aceptada) {
            resolve(doc);
            return;
          }
          const msg =
            typeof res?.detalle_estado?.messages === 'string'
              ? res.detalle_estado.messages
              : 'El comprobante no fue aceptado por Hacienda.';
          reject({ message: msg, [key]: doc });
        },
        error: (err) => {
          reject(errorEmisionFeCr(err));
        },
      });
    });
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
          reject(errorEmisionFeCr(err));
        },
      });
    });
  }
}
