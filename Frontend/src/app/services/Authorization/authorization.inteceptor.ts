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
    console.log('=== INTERCEPTOR DEBUG ===');
    console.log('errorData:', errorData);
    console.log('originalRequest.body:', originalRequest.body);
    
    const modalRef = this.modalService.show(AuthorizationRequestModalComponent, {
      initialState: {
        show: true,
        authorizationType: errorData.authorization_type,
        modelType: 'App\\Models\\Compras\\Compra', 
        modelId: null,
        data: {
          compra_data: originalRequest.body, // ← DATOS COMPLETOS DE LA COMPRA
          total: originalRequest.body?.total || originalRequest.body?.sub_total || 0,
          id_proveedor: originalRequest.body?.id_proveedor,
          detalles_count: originalRequest.body?.detalles?.length || 0
        }
      },
      class: 'modal-lg'
    });
  
    modalRef.content?.close.subscribe(() => {
      modalRef.hide();
    });
  }

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