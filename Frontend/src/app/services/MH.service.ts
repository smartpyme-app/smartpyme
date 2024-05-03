import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpResponse, HttpErrorResponse } from '@angular/common/http';
import { map, catchError, retry } from 'rxjs/operators';
import { Observable, throwError } from 'rxjs';
import { AlertService } from '@services/alert.service';

@Injectable()

export class MHService {

    public baseUrl: string = 'https://apitest.dtes.mh.gob.sv';
    public url_firmado: string = 'http://localhost:8113/firmardocumento/';
    public url_recepciondte: string = 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte';
    public url_anular_dte: string = 'https://apitest.dtes.mh.gob.sv/fesv/anulardte';

    constructor(private http: HttpClient, private alertService: AlertService) { }
    
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


    verificarFirmador(): Observable<any> {
        return this.http.get<any>(`${this.url_firmado}status`, { observe: 'response' });
    }


}
