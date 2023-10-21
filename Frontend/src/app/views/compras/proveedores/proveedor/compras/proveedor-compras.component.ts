import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
  selector: 'app-proveedor-compras',
  templateUrl: './proveedor-compras.component.html'
})
export class ProveedorComprasComponent implements OnInit {

		public proveedor:any = {};
		public proveedores:any = [];
		public compras:any = [];
        public id:any;
		public token:string = '';
        public filtro:any = {};

		public loading:boolean = false;
	    constructor(private apiService: ApiService, private alertService: AlertService,  private route: ActivatedRoute, private router: Router){ }

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
   	            this.compras = [];
   	        }
   	        else{
   	            // Optenemos el proveedor
   	            this.loading = true;
   	            this.apiService.read('proveedor/', this.id).subscribe(proveedor => {
   	               this.proveedor = proveedor;
   	            }, error => {this.alertService.error(error); this.loading = false; });
   	            this.apiService.read('proveedor/compras/', this.id).subscribe(compras => {
   	                this.compras = compras;
   	            	this.loading = false;
   	            }, error => {this.alertService.error(error); this.loading = false; });
   	        }

	    }

        onFiltrar(){
            this.filtro.id = this.id;
            this.apiService.store('proveedor/compras/filtrar', this.filtro).subscribe(compras => { 
                this.compras = compras;
            }, error => {this.alertService.error(error);});

        }

        public setEstado(compra:any, estado:string){
            compra.estado = estado;
            this.apiService.store('compra', compra).subscribe(compra => {
                this.loadAll();
                this.alertService.success('Actualizado');
            }, error => {this.alertService.error(error); });
        }

	    public setPagination(event:any):void{
	        this.loading = true;
	        this.apiService.paginate(this.proveedores.path + '?page='+ event.page).subscribe(proveedores => { 
	            this.proveedores = proveedores;
	            this.loading = false;
	        }, error => {this.alertService.error(error); this.loading = false;});
	    }


}
