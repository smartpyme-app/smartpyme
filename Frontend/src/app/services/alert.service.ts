import { Injectable } from '@angular/core';
import { Subject, Observable } from 'rxjs';
import { Router, ActivatedRoute } from '@angular/router';

@Injectable({
  providedIn: 'root',
})

export class AlertService {

    public modal:boolean = false;
    private alertSubject = new Subject<any>();

    getAlert(): Observable<any> { return this.alertSubject.asObservable(); }
     
    constructor(private router: Router) {
    }

    success(titulo: any = null, message: any) {
        this.alertSubject.next({'tipo': 'alert-success' ,'titulo': titulo, 'mensaje' : message});
    }

    warning(titulo: any = null, message: any) {
        this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': titulo, 'mensaje' : message});
    }

    info(titulo: any = null, message: any) {
        this.alertSubject.next({'tipo': 'alert-info' ,'titulo': titulo, 'mensaje' : message});
    }

    error(message: any) {
        console.log(message);

        if(message.status == 0) {
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : 'No hay conexión con el servidor, intentar nuevamente'});
        }
        else if(message.status == 404) {
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : 'El registro no ha sido encontrado'});
        }
        else if(message.status == 403) {
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.error.error});
        }
        else if(message.status == 401) {
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.error.message});
            this.router.navigate(['/login']);
        }
        else if(message.status == 400) {
            this.alertSubject.next({'tipo': 'alert-info' ,'titulo': message.error.titulo ? message.error.titulo : 'Lo sentimos', 'mensaje' : message.error.error});
        }
        else if(message.status == 422) {
            let alerts='';
            for (var i = 0; i < message.error.error.length; ++i) {
                alerts += '- ' + message.error.error[i] + '<br>';
            }
            this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Corrige los siguientes errores', 'mensaje' : alerts});
        }
        else if(message.status == 500) {
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.statusText});
        }
        else {
            this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Lo sentimos', 'mensaje' : message});
        }
        
    }

}
