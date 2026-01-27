import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpResponse, HttpErrorResponse } from '@angular/common/http';
import { map, catchError, retry } from 'rxjs/operators';
import { Observable, throwError } from 'rxjs';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica.service';

/**
 * @deprecated Este servicio está deprecated. Usar FacturacionElectronicaService en su lugar.
 * Este servicio se mantiene solo para compatibilidad hacia atrás.
 * 
 * Plan de eliminación:
 * - Fase 1 (Actual): Servicio redirige a FacturacionElectronicaService con logging
 * - Fase 2 (Próxima versión): Advertencias en consola
 * - Fase 3 (Versión futura): Eliminación completa
 */
@Injectable()

export class MHService {

    public url_firmado: string = 'https://facturadtesv.com:8443/firmardocumento/';
    public url_recepciondte: string = '/fesv/recepciondte';
    public url_consultadte: string = 'fesv/recepcion/consultadte';
    public url_anular_dte: string = '/fesv/anulardte';
    public url_contingencia: string = '/fesv/contingencia';
    
    public url_pruebas_estadisticas: string = 'mh/pruebas-masivas/estadisticas';
    public url_pruebas_documentos: string = 'mh/pruebas-masivas/documentos-base';
    public url_pruebas_ejecutar: string = 'mh/pruebas-masivas/ejecutar';
    public url_pruebas_limpiar: string = 'mh/pruebas-masivas/limpiar';


    private feService: FacturacionElectronicaService;

    constructor(
        private http: HttpClient, 
        private alertService: AlertService, 
        private apiService: ApiService,
        feService: FacturacionElectronicaService
    ) {
        this.feService = feService;
        console.warn('MHService está deprecated. Por favor, migre a FacturacionElectronicaService.');
    }
    
    /**
     * @deprecated Usar FacturacionElectronicaService.auth() en su lugar
     */
    auth(): Observable<any> {
        console.warn('MHService.auth() está deprecated. Usar FacturacionElectronicaService.auth()');
        return this.feService.auth();
    }

    /**
     * @deprecated Usar FacturacionElectronicaService.login() en su lugar
     */
    login(){
        console.warn('MHService.login() está deprecated. Usar FacturacionElectronicaService.login()');
        // Mantener compatibilidad: guardar también en SP_token_mh
        this.feService.auth().subscribe({
            next: (data) => {
                if (data.body) {
                    localStorage.setItem('SP_token_mh', JSON.stringify(data.body));
                    localStorage.setItem('SP_token_fe', JSON.stringify(data.body));
                }
            },
            error: (error) => {
                this.alertService.error(error);
            }
        });
    }

    /**
     * @deprecated Usar FacturacionElectronicaService.firmarDTE() en su lugar
     */
    firmarDTE(DTE: any): Observable<any> {
        console.warn('MHService.firmarDTE() está deprecated. Usar FacturacionElectronicaService.firmarDTE()');
        return this.feService.firmarDTE(DTE);
    }

    /**
     * @deprecated Usar FacturacionElectronicaService.enviarDTE() en su lugar
     */
    enviarDTE(venta: any, dteFirmado: any): Observable<any> {
        console.warn('MHService.enviarDTE() está deprecated. Usar FacturacionElectronicaService.enviarDTE()');
        // Mantener compatibilidad: intentar obtener token de SP_token_mh si no existe en SP_token_fe
        const tokenMH = JSON.parse(localStorage.getItem('SP_token_mh') || 'null');
        if (tokenMH && !localStorage.getItem('SP_token_fe')) {
            localStorage.setItem('SP_token_fe', JSON.stringify(tokenMH));
        }
        return this.feService.enviarDTE(venta, dteFirmado);
    }

    /**
     * @deprecated Usar FacturacionElectronicaService.consultarDTE() en su lugar
     */
    consultarDTE(venta: any): Observable<any> {
        console.warn('MHService.consultarDTE() está deprecated. Usar FacturacionElectronicaService.consultarDTE()');
        return this.feService.consultarDTE(venta);
    }

    enviarContingenciaDTEs(venta: any, dteFirmado: any): Observable<any> {
        let token = JSON.parse(localStorage.getItem('SP_token_mh')!);

        const headers = new HttpHeaders({
          'Content-Type': 'application/json',
          // 'User-Agent': 'SCRivera',
          'Authorization': token.token,
        });

        let formData:any = {};
        formData.nit = venta.dte.emisor.nit;
        formData.documento = dteFirmado;
        console.log(formData);

        return this.http.post<any>(`${localStorage.getItem('SP_mh_url_base') + this.url_contingencia}`, formData, { headers, params: { saltarJWT: true } });
    }

    /**
     * @deprecated Usar FacturacionElectronicaService.anularDTE() en su lugar
     */
    anularDTE(venta: any, dteFirmado: any): Observable<any> {
        console.warn('MHService.anularDTE() está deprecated. Usar FacturacionElectronicaService.anularDTE()');
        return this.feService.anularDTE(venta, dteFirmado);
    }


    verificarFirmador(): Observable<any> {
        return this.http.get<any>(`${this.url_firmado}status`, { observe: 'response' });
    }

    /**
     * @deprecated Usar FacturacionElectronicaService.emitirDTE() en su lugar
     */
    emitirDTE(venta:any): Promise<any> {
        console.warn('MHService.emitirDTE() está deprecated. Usar FacturacionElectronicaService.emitirDTE()');
        return this.feService.emitirDTE(venta, 'generarDTE', 'venta');
    }

    /**
     * @deprecated Usar FacturacionElectronicaService.emitirDTENotaCredito() en su lugar
     */
    emitirDTENotaCredito(venta:any): Promise<any> {
        console.warn('MHService.emitirDTENotaCredito() está deprecated. Usar FacturacionElectronicaService.emitirDTENotaCredito()');
        return this.feService.emitirDTENotaCredito(venta);
    }

    emitirDTESujetoExcluidoGasto(gasto:any): Promise<any> {

        return new Promise((resolve, reject) => {
            this.apiService.store('generarDTESujetoExcluidoGasto', gasto).subscribe(dte => {
                gasto.dte = dte;
                
                this.firmarDTE(dte).subscribe(dteFirmado => {

                    if(dteFirmado.status == 'ERROR'){
                        reject(dteFirmado.body.mensaje);
                        // reject('No se pudo firmar el DTE, no se encontró el certificado.');
                    }

                    gasto.dte.firmaElectronica = dteFirmado.body;
                    
                    this.enviarDTE(gasto, dteFirmado.body).subscribe(dte => {
                        if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                            gasto.dte.sello = dte.selloRecibido;
                            gasto.dte.selloRecibido = dte.selloRecibido;
                            gasto.sello_mh = dte.selloRecibido;
                            gasto.tipo_dte = dte.tipo_dte;
                            gasto.numero_control = dte.numero_control;
                            // gasto.estado = 'Emitido';
                            this.apiService.store('gasto', gasto).subscribe(data => {
                                resolve(data);
                            },error => {this.alertService.error(error);});
                        }
                    },error => {
                        if(error.error && error.error.observaciones.length > 0){
                            reject(error.error.observaciones);
                        }
                        else if(error.error && error.error.descripcionMsg){
                            reject(error.error.descripcionMsg);
                        }else{
                            reject(error);
                        }
                    });

                },error => {reject(error);});

            },error => {reject(error);});
        });
    }

    emitirDTESujetoExcluidoCompra(compra:any): Promise<any> {

        return new Promise((resolve, reject) => {
            this.apiService.store('generarDTESujetoExcluidoCompra', compra).subscribe(dte => {
                compra.dte = dte;
                
                this.firmarDTE(dte).subscribe(dteFirmado => {

                    if(dteFirmado.status == 'ERROR'){
                        reject(dteFirmado.body.mensaje);
                        // reject('No se pudo firmar el DTE, no se encontró el certificado.');
                    }

                    compra.dte.firmaElectronica = dteFirmado.body;
                    
                    this.enviarDTE(compra, dteFirmado.body).subscribe(dte => {
                        if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                            compra.dte.sello = dte.selloRecibido;
                            compra.dte.selloRecibido = dte.selloRecibido;
                            compra.sello_mh = dte.selloRecibido;
                            compra.tipo_dte = dte.tipo_dte;
                            compra.numero_control = dte.numero_control;
                            // compra.estado = 'Emitido';
                            this.apiService.store('compra', compra).subscribe(data => {
                                resolve(data);
                            },error => {this.alertService.error(error);});
                        }
                    },error => {
                        if(error.error && error.error.observaciones.length > 0){
                            reject(error.error.observaciones);
                        }
                        else if(error.error && error.error.descripcionMsg){
                            reject(error.error.descripcionMsg);
                        }else{
                            reject(error);
                        }
                    });

                },error => {reject(error);});

            },error => {reject(error);});
        });
    }

    emitirDTEContingencia(venta:any): Promise<any> {

        return new Promise((resolve, reject) => {
            this.apiService.store('generarContingencia', venta).subscribe(dte => {
                venta.dte = dte;
                
                this.firmarDTE(dte).subscribe(dteFirmado => {

                    if(dteFirmado.status == 'ERROR'){
                        reject(dteFirmado.body.mensaje);
                        // reject('No se pudo firmar el DTE, no se encontró el certificado.');
                    }

                    venta.dte.firmaElectronica = dteFirmado.body;
                    
                    this.enviarContingenciaDTEs(venta, dteFirmado.body).subscribe(dte => {

                        if ((dte.estado == 'RECIBIDO') && dte.selloRecibido) {
                            venta.dte.sello = dte.selloRecibido;
                            venta.dte.selloRecibido = dte.selloRecibido;
                            venta.sello_mh = dte.selloRecibido;
                            this.apiService.store('venta', venta).subscribe(data => {
                                resolve(data);
                            },error => {this.alertService.error(error); });
                        }
                        if ((dte.estado == 'RECHAZADO')) {
                            reject(dte.observaciones);
                        }

                    },error => {
                        if(error.error && error.error.observaciones.length > 0){
                            reject(error.error.observaciones);
                        }
                        else if(error.error && error.error.descripcionMsg){
                            reject(error.error.descripcionMsg);
                        }else{
                            reject(error);
                        }
                    });

                },error => {reject(error);});

            },error => {reject(error);});
        });
    }

    obtenerEstadisticasPruebasMasivas(): Observable<any> {
        return this.apiService.getAll(this.url_pruebas_estadisticas);
    }
    
    obtenerDocumentosBase(): Observable<any> {
        return this.apiService.getAll(this.url_pruebas_documentos);
    }

    ejecutarPruebasMasivas(tipo: string, cantidad: number, idDocumentoBase?: number, correlativoInicial?: number): Observable<any> {
        const datos = {
            tipo: tipo,
            cantidad: cantidad,
            id_documento_base: idDocumentoBase,
            correlativo_inicial: correlativoInicial
        };
        
        return this.apiService.store(this.url_pruebas_ejecutar, datos)
            .pipe(
                map(response => {
                    // NUEVO: Mensaje personalizado para CCF con notas automáticas
                    if (response.success && tipo === 'creditosFiscales') {
                        response.message += ' Además, se generarán automáticamente las notas de crédito y débito correspondientes.';
                    }
                    return response;
                }),
                catchError(error => {
                    console.error('Error al ejecutar pruebas masivas:', error);
                    
                    if (error.error && error.error.message) {
                        return throwError(error.error.message);
                    }
                    
                    return throwError('Error al ejecutar pruebas masivas. Por favor, intente nuevamente.');
                })
            );
    }
    
}
