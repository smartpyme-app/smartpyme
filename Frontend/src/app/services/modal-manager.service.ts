import { Injectable } from '@angular/core';
import { TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef, ModalOptions } from 'ngx-bootstrap/modal';
import { AlertService } from './alert.service';

/**
 * Configuración para abrir un modal
 */
export interface ModalConfig {
  /** Tamaño del modal: 'sm', 'lg', 'xl' o clase personalizada */
  size?: 'sm' | 'lg' | 'xl' | string;
  /** Si el backdrop debe ser estático (no se cierra al hacer clic fuera) */
  backdrop?: boolean | 'static';
  /** Si se debe establecer alertService.modal = true automáticamente */
  setAlertModal?: boolean;
  /** Clases CSS adicionales */
  class?: string;
  /** Opciones adicionales de ngx-bootstrap */
  [key: string]: any;
}

/**
 * Servicio centralizado para manejar modales en toda la aplicación.
 * Elimina la duplicación de código relacionada con la apertura y cierre de modales.
 */
@Injectable({
  providedIn: 'root'
})
export class ModalManagerService {

  constructor(
    private modalService: BsModalService,
    private alertService: AlertService
  ) {}

  /**
   * Abre contenido/modal mediante ngx-bootstrap (p. ej. listados que extienden `BasePaginatedModalComponent`).
   */
  show(content: any, config?: any): BsModalRef {
    return this.modalService.show(content, config);
  }

  /**
   * Abre un modal con la configuración especificada
   * @param template - TemplateRef del modal a abrir
   * @param config - Configuración opcional del modal
   * @returns BsModalRef - Referencia al modal abierto
   */
  openModal(template: TemplateRef<any>, config?: ModalConfig): BsModalRef {
    const defaultConfig: ModalOptions = {
      class: config?.size ? `modal-${config.size}` : 'modal-md',
      backdrop: config?.backdrop !== undefined ? config.backdrop : true,
      ...config
    };

    // Si se especifica una clase personalizada, usarla en lugar del tamaño por defecto
    if (config?.class) {
      defaultConfig.class = config.class;
    }

    const modalRef = this.modalService.show(template, defaultConfig);

    // Establecer alertService.modal = true por defecto (a menos que se especifique lo contrario)
    if (config?.setAlertModal !== false) {
      this.alertService.modal = true;
    }

    return modalRef;
  }

  /**
   * Cierra un modal específico
   * @param modalRef - Referencia al modal a cerrar
   */
  closeModal(modalRef?: BsModalRef): void {
    if (modalRef) {
      modalRef.hide();
    }
    this.alertService.modal = false;
  }

  /**
   * Cierra todos los modales abiertos
   */
  closeAllModals(): void {
    this.modalService.hide();
    this.alertService.modal = false;
  }
}

