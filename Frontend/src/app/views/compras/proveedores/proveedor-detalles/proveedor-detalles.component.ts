import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-proveedor-detalles',
  templateUrl: './proveedor-detalles.component.html'
})
export class ProveedorDetallesComponent implements OnInit {

    public proveedor:any = {};
    public loading = false;
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.route.params.subscribe((params:any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('proveedor/', params.id).subscribe(proveedor => {
                    this.proveedor = proveedor;
                    this.loading = false;
                }, error => {this.alertService.error(error); this.loading = false;});
            }else{
                this.proveedor = {};
                this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
                this.proveedor.id_usuario = this.apiService.auth_user().id;
            }
        });
    }

}
