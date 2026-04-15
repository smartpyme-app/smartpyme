import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { MHService } from '@services/MH.service';

/**
 * Implementación FE El Salvador (DTE MH). Delega en {@link MHService} sin modificar su lógica.
 */
@Injectable({ providedIn: 'root' })
export class ElSalvadorFacturacionElectronicaService {
  constructor(private readonly mh: MHService) {}

  emitirDTE(venta: any): Promise<any> {
    return this.mh.emitirDTE(venta);
  }

  emitirDTENotaCredito(venta: any): Promise<any> {
    return this.mh.emitirDTENotaCredito(venta);
  }

  emitirDTESujetoExcluidoGasto(gasto: any): Promise<any> {
    return this.mh.emitirDTESujetoExcluidoGasto(gasto);
  }

  emitirDTESujetoExcluidoCompra(compra: any): Promise<any> {
    return this.mh.emitirDTESujetoExcluidoCompra(compra);
  }

  emitirDTEContingencia(venta: any): Promise<any> {
    return this.mh.emitirDTEContingencia(venta);
  }

  firmarDTE(dte: any): Observable<any> {
    return this.mh.firmarDTE(dte);
  }

  enviarDTE(doc: any, dteFirmado: any): Observable<any> {
    return this.mh.enviarDTE(doc, dteFirmado);
  }

  enviarContingenciaDTEs(venta: any, dteFirmado: any): Observable<any> {
    return this.mh.enviarContingenciaDTEs(venta, dteFirmado);
  }

  anularDTE(doc: any, dteFirmado: any): Observable<any> {
    return this.mh.anularDTE(doc, dteFirmado);
  }

  consultarDTE(venta: any): Observable<any> {
    return this.mh.consultarDTE(venta);
  }
}
