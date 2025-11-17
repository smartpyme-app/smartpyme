import { TemplateRef } from '@angular/core';
import { BaseFilteredPaginatedComponent } from './base-filtered-paginated.component';
import { ModalManagerService } from '../../services/modal-manager.service';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

/**
 * Clase base que combina funcionalidad de filtrado/paginación y modales.
 * Para componentes que necesitan ambas funcionalidades.
 *
 * Uso:
 * ```typescript
 * export class MiComponente extends BaseFilteredPaginatedModalComponent {
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
export abstract class BaseFilteredPaginatedModalComponent extends BaseFilteredPaginatedComponent {
  public modalRef?: any; // BsModalRef
  public saving: boolean = false;

  constructor(
    protected override apiService: ApiService,
    protected override alertService: AlertService,
    protected modalManager: ModalManagerService
  ) {
    super(apiService, alertService);
  }

  /**
   * Abre un modal con configuración por defecto
   */
  openModal(template: TemplateRef<any>, config?: any): void {
    this.modalRef = this.modalManager.openModal(template, config);
  }

  /**
   * Cierra el modal actual
   */
  closeModal(): void {
    this.modalManager.closeModal(this.modalRef);
    this.modalRef = undefined;
  }

  /**
   * Método helper para abrir modal de edición/creación
   */
  openEditModal<T>(template: TemplateRef<any>, item?: T, targetProperty: string = 'item'): void {
    if (item) {
      (this as any)[targetProperty] = { ...item };
    } else {
      (this as any)[targetProperty] = {};
    }
    this.openModal(template);
  }

  /**
   * Método helper para modales de confirmación
   */
  openConfirmModal(template: TemplateRef<any>): void {
    this.openModal(template, { size: 'sm', setAlertModal: false });
  }

  /**
   * Método helper para modales grandes
   */
  openLargeModal(template: TemplateRef<any>, config?: any): void {
    this.openModal(template, {
      class: 'modal-lg',
      backdrop: 'static',
      ...config
    });
  }
}

