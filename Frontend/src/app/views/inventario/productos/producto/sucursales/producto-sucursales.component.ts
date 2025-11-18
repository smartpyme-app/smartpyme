import { Component, OnInit, TemplateRef, Input, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-producto-sucursales',
    templateUrl: './producto-sucursales.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ProductoSucursalesComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
    public sucursales: any = [];
    public sucursal: any = {};
    public sucursalSelected: any = {};
    public ajuste:any = {};
    public buscador:string = '';

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

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
        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => {
            this.sucursales = sucursales;
            this.validate();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    public validate(){
        for (let i = 0; i < this.sucursales.length; i++){
            let sucursal = this.producto.sucursales.find((item:any) => item.sucursal_id == this.sucursales[i].id);
            console.log (sucursal);
            if (sucursal) {
                this.sucursales[i].activo = sucursal.activo;
                this.sucursales[i].sucursal_id = sucursal.id;
            }else{
                this.sucursales[i].activo = false;
                this.sucursales[i].sucursal_id = null;
            }
        }
    }

    public onSubmit(sucursal:any) {
        this.sucursal.producto_id = this.producto.id;
        this.sucursal.activo = sucursal.activo;
        this.sucursal.sucursal_id = sucursal.id;
        this.sucursal.id = sucursal.sucursal_id;

        this.loading = true;
        this.apiService.store('producto/sucursal', this.sucursal).pipe(this.untilDestroyed()).subscribe(sucursal => {
            this.sucursal = {};
            this.loading = false;
            this.alertService.success('Sucursal guardada', 'El inventario fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
