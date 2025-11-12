import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import {  } from 'ngx-bootstrap/modal';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
    selector: 'app-compra-proveedor',
    templateUrl: './compra-proveedor.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CompraProveedorComponent implements OnInit {

	@Input() proveedor: any = {};
	@Input() compra: any;
	@Output() proveedorSelect = new EventEmitter();
    public proveedores: any = [];
    public searching = false;
	modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService
	) { }

	ngOnInit() {
	}

	openModal(template: TemplateRef<any>) {
        if(this.proveedor.id) {
            this.searching = false;
        }

        this.modalRef = this.modalService.show(template);
        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
    }

    searchProducto(){
        if(this.proveedor.nombre && this.proveedor.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('proveedores/buscar/', this.proveedor.nombre).subscribe(proveedores => {
               this.proveedores = proveedores;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.proveedor.nombre  || this.proveedor.nombre.length < 1 ){ this.searching = false; this.proveedor = {}; this.proveedores.total = 0; }
    }

	public selectProveedor(proveedor:any){
        this.proveedores = [];
        this.proveedor = proveedor;
	    this.proveedorSelect.emit({proveedor: this.proveedor});
	    this.modalRef?.hide()
	}

    clear(){
        if(this.proveedores.data && this.proveedores.data.length == 0) { this.proveedores = []; }
    }

}
