// interceptors/authorization.interceptor.ts
import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { BsModalService } from 'ngx-bootstrap/modal';
import { AuthorizationRequestModalComponent } from '../../shared/authorization/authorization-request/authorization-request-modal.component';

@Injectable()
export class AuthorizationInterceptor implements HttpInterceptor {

  constructor(private modalService: BsModalService) { }

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<any> {
    return next.handle(req).pipe(
      catchError((error: HttpErrorResponse) => {
        // Detectar errores 403 que requieren autorización
        if (error.status === 403 && error.error?.requires_authorization) {
          this.handleAuthorizationRequired(error.error, req);
          return throwError(() => error);
        }
        return throwError(() => error);
      })
    );
  }

  private handleAuthorizationRequired(errorData: any, originalRequest: HttpRequest<any>) {
    const modalRef = this.modalService.show(AuthorizationRequestModalComponent, {
      initialState: {
        show: true,
        authorizationType: errorData.authorization_type,
        modelType: 'App\\Models\\Compras\\Compra', 
        modelId: null,
        data: this.extractRelevantData(originalRequest.body, errorData.authorization_type)
      },
      class: 'modal-lg'
    });
  
    // Escuchar el evento close del componente
    modalRef.content?.close.subscribe(() => {
      modalRef.hide();
    });
  }

  // private handleAuthorizationRequired(errorData: any, originalRequest: HttpRequest<any>) {
  //   // Extraer información del modelo desde la URL y body
  //   const modelInfo = this.extractModelInfo(originalRequest);
    
  //   // Mostrar modal de solicitud de autorización
  //   const modalRef = this.modalService.show(AuthorizationRequestModalComponent, {
  //     initialState: {
  //       show: true,
  //       authorizationType: errorData.authorization_type,
  //       modelType: modelInfo.type,
  //       modelId: modelInfo.id,
  //       data: this.extractRelevantData(originalRequest.body, errorData.authorization_type)
  //     },
  //     class: 'modal-lg'
  //   });

  //   // Manejar cuando se solicita la autorización
  //   modalRef.content?.requested.subscribe((authorization: any) => {
  //     // Aquí puedes mostrar un mensaje o redirigir
  //     console.log('Autorización solicitada:', authorization);
  //   });
  // }

  private extractModelInfo(request: HttpRequest<any>): { type: string, id: number } {
    // Extraer información del modelo de la URL
    const url = request.url;
    
    // Ejemplos de patrones de URL
    if (url.includes('/compras/')) {
      const id = this.extractIdFromUrl(url);
      return { type: 'App\\Models\\Compras\\Compra', id };
    }
    
    if (url.includes('/ventas/')) {
      const id = this.extractIdFromUrl(url);
      return { type: 'App\\Models\\Ventas\\Venta', id };
    }

    // Por defecto, intentar extraer de los datos del request
    return { 
      type: request.body?.model_type || 'Unknown', 
      id: request.body?.model_id || 0 
    };
  }

  private extractIdFromUrl(url: string): number {
    const matches = url.match(/\/(\d+)(?:\/|$)/);
    return matches ? parseInt(matches[1]) : 0;
  }

  private extractRelevantData(body: any, authorizationType: string): any {
    if (!body) return {};

    switch (authorizationType) {
      case 'purchase_orders_high_amount':
        return { 
          amount: body.total || body.amount,
          currency: body.currency || 'USD'
        };
      case 'sales_high_discount':
        return { 
          discount: body.discount_percentage || body.discount,
          original_amount: body.subtotal
        };
      case 'inventory_adjustments_high':
        return { 
          quantity: Math.abs(body.quantity_adjustment || body.quantity),
          product: body.product_name
        };
      default:
        return body;
    }
  }
}