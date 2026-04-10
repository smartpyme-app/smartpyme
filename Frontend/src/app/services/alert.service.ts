// alert-service.ts
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { Router, ActivatedRoute } from '@angular/router';

@Injectable({
  providedIn: 'root',
})

export class AlertService {

    public modal:boolean = false;
    private alertSubject = new BehaviorSubject<any>(null);

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
            // Manejar diferentes formatos de errores de validación
            let errorMessage = '';
            let errorTitle = 'Error de validación';

            // Formato 1: message.error.error (array o string)
            if(message.error && message.error.error) {
                if(Array.isArray(message.error.error)) {
                    errorMessage = message.error.error.map((err: string) => `- ${err}`).join('<br>');
                    errorTitle = 'Corrige los siguientes errores';
                } else {
                    errorMessage = message.error.error;
                }
            }
            // Formato 2: message.error.message (común en Laravel)
            else if(message.error && message.error.message) {
                if(Array.isArray(message.error.message)) {
                    errorMessage = message.error.message.map((err: string) => `- ${err}`).join('<br>');
                    errorTitle = 'Corrige los siguientes errores';
                } else {
                    errorMessage = message.error.message;
                }
            }
            // Formato 3: message.error.errors (objeto con campos) - MÁS COMÚN EN LARAVEL
            else if(message.error && message.error.errors) {
                const errors = message.error.errors;
                const errorList: string[] = [];
                Object.keys(errors).forEach(key => {
                    if(Array.isArray(errors[key])) {
                        errors[key].forEach((err: string) => {
                            // Traducir el nombre del campo si es necesario
                            const campoTraducido = this.translateFieldName(key);
                            errorList.push(`- ${campoTraducido}: ${err}`);
                        });
                    } else {
                        const campoTraducido = this.translateFieldName(key);
                        errorList.push(`- ${campoTraducido}: ${errors[key]}`);
                    }
                });
                errorMessage = errorList.join('<br>');
                errorTitle = 'Corrige los siguientes errores';
            }
            // Formato 4: message.error directamente como string
            else if(typeof message.error === 'string') {
                errorMessage = message.error;
            }
            // Fallback - mostrar estructura completa para debugging
            else {
                errorMessage = 'Ocurrió un error de validación. Por favor, verifica los datos ingresados.';
            }

            const alertData = {
                'tipo': 'alert-warning',
                'titulo': errorTitle,
                'mensaje': errorMessage
            };

            // Emitir el alert inmediatamente
            this.alertSubject.next(alertData);

        }
        else if(message.status == 500) {
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : message.error.message});
        }
        else {
            this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Lo sentimos', 'mensaje' : message});
        }
    }

    /**
     * Traduce nombres de campos comunes del backend al español
     */
    private translateFieldName(fieldName: string): string {
        const translations: { [key: string]: string } = {
            'id_categoria': 'Categoría',
            'categoria': 'Categoría',
            'id_proveedor': 'Proveedor',
            'proveedor': 'Proveedor',
            'id_usuario': 'Usuario',
            'usuario': 'Usuario',
            'id_sucursal': 'Sucursal',
            'sucursal': 'Sucursal',
            'monto': 'Monto',
            'total': 'Total',
            'fecha': 'Fecha',
            'descripcion': 'Descripción',
            'detalle': 'Detalle',
            'forma_pago': 'Forma de pago',
            'estado': 'Estado'
        };

        return translations[fieldName] || fieldName;
    }

}
