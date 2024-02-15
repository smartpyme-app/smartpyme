import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-paquete',
  templateUrl: './paquete.component.html'
})
export class PaqueteComponent implements OnInit {

    public paquete:any = {};
    public categorias:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public clientes:any = [];
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('paquete/', id).subscribe(paquete => {
                this.paquete = paquete;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.paquete = {};
            this.paquete.forma_pago = 'Efectivo';
            this.paquete.estado = 'En bodega';
            this.paquete.id_cliente = '';
            this.paquete.id_proveedor = '';
            // this.paquete.fecha_pago = this.apiService.date();
            this.paquete.fecha = this.apiService.date();
            this.paquete.id_empresa = this.apiService.auth_user().id_empresa;
            this.paquete.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.paquete.id_usuario = this.apiService.auth_user().id;
        }

    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.paquete.id_proveedor = proveedor.id;
    }


    public onSubmit(){
        this.saving = true;

        this.apiService.store('paquete', this.paquete).subscribe(paquete => {
            if (!this.paquete.id) {
                this.alertService.success('Paquete guardado', 'El paquete fue guardado exitosamente.');
            }else{
                this.alertService.success('Paquete creado', 'El paquete fue añadido exitosamente.');
            }
            this.router.navigate(['/paquetes']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
