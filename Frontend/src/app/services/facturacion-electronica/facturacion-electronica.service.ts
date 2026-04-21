import { Injectable } from '@angular/core';
import { Observable, throwError } from 'rxjs';
import { ApiService } from '@services/api.service';
import { ElSalvadorFacturacionElectronicaService } from './el-salvador-facturacion-electronica.service';
import { FE_PAIS_CR, FE_PAIS_SV, resolveCodigoPaisFe } from './fe-pais.util';
import { CostaRicaFacturacionElectronicaService } from './costa-rica-facturacion-electronica.service';

const MSG_PAIS_NO_SOPORTADO =
  'La facturación electrónica para este país aún no está disponible en el sistema.';

/**
 * Orquestador FE por país: El Salvador (MH) y Costa Rica (DGT / emitirFeCrVenta).
 */
@Injectable({ providedIn: 'root' })
export class FacturacionElectronicaService {
  constructor(
    private readonly api: ApiService,
    private readonly elSalvador: ElSalvadorFacturacionElectronicaService,
    private readonly costaRica: CostaRicaFacturacionElectronicaService
  ) {}

  private codigoPaisActual(): string {
    return resolveCodigoPaisFe(this.api.auth_user()?.empresa);
  }

  private esElSalvador(): boolean {
    return this.codigoPaisActual() === FE_PAIS_SV;
  }

  private esCostaRica(): boolean {
    return this.codigoPaisActual() === FE_PAIS_CR;
  }

  /** País FE = Costa Rica (plantillas y menús). */
  isCostaRicaFe(): boolean {
    return this.esCostaRica();
  }

  private rejectNoSoportado(): Promise<any> {
    return Promise.reject(new Error(MSG_PAIS_NO_SOPORTADO)) as Promise<any>;
  }

  private throwNoSoportadoObs(): Observable<any> {
    return throwError(() => new Error(MSG_PAIS_NO_SOPORTADO)) as Observable<any>;
  }

  emitirDTE(venta: any): Promise<any> {
    if (this.esCostaRica()) {
      return this.costaRica.emitirComprobanteVenta(venta);
    }
    return this.esElSalvador()
      ? this.elSalvador.emitirDTE(venta)
      : this.rejectNoSoportado();
  }

  /**
   * En listados de devoluciones el parámetro suele ser el objeto devolución (id = devolución).
   */
  emitirDTENotaCredito(devolucionOventa: any): Promise<any> {
    if (this.esCostaRica()) {
      return this.costaRica.emitirNotaCreditoDevolucion(devolucionOventa);
    }
    return this.esElSalvador()
      ? this.elSalvador.emitirDTENotaCredito(devolucionOventa)
      : this.rejectNoSoportado();
  }

  /** El Salvador requiere firmar y enviar DTE después de generar; CR envía en un solo paso. */
  requiereFlujoEnviarDteSeparado(): boolean {
    return this.esElSalvador();
  }

  emitirDTESujetoExcluidoGasto(gasto: any): Promise<any> {
    if (this.esCostaRica()) {
      return this.costaRica.emitirFacturaElectronicaGasto(gasto);
    }
    return this.esElSalvador()
      ? this.elSalvador.emitirDTESujetoExcluidoGasto(gasto)
      : this.rejectNoSoportado();
  }

  emitirDTESujetoExcluidoCompra(compra: any): Promise<any> {
    if (this.esCostaRica()) {
      return this.costaRica.emitirFacturaElectronicaCompra(compra);
    }
    return this.esElSalvador()
      ? this.elSalvador.emitirDTESujetoExcluidoCompra(compra)
      : this.rejectNoSoportado();
  }

  emitirDTEContingencia(venta: any): Promise<any> {
    return this.esElSalvador()
      ? this.elSalvador.emitirDTEContingencia(venta)
      : this.rejectNoSoportado();
  }

  firmarDTE(dte: any): Observable<any> {
    return this.esElSalvador() ? this.elSalvador.firmarDTE(dte) : this.throwNoSoportadoObs();
  }

  enviarDTE(doc: any, dteFirmado: any): Observable<any> {
    if (this.esCostaRica()) {
      return this.api.store('enviarDTE', doc);
    }
    return this.esElSalvador()
      ? this.elSalvador.enviarDTE(doc, dteFirmado)
      : this.throwNoSoportadoObs();
  }

  enviarContingenciaDTEs(venta: any, dteFirmado: any): Observable<any> {
    return this.esElSalvador()
      ? this.elSalvador.enviarContingenciaDTEs(venta, dteFirmado)
      : this.throwNoSoportadoObs();
  }

  anularDTE(doc: any, dteFirmado: any): Observable<any> {
    return this.esElSalvador()
      ? this.elSalvador.anularDTE(doc, dteFirmado)
      : this.throwNoSoportadoObs();
  }

  consultarDTE(venta: any): Observable<any> {
    return this.esElSalvador()
      ? this.elSalvador.consultarDTE(venta)
      : this.throwNoSoportadoObs();
  }

  /** Consulta estado del comprobante en Hacienda CR y actualiza la venta en servidor. */
  consultarEstadoFeCrVenta(ventaId: number): Observable<any> {
    return this.esCostaRica()
      ? this.costaRica.consultarEstadoVenta(ventaId)
      : this.throwNoSoportadoObs();
  }

  consultarEstadoFeCrDevolucion(devolucionId: number): Observable<any> {
    return this.esCostaRica()
      ? this.costaRica.consultarEstadoDevolucion(devolucionId)
      : this.throwNoSoportadoObs();
  }

  consultarEstadoFeCrCompra(compraId: number): Observable<any> {
    return this.esCostaRica()
      ? this.costaRica.consultarEstadoCompra(compraId)
      : this.throwNoSoportadoObs();
  }

  consultarEstadoFeCrGasto(gastoId: number): Observable<any> {
    return this.esCostaRica()
      ? this.costaRica.consultarEstadoGasto(gastoId)
      : this.throwNoSoportadoObs();
  }

  /** Consulta estado de la ND (02) asociada a la venta (dte.cr.nota_debito). */
  consultarEstadoFeCrNotaDebitoVenta(ventaId: number): Observable<any> {
    return this.esCostaRica()
      ? this.costaRica.consultarEstadoNotaDebitoVenta(ventaId)
      : this.throwNoSoportadoObs();
  }

  emitirNotaDebitoVenta(venta: any, motivo: string, montoLinea: number): Promise<any> {
    return this.esCostaRica()
      ? this.costaRica.emitirNotaDebitoVenta(venta, motivo, montoLinea)
      : this.rejectNoSoportado();
  }
}
