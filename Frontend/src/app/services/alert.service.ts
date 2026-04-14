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
        const mensaje = this.normalizeUnknownError(message);
        this.alertSubject.next({ tipo: 'alert-warning', titulo, mensaje });
    }

    info(titulo: any = null, message: any) {
        // console.log(message);
        this.alertSubject.next({'tipo': 'alert-info' ,'titulo': titulo, 'mensaje' : message});
    }

    error(message: any) {
        console.log(message);

        // Para errores de validación (422), siempre mostrar el alert incluso si hay modal abierto
        if(message.status == 422) {
            // Forzar modal a false ANTES de procesar el error
            this.modal = false;
        }

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
            const body = message.error ?? {};
            const mensaje = body.message ?? body.error ?? (typeof body === 'string' ? body : undefined);
            const titulo = body.titulo || 'Lo sentimos';
            const tipo = body.titulo ? 'alert-info' : 'alert-danger';
            this.alertSubject.next({
                tipo,
                titulo,
                mensaje: mensaje ?? 'No se pudo completar la operación.',
            });
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

            // Asegurar que el modal no bloquee la visualización del error
            setTimeout(() => {
                this.modal = false;
            }, 0);
        }
        else if(message.status == 500) {
            const mensaje = message.error?.message
                ? message.error.message
                : this.normalizeUnknownError(message);
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : mensaje});
        }
        else {
            const mensaje = this.normalizeUnknownError(message);
            this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Lo sentimos', 'mensaje' : mensaje});
        }
    }

    /**
     * Convierte errores HTTP, objetos Laravel u otros valores en texto legible (evita "[object Object]").
     */
    private normalizeUnknownError(message: any): string {
        if (message == null) {
            return 'Ocurrió un error inesperado.';
        }
        if (typeof message === 'string') {
            return message;
        }
        if (typeof message === 'number' || typeof message === 'boolean') {
            return String(message);
        }
        if (Array.isArray(message)) {
            return message.map((e) => this.normalizeUnknownError(e)).join('<br>');
        }
        // HttpErrorResponse u objeto con status + error
        if (message.error != null && typeof message === 'object') {
            const body = message.error;
            if (typeof body === 'string') {
                return body;
            }
            if (body && typeof body === 'object') {
                if (body.errors && typeof body.errors === 'object') {
                    const parts: string[] = [];
                    Object.keys(body.errors).forEach((key) => {
                        const v = (body.errors as any)[key];
                        const label = this.translateFieldName(key);
                        if (Array.isArray(v)) {
                            v.forEach((err: string) => parts.push(`- ${label}: ${err}`));
                        } else {
                            parts.push(`- ${label}: ${v}`);
                        }
                    });
                    if (parts.length) {
                        return parts.join('<br>');
                    }
                }
                if (body.message && typeof body.message === 'string') {
                    return body.message;
                }
                if (body.error) {
                    if (typeof body.error === 'string') {
                        return body.error;
                    }
                    if (Array.isArray(body.error)) {
                        return body.error.map((e: string) => `- ${e}`).join('<br>');
                    }
                }
                if (body.descripcionMsg) {
                    return String(body.descripcionMsg);
                }
            }
        }
        if (message.message && typeof message.message === 'string') {
            return message.message;
        }
        try {
            return JSON.stringify(message);
        } catch {
            return 'Ocurrió un error inesperado.';
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
            'detalles': 'Productos / líneas',
            'forma_pago': 'Forma de pago',
            'estado': 'Estado'
        };

        return translations[fieldName] || fieldName;
    }

}
