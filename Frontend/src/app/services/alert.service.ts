// alert-service.ts
import { Injectable } from '@angular/core';
import { Subject, Observable } from 'rxjs';
import { Router, ActivatedRoute } from '@angular/router';
import { normalizarErroresHacienda } from '../shared/utils/mh-recepcion-errores';

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

        /** Evita duplicar el toast cuando MHService ya mostró «Debe volver a iniciar sesión». */
        if (
            message != null &&
            typeof message === 'object' &&
            (message as any).mhAlertaSesionMostrada === true
        ) {
            return;
        }

        let msg = message;

        if (Array.isArray(msg)) {
            msg = msg
                .map((m) => (m != null ? String(m) : ''))
                .map((s) => s.trim())
                .filter((s) => s !== '')
                .join('<br>');
        } else if (msg && typeof msg === 'object') {
            const mhLines = normalizarErroresHacienda(msg);
            if (mhLines.length > 0) {
                msg = mhLines.join('<br>');
            } else if (msg.error?.error) {
                msg = msg.error.error;
            } else if (msg.error) {
                msg = msg.error;
            } else if (msg.message) {
                msg = msg.message;
            }
        }

        this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': titulo, 'mensaje' : msg});
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
            const errBody = message.error;
            if (errBody && Array.isArray(errBody.failures) && errBody.failures.length > 0) {
                const byRow: { [row: number]: string[] } = {};
                for (const f of errBody.failures) {
                    const r = Number(f.row);
                    if (!byRow[r]) {
                        byRow[r] = [];
                    }
                    const errs = Array.isArray(f.errors) ? f.errors : [];
                    for (const e of errs) {
                        if (e) {
                            byRow[r].push(String(e));
                        }
                    }
                }
                const rows = Object.keys(byRow).map((k) => Number(k)).sort((a, b) => a - b);
                let alerts = '';
                for (const r of rows) {
                    alerts += `<strong>Fila ${r}</strong> del Excel:<br>`;
                    for (const line of byRow[r]) {
                        alerts += '&nbsp;&nbsp;• ' + line + '<br>';
                    }
                }
                this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Corrige los siguientes errores', 'mensaje' : alerts});
            } else if(errBody && errBody.error) {
                if(Array.isArray(errBody.error)) {
                    let alerts='';
                    for (var i = 0; i < errBody.error.length; ++i) {
                        alerts += '- ' + errBody.error[i] + '<br>';
                    }
                    this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Corrige los siguientes errores', 'mensaje' : alerts});
                } else {
                    // Si es una cadena, mostrarla directamente
                    this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Error de validación', 'mensaje' : errBody.error});
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
