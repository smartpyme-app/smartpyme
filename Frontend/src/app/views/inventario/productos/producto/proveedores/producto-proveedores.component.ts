import { Component, OnInit, TemplateRef, Input, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { TruncatePipe } from '@pipes/truncate.pipe';

@Component({
    selector: 'app-producto-proveedores',
    templateUrl: './producto-proveedores.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe],
    
})
export class ProductoProveedoresComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
    public proveedores: any = [];
    public proveedor: any = {};
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
        this.apiService.getAll('proveedores/list').pipe(this.untilDestroyed()).subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    override openModal(template: TemplateRef<any>, proveedor:any) {
        this.proveedor = proveedor;
        this.proveedor.id_proveedor = '';
        super.openModal(template, {class: 'modal-md'});
    }

    // proveedor
    public setProveedor(proveedor:any){
        if(!this.proveedor.id_proveedor){
            this.proveedores.push(proveedor);
        }
        this.proveedor.id_proveedor = proveedor.id;
    }

    public onSubmit() {
        this.loading = true;
        this.proveedor.id_producto = this.producto.id;
        this.apiService.store('producto/proveedor', this.proveedor).pipe(this.untilDestroyed()).subscribe(proveedor => {
            if(!this.proveedor.id)
                this.producto.proveedores.push(proveedor);
            this.proveedor = {};
            this.loading = false;
            this.closeModal();
            this.alertService.success('Proveedor agregado', 'El proveedor fue agregado exitosamente.');
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public delete(proveedor:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/proveedor/', proveedor.id).pipe(this.untilDestroyed()).subscribe(data => {
                for (let i = 0; i < this.producto.proveedores.length; i++) { 
                    if (this.producto.proveedores[i].id == data.id )
                        this.producto.proveedores.splice(i, 1);
                }
                this.alertService.success('Proveedor eliminado', 'El proveedor fue eliminado exitosamente.');
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
