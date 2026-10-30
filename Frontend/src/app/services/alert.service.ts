// alert-service.ts
import { Injectable, inject, Injector } from '@angular/core';
import { AuthService } from '@services/auth.service';
import { BehaviorSubject, Observable } from 'rxjs';
import { Router, ActivatedRoute } from '@angular/router';
import { pareceErrorHaciendaCr } from './facturacion-electronica/hacienda-cr-error.parser';
import { sanitizeApiErrorMessage } from '@shared/utils/api-error-message.util';

@Injectable({
  providedIn: 'root',
})

export class AlertService {

    public modal:boolean = false;
    private alertSubject = new BehaviorSubject<any>(null);
    private injector = inject(Injector);

    getAlert(): Observable<any> { return this.alertSubject.asObservable(); }

    constructor(private router: Router) {
    }

    success(titulo: any = null, message: any) {
        console.log(message);
        this.alertSubject.next({'tipo': 'alert-success' ,'titulo': titulo, 'mensaje' : message});
    }

    warning(titulo: any = null, message: any) {
        console.log(message);
        let mensaje = this.normalizeUnknownError(message);
        if (typeof mensaje === 'string' && pareceErrorHaciendaCr(mensaje)) {
            const primera = mensaje.split(/\n/).find((l) => l.trim().length > 0)?.trim() ?? mensaje;
            mensaje =
                (primera.length > 220 ? `${primera.slice(0, 220)}…` : primera) +
                ' — Abra «Emitir / ver comprobante» desde Ventas para ver la explicación detallada.';
        }
        this.alertSubject.next({ tipo: 'alert-warning', titulo, mensaje });
    }

    info(titulo: any = null, message: any) {
        // console.log(message);
        this.alertSubject.next({'tipo': 'alert-info' ,'titulo': titulo, 'mensaje' : message});
    }

    error(message: any) {
        console.log(message);

        const status = message?.status ?? message?.code ?? message?.error?.status ?? message?.error?.code;
        const errBody = message?.error ?? message;

        if(status == 0) {
            const mensaje = 'No hay conexión con el servidor. Posibles causas: timeout (la operación tardó demasiado), CORS, o el servidor no está disponible. Intente nuevamente.';
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : mensaje});
        }
        else if(status == 404) {
            const body = this.parseErrorBody(errBody);
            const mensaje = typeof body['error'] === 'string'
                ? body['error']
                : 'El registro no ha sido encontrado';
            const titulo = body['code'] === 'hacienda_contribuyente_not_found' ? 'Hacienda' : 'Lo sentimos';
            const tipo = body['code'] === 'hacienda_contribuyente_not_found' ? 'alert-warning' : 'alert-danger';
            this.alertSubject.next({ tipo, titulo, mensaje });
        }
        else if(status == 403) {
            const mensaje = typeof errBody?.error === 'string' ? errBody.error : this.normalizeUnknownError(message);
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : mensaje});
        }
        else if(status == 401) {
            const mensaje =
                typeof errBody?.error === 'string'
                    ? errBody.error
                    : typeof errBody?.message === 'string'
                      ? errBody.message
                      : 'Sesión expirada';
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': 'Lo sentimos', 'mensaje' : mensaje});
            this.injector.get(AuthService).sessionExpired();
        }
        else if(status == 400) {
            const body = this.parseErrorBody(errBody);
            const messageRaw = body['message'];
            const errorRaw = body['error'];
            const tituloRaw = body['titulo'];
            const mensaje =
                (typeof messageRaw === 'string' ? messageRaw : undefined)
                ?? (typeof errorRaw === 'string' ? errorRaw : undefined);
            const titulo = (typeof tituloRaw === 'string' && tituloRaw.trim()) ? tituloRaw : 'Lo sentimos';
            const tipo = tituloRaw ? 'alert-warning' : 'alert-danger';
            this.alertSubject.next({
                tipo,
                titulo,
                mensaje: mensaje ?? 'No se pudo completar la operación.',
            });
        }
        else if(status == 422) {
            const body422 = this.parseErrorBody(errBody);
            const code422 = body422['code'];
            const titulo422 = body422['titulo'];
            const error422 = body422['error'];
            if (code422 === 'detalle_sin_producto') {
                this.alertSubject.next({
                    tipo: 'alert-warning',
                    titulo: (typeof titulo422 === 'string' ? titulo422 : 'Producto no asignado'),
                    mensaje: typeof error422 === 'string' ? error422 : 'Asigne un producto o servicio.',
                });
                return;
            }
            if (errBody && Array.isArray((errBody as any).failures) && (errBody as any).failures.length > 0) {
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
                return;
            }

            const validationLines = this.extractValidationMessages(errBody);
            const errorTitle = validationLines.length ? 'Corrige los siguientes errores' : 'Error de validación';
            const errorMessage = validationLines.length
                ? validationLines.map((line) => `- ${line}`).join('<br>')
                : 'Ocurrió un error de validación. Por favor, verifica los datos ingresados.';

            this.alertSubject.next({
                tipo: 'alert-warning',
                titulo: errorTitle,
                mensaje: errorMessage,
            });
        }
        else if(status == 502 || status == 503) {
            const body = this.parseErrorBody(errBody);
            const mensaje = typeof body['error'] === 'string'
                ? body['error']
                : this.normalizeUnknownError(message);
            const titulo = body['code'] === 'hacienda_html_response' ? 'Hacienda' : 'Lo sentimos';
            this.alertSubject.next({ tipo: 'alert-warning', titulo, mensaje });
        }
        else if(status == 500) {
            const body = this.parseErrorBody(errBody);
            const messageRaw = body['message'];
            const errorRaw = body['error'];
            const tituloRaw = body['titulo'];
            const mensaje = sanitizeApiErrorMessage(
                (typeof messageRaw === 'string' ? messageRaw : undefined)
                ?? (typeof errorRaw === 'string' ? errorRaw : undefined)
                ?? this.normalizeUnknownError(message)
            );
            const titulo = (typeof tituloRaw === 'string' && tituloRaw.trim()) ? tituloRaw : 'Lo sentimos';
            this.alertSubject.next({'tipo': 'alert-danger' ,'titulo': titulo, 'mensaje' : mensaje});
        }
        else {
            const mensaje = sanitizeApiErrorMessage(this.normalizeUnknownError(message));
            this.alertSubject.next({'tipo': 'alert-warning' ,'titulo': 'Lo sentimos', 'mensaje' : mensaje});
        }
    }

    /** Cuerpo de error API (objeto o JSON string, p. ej. respuestas text de storeWithTimeout). */
    private parseErrorBody(errBody: unknown): Record<string, unknown> {
        if (errBody == null) {
            return {};
        }
        if (typeof errBody === 'string') {
            const trimmed = errBody.trim();
            if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
                try {
                    const parsed = JSON.parse(trimmed);
                    if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
                        return parsed as Record<string, unknown>;
                    }
                } catch {
                    /* plain text */
                }
            }
            return { error: errBody };
        }
        if (typeof errBody === 'object' && !Array.isArray(errBody)) {
            return errBody as Record<string, unknown>;
        }
        return { error: String(errBody) };
    }

    /**
     * Convierte errores HTTP, objetos Laravel u otros valores en texto legible (evita "[object Object]").
     */
    private normalizeUnknownError(message: any): string {
        if (message == null) {
            return 'Ocurrió un error inesperado.';
        }
        if (typeof message === 'string') {
            return sanitizeApiErrorMessage(message);
        }
        if (typeof message === 'number' || typeof message === 'boolean') {
            return String(message);
        }
        if (Array.isArray(message)) {
            return message.map((e) => this.normalizeUnknownError(e)).join('<br>');
        }
        // HttpErrorResponse u objeto con status + error
        if (message.error != null) {
            const bodyRaw = message.error;
            if (typeof bodyRaw === 'string') {
                const parsed = this.parseErrorBody(bodyRaw);
                const parsedError = parsed['error'];
                if (typeof parsedError === 'string') {
                    return parsedError;
                }
                return bodyRaw;
            }
            const body = bodyRaw;
            if (Array.isArray(body)) {
                return body.map((e: any) => this.normalizeUnknownError(e)).join('<br>');
            }
            if (typeof body === 'object') {
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
                        return sanitizeApiErrorMessage(body.error);
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
     * Extrae mensajes legibles de distintos formatos de error 422 del backend.
     */
    private extractValidationMessages(body: any): string[] {
        if (body == null) {
            return [];
        }
        if (typeof body === 'string') {
            return [body];
        }
        if (Array.isArray(body)) {
            return body.map((item) => String(item)).filter(Boolean);
        }
        if (typeof body !== 'object') {
            return [String(body)];
        }

        const lines: string[] = [];

        if (body.errors && typeof body.errors === 'object') {
            Object.keys(body.errors).forEach((key) => {
                const value = (body.errors as any)[key];
                const label = this.translateFieldName(key);
                if (Array.isArray(value)) {
                    value.forEach((err: string) => {
                        if (err) {
                            lines.push(`${label}: ${err}`);
                        }
                    });
                } else if (value) {
                    lines.push(`${label}: ${value}`);
                }
            });
        }

        if (lines.length === 0 && Array.isArray(body.error)) {
            body.error.forEach((err: string) => {
                if (err) {
                    lines.push(String(err));
                }
            });
        } else if (lines.length === 0 && typeof body.error === 'string' && body.error.trim()) {
            lines.push(body.error);
        }

        if (lines.length === 0 && Array.isArray(body.message)) {
            body.message.forEach((err: string) => {
                if (err) {
                    lines.push(String(err));
                }
            });
        } else if (lines.length === 0 && typeof body.message === 'string' && body.message.trim()) {
            const genericMessages = [
                'Error de validación',
                'The given data was invalid.',
                'Los datos proporcionados no son válidos.',
            ];
            if (!genericMessages.includes(body.message)) {
                lines.push(body.message);
            }
        }

        return lines;
    }

    /**
     * Traduce nombres de campos comunes del backend al español
     */
    private translateFieldName(fieldName: string): string {
        const translations: { [key: string]: string } = {
            'id_categoria': 'Categoría',
            'categoria': 'Categoría',
            'id_subcategoria': 'Subcategoría',
            'subcategoria': 'Subcategoría',
            'id_proveedor': 'Proveedor',
            'proveedor': 'Proveedor',
            'id_usuario': 'Usuario',
            'usuario': 'Usuario',
            'id_sucursal': 'Sucursal',
            'sucursal': 'Sucursal',
            'id_empresa': 'Empresa',
            'empresa': 'Empresa',
            'nombre': 'Nombre',
            'codigo': 'Código',
            'barcode': 'Código de barras',
            'precio': 'Precio',
            'costo': 'Costo',
            'costo_promedio': 'Costo promedio',
            'porcentaje_impuesto': 'Impuesto',
            'medida': 'Unidad de medida',
            'marca': 'Marca',
            'descripcion': 'Descripción',
            'monto': 'Monto',
            'total': 'Total',
            'fecha': 'Fecha',
            'detalle': 'Detalle',
            'detalles': 'Productos / líneas',
            'forma_pago': 'Forma de pago',
            'estado': 'Estado',
        };

        return translations[fieldName] || fieldName;
    }

}
