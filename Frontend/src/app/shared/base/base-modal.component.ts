import { Directive, TemplateRef } from '@angular/core';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { ModalManagerService, ModalConfig } from '../../services/modal-manager.service';
import { AlertService } from '../../services/alert.service';

/**
 * Clase base abstracta para componentes que utilizan modales.
 * Elimina la duplicación masiva del patrón openModal(template, item) en ~276 archivos.
 * 
 * Uso:
 * ```typescript
 * export class MiComponente extends BaseModalComponent {
 *   public item: any = {};
 *   
 *   constructor(
 *     protected override modalManager: ModalManagerService,
 *     protected override alertService: AlertService
 *   ) {
 *     super(modalManager, alertService);
 *   }
 *   
 *   abrirModalEdicion(template: TemplateRef<any>, item?: any) {
 *     this.openEditModal(template, item, 'item');
 *   }
 * }
 * ```
 */
@Directive()
export abstract class BaseModalComponent {
  public modalRef?: BsModalRef;
  public loading: boolean = false;
  public saving: boolean = false;

  constructor(
    protected modalManager: ModalManagerService,
    protected alertService: AlertService
  ) {}

  /**
   * Abre un modal con configuración por defecto
   * @param template - TemplateRef del modal a abrir
   * @param config - Configuración opcional del modal
   */
  openModal(template: TemplateRef<any>, config?: ModalConfig): void {
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
   * Copia el item al objeto del componente antes de abrir el modal
   * @param template - TemplateRef del modal
   * @param item - Item a editar (opcional, si no se proporciona se crea uno nuevo)
   * @param targetProperty - Nombre de la propiedad del componente donde se guardará el item (default: 'item')
   */
  openEditModal<T>(template: TemplateRef<any>, item?: T, targetProperty: string = 'item'): void {
    if (item) {
      // Copiar el item para evitar mutaciones directas
      (this as any)[targetProperty] = { ...item };
    } else {
      // Crear un objeto vacío para nuevo registro
      (this as any)[targetProperty] = {};
    }
    this.openModal(template);
  }

  /**
   * Método helper para modales de confirmación (tamaño pequeño)
   * @param template - TemplateRef del modal
   */
  openConfirmModal(template: TemplateRef<any>): void {
    this.openModal(template, { size: 'sm', setAlertModal: false });
  }

  /**
   * Método helper para modales grandes (edición/creación de registros)
   * @param template - TemplateRef del modal
   * @param config - Configuración adicional opcional
   */
  openLargeModal(template: TemplateRef<any>, config?: ModalConfig): void {
    this.openModal(template, { 
      size: 'lg', 
      backdrop: 'static',
      ...config 
    });
  }

  /**
   * Método helper genérico que replica el patrón común openModal(template, item)
   * @param template - TemplateRef del modal
   * @param item - Item a pasar al modal (opcional)
   * @param targetProperty - Nombre de la propiedad donde guardar el item (default: basado en el nombre del componente)
   */
  openModalWithItem(template: TemplateRef<any>, item?: any, targetProperty?: string): void {
    // Si no se especifica targetProperty, intentar inferirlo del nombre del componente
    if (!targetProperty) {
      const componentName = this.constructor.name.toLowerCase();
      // Remover 'component' del nombre si existe
      targetProperty = componentName.replace('component', '').trim() || 'item';
    }

    if (item) {
      (this as any)[targetProperty] = { ...item };
    } else {
      (this as any)[targetProperty] = {};
    }

    this.openModal(template);
  }
}

