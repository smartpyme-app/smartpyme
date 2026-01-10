import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

export interface DuplicateCheckOptions {
  endpoint: string; // Ej: 'clientes', 'proveedores'
  searchParams: {
    nombre?: string;
    apellido?: string;
    estado?: number;
    [key: string]: any; // Permite otros parámetros de búsqueda
  };
  title?: string; // Título personalizado para la alerta
  message?: string; // Mensaje personalizado para la alerta
  editUrl?: string; // URL base para editar el registro (ej: '/cliente/editar/', '/proveedor/editar/')
  showEditLink?: boolean; // Si se debe mostrar el enlace de edición
  onSuccess?: (duplicate: any) => void; // Callback cuando se encuentra un duplicado
  onError?: (error: any) => void; // Callback para manejar errores
  onComplete?: () => void; // Callback cuando se completa la verificación
}

@Injectable({
  providedIn: 'root',
})
export class DuplicateCheckService {
  constructor(
    private apiService: ApiService,
    private alertService: AlertService
  ) {}

  /**
   * Verifica si existe un registro duplicado basado en los parámetros de búsqueda
   * @param options Opciones de configuración para la verificación
   * @returns Observable que emite true si se encuentra un duplicado, false en caso contrario
   */
  verificarSiExiste(options: DuplicateCheckOptions): Observable<boolean> {
    // Validar que existan los campos mínimos requeridos
    if (!options.searchParams.nombre && !options.searchParams.apellido) {
      return of(false);
    }

    return this.apiService.getAll(options.endpoint, {
      ...options.searchParams,
      estado: options.searchParams.estado ?? 1,
    }).pipe(
      map((response: any) => {
        const duplicates = response?.data || [];
        const hasDuplicate = duplicates.length > 0 && duplicates[0];

        if (hasDuplicate) {
          const duplicate = duplicates[0];
          
          // Construir el mensaje de alerta
          let message = options.message;
          if (!message) {
            message = 'Por favor, verificar. Puedes ignorar esta alerta si consideras que no estas duplicando el registro.';
          }

          // Agregar enlace de edición si está configurado
          if (options.showEditLink !== false && options.editUrl && duplicate.id) {
            const editLink = `<a class="btn btn-link" target="_blank" href="${this.apiService.appUrl}${options.editUrl}${duplicate.id}">Ver ${this.getEntityName(options.endpoint)}</a>`;
            message = `Por favor, verifica su información acá: ${editLink}. <br> ${message}`;
          }

          // Mostrar alerta
          const title = options.title || '🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.';
          this.alertService.warning(title, message);

          // Ejecutar callback si existe
          if (options.onSuccess) {
            options.onSuccess(duplicate);
          }
        }

        // Ejecutar callback de completado
        if (options.onComplete) {
          options.onComplete();
        }

        return hasDuplicate;
      }),
      catchError((error) => {
        this.alertService.error(error);
        
        // Ejecutar callback de error si existe
        if (options.onError) {
          options.onError(error);
        }

        return of(false);
      })
    );
  }

  /**
   * Obtiene el nombre de la entidad basado en el endpoint
   * @param endpoint Endpoint de la API
   * @returns Nombre de la entidad en español
   */
  private getEntityName(endpoint: string): string {
    const entityMap: { [key: string]: string } = {
      'clientes': 'cliente',
      'proveedores': 'proveedor',
    };
    return entityMap[endpoint] || 'registro';
  }
}

