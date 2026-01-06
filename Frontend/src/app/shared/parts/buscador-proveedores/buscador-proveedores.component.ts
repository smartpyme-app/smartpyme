import { Component, OnInit, EventEmitter, Output, Input, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

@Component({
    selector: 'app-buscador-proveedores',
    templateUrl: './buscador-proveedores.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BuscadorProveedoresComponent implements OnInit {

	@Output() setProveedor = new EventEmitter();

	public proveedores: any = [];
    public proveedor: any = {};
    public searching = false;

	private destroyRef = inject(DestroyRef);
	private untilDestroyed = subscriptionHelper(this.destroyRef);
	private cdr = inject(ChangeDetectorRef);

	constructor(private apiService: ApiService, private alertService: AlertService) { }

	ngOnInit() {

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
               this.cdr.markForCheck();
            }, error => {this.alertService.error(error);this.searching = false; this.cdr.markForCheck();});
        }else if (!this.proveedor.nombre  || this.proveedor.nombre.length < 1 ){ this.searching = false; this.proveedor = {}; this.proveedores.total = 0; this.cdr.markForCheck(); }
    }

    selectProveedor(proveedor:any){
        this.proveedores = [];
        this.proveedor = proveedor;
        console.log(this.proveedor);
        this.cdr.markForCheck();
        this.setProveedor.emit({proveedor: this.proveedor});
    }

}
