import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-compra-proveedor',
    templateUrl: './compra-proveedor.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CompraProveedorComponent extends BaseModalComponent implements OnInit {

	@Input() proveedor: any = {};
	@Input() compra: any;
	@Output() proveedorSelect = new EventEmitter();
    public proveedores: any = [];
    public searching = false;

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
        if(this.proveedor.id) {
            this.searching = false;
        }

        super.openModal(template);
        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.pipe(this.untilDestroyed()).subscribe(val => { this.searchProducto(); });
    }

    searchProducto(){
        if(this.proveedor.nombre && this.proveedor.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('proveedores/buscar/', this.proveedor.nombre)
                .pipe(this.untilDestroyed())
                .subscribe(proveedores => {
               this.proveedores = proveedores;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.proveedor.nombre  || this.proveedor.nombre.length < 1 ){ this.searching = false; this.proveedor = {}; this.proveedores.total = 0; }
    }

	public selectProveedor(proveedor:any){
        this.proveedores = [];
        this.proveedor = proveedor;
	    this.proveedorSelect.emit({proveedor: this.proveedor});
	    this.closeModal()
	}

    clear(){
        if(this.proveedores.data && this.proveedores.data.length == 0) { this.proveedores = []; }
    }

}
