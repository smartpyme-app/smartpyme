import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-proveedor',
  templateUrl: './proveedor.component.html'
})
export class ProveedorComponent implements OnInit {

    public proveedor:any = {};
    public loading = false;
    public saving = false;

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
                this.proveedor.tipo_contribuyente = '';
                this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
                this.proveedor.id_usuario = this.apiService.auth_user().id;
            }
        });
    }

    public submit():void{
        this.saving = true;

        this.apiService.store('proveedor', this.proveedor).subscribe(proveedor => { 
            if(this.proveedor.id) {
                this.alertService.success('Proveedor guardado', 'El proveedor fue guardado exitosamente.');
            }else {
                this.alertService.success('Proveedor creado', 'El proveedor fue añadido exitosamente.');
            }
            this.router.navigate(['/proveedores']);
            this.proveedor = proveedor;
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }


}
