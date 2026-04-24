import { Injectable } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

/**
 * Fachada sobre ngx-bootstrap para abrir modales con plantillas o componentes.
 */
@Injectable({ providedIn: 'root' })
export class ModalManagerService {
  constructor(private readonly bsModal: BsModalService) {}

  show(content: any, config?: any): BsModalRef {
    return this.bsModal.show(content, config);
  }
}
