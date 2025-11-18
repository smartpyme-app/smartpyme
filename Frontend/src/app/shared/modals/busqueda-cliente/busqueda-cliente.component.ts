import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-busqueda-cliente',
    templateUrl: './busqueda-cliente.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule]
})
export class BusquedaClienteComponent extends BaseModalComponent implements OnInit {

  public cliente: any = {};
  public override loading = false;
  @Output() clienteSelect = new EventEmitter();

	private destroyRef = inject(DestroyRef);
	private untilDestroyed = subscriptionHelper(this.destroyRef);
	constructor( 
	    private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
	) {
        super(modalManager, alertService);
    }

    ngOnInit() {
    }

	  override openModal(template: TemplateRef<any>) {
        super.openModal(template, { class: 'modal-lg', backdrop: 'static' });
    }


    public submit():void{
        this.loading = true;
        this.apiService.store('cliente', this.cliente)
            .pipe(this.untilDestroyed())
            .subscribe(cliente => { 
            this.clienteSelect.emit({item: cliente});
            this.loading = false;
            this.closeModal()
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
