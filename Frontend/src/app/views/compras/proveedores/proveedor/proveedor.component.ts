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
                this.proveedor.tipo = 'Persona';
                this.proveedor.tipo_contribuyente = '';
                this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
                this.proveedor.id_usuario = this.apiService.auth_user().id;
            }
        });
    }

    public setTipo(tipo:any){
        this.proveedor.tipo = tipo;
    }

    public onSubmit():void{
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


    public verificarSiExiste(){
        if(this.proveedor.nombre && this.proveedor.apellido){
            this.apiService.getAll('proveedores', { nombre: this.proveedor.nombre, apellido: this.proveedor.apellido, estado: 1, }).subscribe(proveedores => { 
                if(proveedores.data[0]){
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.', 
                        'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' + this.apiService.appUrl + '/proveedor/editar/' + proveedores.data[0].id + '">Ver proveedor</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
                    );
                }
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }
    }

}
