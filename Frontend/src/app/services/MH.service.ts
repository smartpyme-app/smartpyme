import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpResponse, HttpErrorResponse } from '@angular/common/http';
import { map, catchError, retry } from 'rxjs/operators';
import { Observable, throwError } from 'rxjs';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

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


    constructor(private http: HttpClient, private alertService: AlertService, private apiService: ApiService) { }
    
    auth(): Observable<any> {
        let user = JSON.parse(localStorage.getItem('SP_auth_user')!);
        let formData:FormData = new FormData();

        if (!user.empresa?.mh_usuario || !user.empresa?.mh_contrasena) {
            return throwError(() => new Error("Configure el usuario y contraseña para conectarse a la API de hacienda"));
        }

        formData.append('user', user.empresa.mh_usuario.replace(/-/g, ''));
        formData.append('pwd', user.empresa.mh_contrasena);

        return this.http.post<any>(`${localStorage.getItem('SP_mh_url_base')}/seguridad/auth`, formData);
    }

    login(){
        // Nota: Este método se llama desde login() del componente y la suscripción se completa rápidamente
        // No necesita unsubscribe porque el servicio es singleton y la suscripción se completa antes de que el componente se destruya
        this.auth().subscribe({
            next: (data) => {
                localStorage.setItem('SP_token_mh', JSON.stringify(data.body));
            },
            error: (error) => {
                this.alertService.error(error);
            }
        });
    }

    firmarDTE(DTE: any): Observable<any> {
        let user = JSON.parse(localStorage.getItem('SP_auth_user')!);

        if (!user) {
            return throwError(() => new Error('Usuario no autenticado, vuelva a iniciar sesión'));
        }

        if (!user.empresa?.nit) {
            return throwError(() => new Error('NIT no configurado en la información de la cuenta'));
        }

        if (!user.empresa.mh_pwd_certificado) {
            return throwError(() => new Error('Contraseña del certificado no configurada en los datos de facturación electrónica'));
        }

        let formData:any = {};
        formData.nit = user.empresa.nit.replace(/-/g, '');
        formData.activo = true;
        formData.passwordPri = user.empresa.mh_pwd_certificado;
        formData.dteJson = DTE;

        return this.http.post<any>(`${this.url_firmado}`, formData, { params: { saltarJWT: true } });
    }

    enviarDTE(venta: any, dteFirmado: any): Observable<any> {
        let token = JSON.parse(localStorage.getItem('SP_token_mh')!);
        if (!token) {
            return throwError(() => new Error('Token de MH no creado'));
        }

        const headers = new HttpHeaders({
          'Content-Type': 'application/json',
          'User-Agent': 'Angular',
          'Authorization': token.token,
        });

        let formData:any = {};
        formData.ambiente = venta.dte.identificacion.ambiente;
        formData.idEnvio = venta.id;
        formData.version = venta.dte.identificacion.version;
        formData.tipoDte = venta.dte.identificacion.tipoDte;
        formData.documento = dteFirmado;
        formData.codigoGeneracion = venta.dte.codigoGeneracion;

        return this.http.post<any>(`${localStorage.getItem('SP_mh_url_base') + this.url_recepciondte}`, formData, { headers, params: { saltarJWT: true } });
    }

    consultarDTE(venta: any): Observable<any> {
        let user = JSON.parse(localStorage.getItem('SP_auth_user')!);
        let token = JSON.parse(localStorage.getItem('SP_token_mh')!);

        const headers = new HttpHeaders({
          'Content-Type': 'application/json',
          'User-Agent': 'Angular',
          'Authorization': token.token,
        });


        let formData:any = {};
        formData.nitEmisor = user.empresa.nit.replace(/-/g, '');
        formData.tdte = venta.dte.identificacion.tipoDte;
        formData.codigoGeneracion = venta.dte.codigo_generacion;

        return this.http.post<any>(`${localStorage.getItem('SP_mh_url_base') + this.url_consultadte}`, formData, { headers, params: { saltarJWT: true } });
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

    anularDTE(venta: any, dteFirmado: any): Observable<any> {
        let token = JSON.parse(localStorage.getItem('SP_token_mh')!);

        const headers = new HttpHeaders({
          'Content-Type': 'application/json',
          'User-Agent': 'Angular',
          'Authorization': token.token,
        });

        let formData:any = {};
        formData.ambiente = venta.dte_invalidacion.identificacion.ambiente;
        formData.idEnvio = venta.id;
        formData.version = venta.dte_invalidacion.identificacion.version;
        formData.documento = dteFirmado;

        return this.http.post<any>(`${localStorage.getItem('SP_mh_url_base') + this.url_anular_dte}`, formData, { headers, params: { saltarJWT: true } });
    }


    verificarFirmador(): Observable<any> {
        return this.http.get<any>(`${this.url_firmado}status`, { observe: 'response' });
    }

    emitirDTE(venta:any): Promise<any> {

        return new Promise((resolve, reject) => {
            this.apiService.store('generarDTE', venta).subscribe(dte => {
                venta.dte = dte;
                
                this.firmarDTE(dte).subscribe(dteFirmado => {

                    if(dteFirmado.status == 'ERROR'){
                        reject(dteFirmado.body.mensaje);
                        // reject('No se pudo firmar el DTE, no se encontró el certificado.');
                    }

                    venta.dte.firmaElectronica = dteFirmado.body;
                    
                    this.enviarDTE(venta, dteFirmado.body).subscribe(dte => {
                        if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                            venta.dte.sello = dte.selloRecibido;
                            venta.dte.selloRecibido = dte.selloRecibido;
                            venta.sello_mh = dte.selloRecibido;
                            venta.tipo_dte = dte.tipoDte;
                            venta.numero_control = dte.numeroControl;
                            venta.codigo_generacion = dte.codigoGeneracion;
                            // venta.estado = 'Emitido';
                            this.apiService.store('venta', venta).subscribe(data => {
                                resolve(data);
                            },error => {this.alertService.error(error);});
                        }
                    },error => {
                        if(error.error && error.error.observaciones && error.error.observaciones.length > 0){
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

    emitirDTENotaCredito(venta:any): Promise<any> {

        return new Promise((resolve, reject) => {
            this.apiService.store('generarDTENotaCredito', venta).subscribe(dte => {
                venta.dte = dte;
                
                this.firmarDTE(dte).subscribe(dteFirmado => {

                    if(dteFirmado.status == 'ERROR'){
                        reject(dteFirmado.body.mensaje);
                        // reject('No se pudo firmar el DTE, no se encontró el certificado.');
                    }

                    venta.dte.firmaElectronica = dteFirmado.body;
                    
                    this.enviarDTE(venta, dteFirmado.body).subscribe(dte => {
                        if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                            venta.dte.sello = dte.selloRecibido;
                            venta.dte.selloRecibido = dte.selloRecibido;
                            venta.sello_mh = dte.selloRecibido;
                            venta.tipo_dte = dte.tipo_dte;
                            venta.numero_control = dte.numero_control;
                            // venta.estado = 'Emitido';
                            this.apiService.store('devolucion/venta', venta).subscribe(data => {
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
