// alert-service.ts
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
        console.log(message);
        this.alertSubject.next({'tipo': 'alert-success' ,'titulo': titulo, 'mensaje' : message});
    }

    warning(titulo: any = null, message: any) {
        console.log(message);

        if (message?.error?.error) {
            message = message.error.error;
        } else if (message?.error) {
            message = message.error;
        } else if (message?.message) {
            message = message.message;
        } else {
            message = message;
        }

        this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': titulo, 'mensaje' : message});
    }

    info(titulo: any = null, message: any) {
        // console.log(message);
        this.alertSubject.next({'tipo': 'alert-info' ,'titulo': titulo, 'mensaje' : message});
    }

    error(message: any) {
        console.log(message);
    
        if(message.status == 0) {
            const mensaje = 'No hay conexión con el servidor. Posibles causas: timeout (la operación tardó demasiado), CORS, o el servidor no está disponible. Intente nuevamente.';
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : mensaje});
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
            const mensaje = message.error?.error ?? message.error?.message ?? (typeof message.error === 'string' ? message.error : 'Solicitud incorrecta');
            this.alertSubject.next({'tipo': 'alert-info' ,'titulo': message.statusText || 'Lo sentimos', 'mensaje' : mensaje});
        }
        else if(message.status == 422) {
            // Verificar si el error es un array o una cadena
            if(message.error && message.error.error) {
                if(Array.isArray(message.error.error)) {
                    let alerts='';
                    for (var i = 0; i < message.error.error.length; ++i) {
                        alerts += '- ' + message.error.error[i] + '<br>';
                    }
                    this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Corrige los siguientes errores', 'mensaje' : alerts});
                } else {
                    // Si es una cadena, mostrarla directamente
                    this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Error de validación', 'mensaje' : message.error.error});
                }
            } else {
                // Fallback por si la estructura es diferente
                this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Error de validación', 'mensaje' : 'Ocurrió un error de validación'});
            }
        }
        else if(message.status == 500) {
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.error.message});
        }
        else {
            this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Lo sentimos', 'mensaje' : message});
        }
    }

    // error(message: any) {
    //     console.log(message);

    //     if(message.status == 0) {
    //         this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : 'No hay conexión con el servidor, intentar nuevamente'});
    //     }
    //     else if(message.status == 404) {
    //         this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : 'El registro no ha sido encontrado'});
    //     }
    //     else if(message.status == 403) {
    //         this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.error.error});
    //     }
    //     else if(message.status == 401) {
    //         this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.error.message});
    //         this.router.navigate(['/login']);
    //     }
    //     else if(message.status == 400) {
    //         this.alertSubject.next({'tipo': 'alert-info' ,'titulo': message.statusText, 'mensaje' : message.error.error});
    //     }
    //     else if(message.status == 422) {
    //         let alerts='';
    //         for (var i = 0; i < message.error.error.length; ++i) {
    //             alerts += '- ' + message.error.error[i] + '<br>';
    //         }
    //         this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Corrige los siguientes errores', 'mensaje' : alerts});
    //     }
    //     else if(message.status == 500) {
    //         this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.error.message});
    //     }
    //     else {
    //         this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Lo sentimos', 'mensaje' : message});
    //     }
        
    // }

}
