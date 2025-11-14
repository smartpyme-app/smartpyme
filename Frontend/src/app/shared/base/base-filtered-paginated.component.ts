import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

/**
 * Clase base abstracta para componentes que implementan paginación mediante filtros.
 * 
 * Este componente es para casos donde la paginación se maneja actualizando `filtros.page`
 * y luego llamando a un método de filtrado que usa `apiService.getAll()` con los filtros.
 * 
 * Uso:
 * ```typescript
 * export class MiComponente extends BaseFilteredPaginatedComponent {
 *   public datos: any = {};
 *   
 *   protected aplicarFiltros(): void {
 *     this.loading = true;
 *     this.apiService.getAll('endpoint', this.filtros).subscribe(
 *       datos => {
 *         this.datos = datos;
 *         this.loading = false;
 *       },
 *       error => {
 *         this.alertService.error(error);
 *         this.loading = false;
 *       }
 *     );
 *   }
 * }
 * ```
 */
export abstract class BaseFilteredPaginatedComponent {
  public loading: boolean = false;
  public filtros: any = {};

  constructor(
    protected apiService: ApiService,
    protected alertService: AlertService
  ) {}

  /**
   * Método abstracto que debe implementar el componente hijo.
   * Debe contener la lógica de filtrado que usa `this.filtros` para hacer la petición.
   * 
   * Ejemplo:
   * ```typescript
   * protected aplicarFiltros(): void {
   *   this.loading = true;
   *   this.apiService.getAll('endpoint', this.filtros).subscribe(
   *     datos => {
   *       this.datos = datos;
   *       this.loading = false;
   *     },
   *     error => {
   *       this.alertService.error(error);
   *       this.loading = false;
   *     }
   *   );
   * }
   * ```
   */
  protected abstract aplicarFiltros(): void;

  /**
   * Método genérico para manejar la paginación.
   * Actualiza `filtros.page` y llama a `aplicarFiltros()`.
   * 
   * @param event - Evento de paginación que contiene event.page
   */
  public setPagination(event: any): void {
    if (!event || typeof event.page === 'undefined') {
      console.error(`${this.constructor.name}: Evento de paginación inválido:`, event);
      return;
    }

    this.loading = true;
    this.filtros.page = event.page;
    this.aplicarFiltros();
  }

  /**
   * Hook opcional para procesamiento adicional después de aplicar filtros.
   * Puede ser sobrescrito por componentes hijos si necesitan lógica adicional.
   */
  protected onFiltrosAplicados(): void {
    // Implementación por defecto vacía
  }
}

