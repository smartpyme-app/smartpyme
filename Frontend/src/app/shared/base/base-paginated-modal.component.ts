import { Directive, TemplateRef } from '@angular/core';
import { BasePaginatedComponent, PaginatedResponse } from './base-paginated.component';
import { ModalManagerService } from '../../services/modal-manager.service';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

// Re-exportar PaginatedResponse para conveniencia
export type { PaginatedResponse };

/**
 * Clase base que combina funcionalidad de paginación y modales.
 * Para componentes que necesitan ambas funcionalidades.
 * 
 * Uso:
 * ```typescript
 * export class MiComponente extends BasePaginatedModalComponent {
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
@Directive()
export abstract class BasePaginatedModalComponent extends BasePaginatedComponent {
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
    this.modalRef = this.modalManager.openModal(template, { size: 'sm', setAlertModal: false });
  }

  /**
   * Método helper para modales grandes
   * No delegar en this.openModal(): las subclases que lo sobrescriben causarían recursión.
   */
  openLargeModal(template: TemplateRef<any>, config?: any): void {
    this.modalRef = this.modalManager.openModal(template, {
      size: 'lg',
      backdrop: 'static',
      ...config
    });
  }
}

