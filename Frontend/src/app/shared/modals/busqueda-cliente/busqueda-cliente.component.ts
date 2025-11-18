import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-busqueda-cliente',
    templateUrl: './busqueda-cliente.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule]
})
export class BusquedaClienteComponent implements OnInit {

  public cliente: any = {};
  public loading = false;
  @Output() clienteSelect = new EventEmitter();

	modalRef?: BsModalRef;

	private destroyRef = inject(DestroyRef);
	private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService
	) { }

    ngOnInit() {
    }

	  openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }


    public submit():void{
        this.loading = true;
        this.apiService.store('cliente', this.cliente)
            .pipe(this.untilDestroyed())
            .subscribe(cliente => { 
            this.clienteSelect.emit({item: cliente});
            this.loading = false;
            this.modalRef?.hide()
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
