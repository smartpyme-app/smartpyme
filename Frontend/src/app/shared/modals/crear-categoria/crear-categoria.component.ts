import { Component, OnInit, TemplateRef, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-categoria',
    templateUrl: './crear-categoria.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearCategoriaComponent extends BaseModalComponent implements OnInit {
  public categoria: any = {};
  @Output() update = new EventEmitter();

  constructor(
    private apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {}

  override openModal(template: TemplateRef<any>) {
    this.categoria = {};
    this.categoria.enable = true;
    this.categoria.subcategoria = false;
    this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
    super.openModal(template, { class: 'modal-sm', backdrop: 'static' });
  }

  public onSubmit() {
    this.loading = true;

    this.apiService.store('categoria', this.categoria)
      .pipe(this.untilDestroyed())
      .subscribe(
      categoria => {
        this.update.emit(categoria);
        this.closeModal();
        this.loading = false;
        this.alertService.success('Categoria creada', 'La categoria ha sido agregada.');
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }
}
