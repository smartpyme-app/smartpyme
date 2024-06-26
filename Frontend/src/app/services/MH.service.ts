import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpResponse, HttpErrorResponse } from '@angular/common/http';
import { map, catchError, retry } from 'rxjs/operators';
import { Observable, throwError } from 'rxjs';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Injectable()

export class MHService {

    public baseUrl: string = 'https://apitest.dtes.mh.gob.sv';
    public url_firmado: string = 'https://firmador.smartpyme.site:8443/firmardocumento/';
    public url_recepciondte: string = 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte';
    public url_anular_dte: string = 'https://apitest.dtes.mh.gob.sv/fesv/anulardte';

    constructor(private http: HttpClient, private alertService: AlertService, private apiService: ApiService) { }
    
    auth(): Observable<any> {
        let user = JSON.parse(localStorage.getItem('SP_auth_user')!);
        let formData:FormData = new FormData();

        formData.append('user', user.empresa.mh_usuario.replace(/-/g, ''));
        formData.append('pwd', user.empresa.mh_contrasena);

        return this.http.post<any>(`${this.baseUrl}/seguridad/auth`, formData);
    }

    login(){
        this.auth().subscribe( data => {
            localStorage.setItem('SP_token_mh', JSON.stringify(data.body))
        }, error =>{
            this.alertService.error(error);
        });
    }

    firmarDTE(DTE: any): Observable<any> {
        let user = JSON.parse(localStorage.getItem('SP_auth_user')!);

        let formData:any = {};
        formData.nit = user.empresa.nit.replace(/-/g, '');
        formData.activo = true;
        formData.passwordPri = user.empresa.mh_pwd_certificado;
        formData.dteJson = DTE;

        return this.http.post<any>(`${this.url_firmado}`, formData, { params: { saltarJWT: true } });
    }

    enviarDTE(venta: any, dteFirmado: any): Observable<any> {
        let token = JSON.parse(localStorage.getItem('SP_token_mh')!);

        const headers = new HttpHeaders({
          'Content-Type': 'application/json',
          'User-Agent': 'Angular',
          'Authorization': token.token,
        });

        console.log(venta);
        console.log(dteFirmado);

        let formData:any = {};
        formData.ambiente = venta.dte.identificacion.ambiente;
        formData.idEnvio = venta.id;
        formData.version = venta.dte.identificacion.version;
        formData.tipoDte = venta.dte.identificacion.tipoDte;
        formData.documento = dteFirmado;
        formData.codigoGeneracion = venta.dte.codigoGeneracion;
        console.log(formData);

        return this.http.post<any>(`${this.url_recepciondte}`, formData, { headers, params: { saltarJWT: true } });
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

        return this.http.post<any>(`${this.url_anular_dte}`, formData, { headers, params: { saltarJWT: true } });
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
                            // venta.estado = 'Emitido';
                            this.apiService.store('venta', venta).subscribe(data => {
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

                },error => {reject('No se pudo firmar el DTE');});

            },error => {reject('No se pudo generar el DTE');});
        });
    }


}
