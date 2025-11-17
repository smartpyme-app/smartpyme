import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe } from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-producto-inventarios',
    templateUrl: './producto-inventarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, SumPipe],
    
})
export class ProductoInventariosComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
    public bodegas: any = [];
    public sucursal: any = {};
    public inventario: any = {};
    public sucursalSelected: any = {};
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
    }

    public setAjuste(event:any){
        this.inventario.stock = event.stock_real;
    }


    override openModal(template: TemplateRef<any>, inventario:any) {
        this.inventario = inventario;

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

        if(!this.inventario.id) {
            this.inventario.stock = 0;
            this.inventario.id_producto = this.producto.id;
            this.inventario.id_bodega = this.inventario.id_bodega;
            this.alertService.success('Inventario creado', 'El inventario fue añadido exitosamente.');
        }else{
            this.alertService.success('Inventario guardado', 'El inventario fue guardado exitosamente.');
        }
        super.openModal(template, {class: 'modal-md'});
    }

    public onSubmit() {
        this.loading = true;

        this.apiService.store('inventario', this.inventario).subscribe(inventario => {
            if(!this.inventario.id) {
                this.producto.inventarios.push(inventario);
                this.alertService.success('Inventario creado', 'El inventario fue añadido exitosamente.');
            } else {
                this.alertService.success('Inventario actualizado', 'El inventario fue actualizado exitosamente.');
            }

            this.inventario = {};
            this.loading = false;
            this.closeModal();
        }, error => {
            const errorMessage = error.error?.error || error.error?.message || error.message || 'Error desconocido';

            this.alertService.error(errorMessage);
            this.loading = false;
        });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('inventario/', id) .subscribe(data => {
                for (let i = 0; i < this.producto.inventarios.length; i++) {
                    if (this.producto.inventarios[i].id == data.id )
                        this.producto.inventarios.splice(i, 1);
                }
                this.alertService.success('Inventario eliminado', 'El inventario fue eliminado exitosamente.');
            }, error => {this.alertService.error(error); });

        }

    }

    obtenerIdSucursal(id:number) {
        this.inventario.id_sucursal = this.bodegas.find((bodega:any) => bodega.id == id).id_sucursal;
    }

}
