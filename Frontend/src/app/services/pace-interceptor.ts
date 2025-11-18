import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent } from '@angular/common/http';
import { Observable } from 'rxjs';
import { tap, finalize, catchError } from 'rxjs/operators';

declare var Pace: any;

@Injectable()
export class PaceInterceptor implements HttpInterceptor {
  private activeRequests = 0;
  private paceTimeout: any = null;

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    // Ignorar ciertas URLs que no necesitan mostrar la barra de progreso
    const ignoreUrls = [
      '/api/notificaciones',
      '/api/chat',
      '/api/ping',
      '/api/heartbeat'
    ];

    const shouldIgnore = ignoreUrls.some(url => req.url.includes(url));
    
    if (!shouldIgnore && typeof Pace !== 'undefined') {
      this.activeRequests++;
      
      // Si es la primera petición, iniciar Pace
      if (this.activeRequests === 1) {
        if (Pace && typeof Pace.restart === 'function') {
          Pace.restart();
        }
      }

      // Timeout de seguridad: si después de 30 segundos Pace sigue activo, forzarlo a completar
      if (this.paceTimeout) {
        clearTimeout(this.paceTimeout);
      }
      
      this.paceTimeout = setTimeout(() => {
        if (typeof Pace !== 'undefined' && Pace.running) {
          console.warn('Pace.js timeout: forzando finalización después de 30 segundos');
          if (typeof Pace.stop === 'function') {
            Pace.stop();
          }
          this.activeRequests = 0;
        }
      }, 30000);
    }

    return next.handle(req).pipe(
      tap({
        error: (error) => {
          // En caso de error, también debemos finalizar Pace
          if (!shouldIgnore && typeof Pace !== 'undefined') {
            this.decrementRequests();
          }
        }
      }),
      finalize(() => {
        if (!shouldIgnore && typeof Pace !== 'undefined') {
          this.decrementRequests();
        }
      })
    );
  }

  private decrementRequests() {
    this.activeRequests--;
    
    if (this.activeRequests <= 0) {
      this.activeRequests = 0;
      
      // Limpiar timeout
      if (this.paceTimeout) {
        clearTimeout(this.paceTimeout);
        this.paceTimeout = null;
      }

      // Pequeño delay para asegurar que todas las peticiones se completaron
      setTimeout(() => {
        if (this.activeRequests === 0 && typeof Pace !== 'undefined' && Pace.running) {
          // Forzar que Pace complete si aún está corriendo
          if (typeof Pace.stop === 'function') {
            Pace.stop();
          }
        }
      }, 100);
    }
  }
}

