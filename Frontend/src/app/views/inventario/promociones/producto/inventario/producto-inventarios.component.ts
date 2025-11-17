import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';
import { ModalManagerService } from '../../../../../services/modal-manager.service';
import { BaseModalComponent } from '../../../../../shared/base/base-modal.component';

@Component({
    selector: 'app-producto-inventarios',
    templateUrl: './producto-inventarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ProductoInventariosComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
    public sucursales: any = [];
    public sucursal: any = {};
    public inventario: any = {};
    public sucursalSelected: any = {};
    public ajuste:any = {};
    public buscador:string = '';

    constructor(
        private apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('producto/sucursales/' + this.producto.id).subscribe(sucursales => {
            this.producto.sucursales = sucursales;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

        this.apiService.getAll('sucursales').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    public setAjuste(event:any){
        this.inventario.stock = event.stock_final;
    }


    openModalSucursal(template: TemplateRef<any>, sucursal:any) {
        this.sucursal = sucursal;
        if (!this.sucursal.id){
            this.sucursal.activo = true;
            this.sucursal.inventario = false;
        }else{
            this.sucursalSelected = this.sucursales.find((x:any) => x.id == sucursal.sucursal_id);
            console.log(this.sucursalSelected);
        }
        this.sucursal.producto_id = this.producto.id;
        super.openModal(template, {class: 'modal-md'});
    }

    onSucursal(id:any){
        let sucursal = this.sucursales.find((x:any) => x.id == id);
        console.log(sucursal);
        this.sucursalSelected = sucursal;
    }

    public agregarSucursal(sucursal:any) {
        console.log (sucursal);
        this.sucursal = sucursal;
        this.loading = true;
        this.sucursal.producto_id = this.producto.id;
        this.apiService.store('producto/sucursal', this.sucursal).subscribe(sucursal => {
            if(!this.sucursal.id)
                this.producto.sucursales.push(sucursal);
            this.sucursal = {};
            this.producto.bodega_venta_id = sucursal.bodega_venta_id;
            this.loading = false;
            this.closeModal();
            this.alertService.success("Registro guardado");
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public deleteSucursal(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/sucursal/', id) .subscribe(data => {
                for (let i = 0; i < this.producto.sucursales.length; i++) { 
                    if (this.producto.sucursales[i].id == data.id )
                        this.producto.sucursales.splice(i, 1);
                }
                this.alertService.success("Registro eliminado");
            }, error => {this.alertService.error(error); });
                   
        }

    }


    openModalInventario(template: TemplateRef<any>, sucursal:any, inventario:any) {
        this.sucursal = sucursal;
        this.inventario = inventario;
        if (!this.inventario.id){
            this.inventario.stock = 0;
            this.inventario.bodega_id = 1;
        }
        this.inventario.producto_id = this.producto.id;
        super.openModal(template, {class: 'modal-md'});
    }

    public agregarInventario() {
        this.loading = true;
        this.apiService.store('inventario', this.inventario).subscribe(inventario => {
            if(!this.inventario.id)
                this.producto.sucursal.inventarios.push(inventario);
            this.inventario = {};
            this.loading = false;
            this.closeModal();
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('inventario/', id) .subscribe(data => {
                for (let i = 0; i < this.producto.inventarios.length; i++) { 
                    if (this.producto.sucursal.inventarios[i].id == data.id )
                        this.producto.sucursal.inventarios.splice(i, 1);
                }
                this.alertService.success("Registro eliminado");
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
