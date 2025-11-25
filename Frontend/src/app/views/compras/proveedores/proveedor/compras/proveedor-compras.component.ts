import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
    selector: 'app-proveedor-compras',
    templateUrl: './proveedor-compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class ProveedorComprasComponent extends BasePaginatedComponent implements OnInit {

		public proveedor:any = {};
		public proveedores:any = [];
		public compras: PaginatedResponse<any> = {} as PaginatedResponse;
        public id:any;
		public token:string = '';
        public filtro:any = {};

	    constructor(apiService: ApiService, alertService: AlertService,  private route: ActivatedRoute, private router: Router){
            super(apiService, alertService);
        }

        protected getPaginatedData(): PaginatedResponse | null {
            return this.compras;
        }

        protected setPaginatedData(data: PaginatedResponse): void {
            this.compras = data;
        }

		ngOnInit() {
			this.token = this.apiService.auth_token();
	        this.loadAll();
	    }

	    public loadAll() {
            this.filtro.estado = "";
            this.filtro.forma_de_pago = "";

            if(this.route.snapshot.paramMap.get('estado')){
                this.filtro.estado = this.route.snapshot.paramMap.get('estado');
            }
	        this.id = +this.route.snapshot.paramMap.get('id')!;
                            
   	        if(isNaN(this.id)){
   	            this.compras = {} as PaginatedResponse<any>;
   	        }
   	        else{
   	            // Optenemos el proveedor
   	            this.loading = true;
   	            this.apiService.read('proveedor/', this.id)
                    .pipe(this.untilDestroyed())
                    .subscribe(proveedor => {
                        this.proveedor = proveedor;
                    }, error => {this.alertService.error(error); this.loading = false; });
   	            this.apiService.read('proveedor/compras/', this.id)
                    .pipe(this.untilDestroyed())
                    .subscribe(compras => {
                        this.compras = compras;
                        this.loading = false;
                    }, error => {this.alertService.error(error); this.loading = false; });
   	        }

	    }

        onFiltrar(){
            this.filtro.id = this.id;
            this.apiService.store('proveedor/compras/filtrar', this.filtro)
                .pipe(this.untilDestroyed())
                .subscribe(compras => { 
                    this.compras = compras;
                }, error => {this.alertService.error(error);});

        }

        public setEstado(compra:any, estado:string){
            compra.estado = estado;
            this.apiService.store('compra', compra)
                .pipe(this.untilDestroyed())
                .subscribe(compra => {
                    this.loadAll();
                    this.alertService.success('Compra guardada', 'La compra fue guardada exitosamente.');
                }, error => {this.alertService.error(error); });
        }

	    // setPagination() ahora se hereda de BasePaginatedComponent


}
