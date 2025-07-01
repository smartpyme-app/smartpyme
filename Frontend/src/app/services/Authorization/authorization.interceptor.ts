// interceptors/authorization.interceptor.ts
import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { BsModalService } from 'ngx-bootstrap/modal';
import { AuthorizationRequestModalComponent } from '../../shared/authorization/authorization-request/authorization-request-modal.component';

// Interfaces para tipado
interface AuthConfig {
  modelType: string;
  modelId: null;
  dataExtractor: (request: HttpRequest<any>) => any;
  isPrefix?: boolean;
}

interface AuthConfigs {
  [key: string]: AuthConfig;
}

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
    console.log('originalRequest.url:', originalRequest.url);
    console.log('originalRequest.body:', originalRequest.body);
    console.log('originalRequest.method:', originalRequest.method);
    
    const authConfig = this.getAuthorizationConfig(errorData.authorization_type, originalRequest);
    
    console.log('=== AUTH CONFIG RESULTADO ===');
    console.log('authConfig:', authConfig);
    
    const modalRef = this.modalService.show(AuthorizationRequestModalComponent, {
      initialState: {
        show: true,
        authorizationType: errorData.authorization_type,
        modelType: authConfig.modelType,
        modelId: authConfig.modelId,
        data: authConfig.data
      },
      class: 'modal-lg'
    });
    
    modalRef.content?.close.subscribe(() => {
      modalRef.hide();
    });
  }

  private getAuthorizationConfig(authType: string, request: HttpRequest<any>) {
    console.log('=== GET AUTHORIZATION CONFIG ===');
    console.log('🏷️ Auth type:', authType);
    console.log('🌐 Request URL:', request.url);
    
    const configs: AuthConfigs = {
      // Compras
      'compras_altas': {
        modelType: 'App\\Models\\Compras\\Compra',
        modelId: null,
        dataExtractor: this.extractCompraData.bind(this)
      },
      
      // Órdenes de compra (usando prefijo)
      'orden_compra_nivel_': {
        modelType: 'App\\Models\\OrdenCompra', 
        modelId: null,
        dataExtractor: this.extractCompraData.bind(this),
        isPrefix: true
      },
      
      // Ventas (usando prefijo)
      'ventas_': {
        modelType: 'App\\Models\\Ventas\\Venta',
        modelId: null, 
        dataExtractor: this.extractVentaData.bind(this),
        isPrefix: true
      },
      
      // Usuarios (usando prefijo)
      'editar_usuario_': {
        modelType: 'App\\Models\\User',
        modelId: null,
        dataExtractor: this.extractUsuarioData.bind(this),
        isPrefix: true
      },
      
      // Inventario (ejemplo futuro)
      'inventario_': {
        modelType: 'App\\Models\\Inventario\\Producto',
        modelId: null,
        dataExtractor: this.extractInventarioData.bind(this),
        isPrefix: true
      }
    };

    // Buscar configuración exacta primero
    if (configs[authType]) {
      const config = configs[authType];
      const data = config.dataExtractor(request);
      console.log('✅ Configuración exacta encontrada');
      return {
        modelType: config.modelType,
        modelId: config.modelId,
        data: data
      };
    }

    // Buscar por prefijo
    const prefixConfig = Object.keys(configs).find(key => 
      configs[key].isPrefix && authType.startsWith(key)
    );

    if (prefixConfig) {
      const config = configs[prefixConfig];
      const data = config.dataExtractor(request);
      console.log('✅ Configuración por prefijo encontrada:', prefixConfig);
      return {
        modelType: config.modelType,
        modelId: config.modelId,
        data: data
      };
    }

    // Por defecto
    console.log('⚠️ Usando configuración por defecto');
    return {
      modelType: 'App\\Models\\Compras\\Compra',
      modelId: null,
      data: this.extractCompraData(request)
    };
  }

  // Métodos de extracción de datos
  private extractCompraData(request: HttpRequest<any>) {
    return {
      compra_data: request.body,
      total: request.body?.total || request.body?.sub_total || 0,
      id_proveedor: request.body?.id_proveedor,
      detalles_count: request.body?.detalles?.length || 0
    };
  }

  private extractVentaData(request: HttpRequest<any>) {
    return {
      venta_data: request.body,
      total: request.body?.total || request.body?.sub_total || 0,
      id_cliente: request.body?.id_cliente,
      detalles_count: request.body?.detalles?.length || 0
    };
  }

  // 1. INTERCEPTOR - extractUsuarioData mejorado
  private extractUsuarioData(request: HttpRequest<any>) {
    console.log('=== EXTRACT USUARIO DATA DEBUG ===');
    console.log('🌐 URL completa:', request.url);
    console.log('📋 Request body:', request.body);
    console.log('🔧 Request method:', request.method);
    console.log('📝 Is FormData:', request.body instanceof FormData);
    
    // Intentar extraer ID de múltiples fuentes
    let id = this.extractIdFromUrl(request.url);
    
    // Solo extraer campos esenciales
    let essentialData: any = {
      id_usuario: id || 0
    };
    
    // Manejar FormData (que es lo que envía Angular)
    if (request.body instanceof FormData) {
      console.log('🔍 Procesando FormData...');
      
      // Solo extraer campos específicos que necesitamos
      const relevantFields = ['rol_id', 'password', 'codigo_autorizacion', 'name', 'email'];
      
      request.body.forEach((value, key) => {
        console.log(`📦 FormData - ${key}: ${value}`);
        
        // Solo procesar campos relevantes
        if (relevantFields.includes(key)) {
          if (key === 'rol_id') {
            essentialData[key] = value ? parseInt(value as string) : null;
            console.log(`✅ Campo relevante capturado - ${key}: ${essentialData[key]}`);
          } else {
            essentialData[key] = value;
            console.log(`✅ Campo relevante capturado - ${key}: ${value}`);
          }
        }
        
        // Si encontramos el ID en FormData y no lo teníamos
        if (key === 'id' && (!id || id === 0)) {
          id = parseInt(value as string);
          essentialData.id_usuario = id;
        }
      });
      
    } else {
      // Manejar objeto normal - solo extraer campos relevantes
      const relevantFields = ['rol_id', 'password', 'codigo_autorizacion', 'name', 'email'];
      
      relevantFields.forEach(field => {
        if (request.body && request.body[field] !== undefined) {
          essentialData[field] = request.body[field];
        }
      });
      
      // Si no encontramos ID en la URL, buscar en el body
      if (!id || id === 0) {
        id = request.body?.id || 
            request.body?.id_usuario || 
            request.body?.user_id ||
            request.body?.usuario_id;
        essentialData.id_usuario = id || 0;
      }
    }
    
    console.log('🏁 ID final determinado:', essentialData.id_usuario);
    console.log('🔄 rol_id extraído:', essentialData.rol_id);
    console.log('📤 Datos esenciales finales:', essentialData);
    
    return essentialData;
  }

  private extractInventarioData(request: HttpRequest<any>) {
    return {
      id_producto: this.extractIdFromUrl(request.url),
      cantidad: request.body?.cantidad,
      ...request.body
    };
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
    console.log('🔍 Extracting ID from URL:', url);
    
    // Limpiar la URL de query parameters
    const cleanUrl = url.split('?')[0];
    
    // Patrones más específicos para diferentes endpoints
    const patterns = [
      /\/usuario\/password\/(\d+)$/,           // usuario/password/60
      /\/usuario\/(\d+)$/,                     // usuario/60
      /\/usuario\/(\d+)\/\w+$/,                // usuario/60/algo
      /\/usuario\/(\d+)(?:\/.*)?$/,            // usuario/60/cualquier-cosa
      /\/(\d+)(?:\/[^\/]*)?$/,                 // cualquier número al final
      /.*\/(\d+)$/                             // último número en la URL
    ];
    
    for (const pattern of patterns) {
      const matches = cleanUrl.match(pattern);
      if (matches) {
        const id = parseInt(matches[1]);
        console.log('✅ ID extraído exitosamente:', id, 'con patrón:', pattern);
        return id;
      }
    }
    
    console.warn('⚠️ No se pudo extraer ID de la URL:', url);
    return 0;
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