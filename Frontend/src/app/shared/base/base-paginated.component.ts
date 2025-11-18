import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { PaginatedResponse } from '../../models/shared/Pagination.interface';
import { BaseComponent } from './base.component';

// Re-exportar PaginatedResponse para conveniencia (los tipos se eliminan en compilación, sin overhead)
export type { PaginatedResponse };

/**
 * Clase base abstracta para componentes que implementan paginación.
 * Elimina la duplicación de código del método setPagination() en múltiples componentes.
 * 
 * Incluye gestión automática de suscripciones para prevenir memory leaks.
 * 
 * Uso:
 * ```typescript
 * export class MiComponente extends BasePaginatedComponent {
 *   public datos: PaginatedResponse = {} as PaginatedResponse;
 *   
 *   protected getPaginatedData(): PaginatedResponse {
 *     return this.datos;
 *   }
 *   
 *   protected setPaginatedData(data: PaginatedResponse): void {
 *     this.datos = data;
 *   }
 * }
 * ```
 */

export abstract class BasePaginatedComponent extends BaseComponent {
  public loading: boolean = false;
  public filtros: any = {};

  constructor(
    protected apiService: ApiService,
    protected alertService: AlertService
  ) {
    super();
  }

  /**
   * Obtiene la referencia al objeto de datos paginados.
   * Debe tener una propiedad 'path' con la URL de paginación.
   */
  protected abstract getPaginatedData(): PaginatedResponse | null;

  /**
   * Actualiza la propiedad de datos paginados en el componente hijo.
   * CRÍTICO: Este método debe reasignar la propiedad completa.
   */
  protected abstract setPaginatedData(data: PaginatedResponse): void;

  /**
   * Método genérico para manejar la paginación.
   * @param event - Evento de paginación que contiene event.page
   */
  public setPagination(event: any): void {
    if (!event || typeof event.page === 'undefined') {
      console.error('Evento de paginación inválido:', event);
      return;
    }

    this.loading = true;
    
    const paginatedData = this.getPaginatedData();
    
    if (!paginatedData) {
      console.error(`${this.constructor.name}: getPaginatedData() retornó null/undefined`);
      this.loading = false;
      return;
    }
    
    if (!paginatedData.path) {
      console.error(
        `${this.constructor.name}: El objeto de datos paginados no tiene 'path'.`,
        'Estructura:', paginatedData
      );
      this.loading = false;
      return;
    }

    const paginationUrl = `${paginatedData.path}?page=${event.page}`;
    
    this.apiService.paginate(paginationUrl, this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          this.setPaginatedData(response);
          this.onPaginateSuccess(response);
          this.loading = false;
        },
        error: (error) => {
          console.error(`${this.constructor.name}: Error en paginación`, error);
          this.alertService.error(error);
          this.loading = false;
        }
      });
  }

  /**
   * Hook opcional para procesamiento adicional después de paginar.
   */
  protected onPaginateSuccess(response: PaginatedResponse): void {
    // Implementación por defecto vacía
  }
}

