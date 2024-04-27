import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-proyecto',
  templateUrl: './proyecto.component.html'
})
export class ProyectoComponent implements OnInit {

    public proyecto:any = {};
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
            this.apiService.read('proyecto/', id).subscribe(proyecto => {
                this.proyecto = proyecto;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.proyecto = {};
            this.proyecto.estado = 'Pendiente';
            this.proyecto.id_cliente = '';
            this.proyecto.fecha = this.apiService.date();
            this.proyecto.id_empresa = this.apiService.auth_user().id_empresa;
            this.proyecto.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.proyecto.id_usuario = this.apiService.auth_user().id;
        }

    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.proyecto.id_proveedor = proveedor.id;
    }


    public onSubmit(){
        this.saving = true;

        this.apiService.store('proyecto', this.proyecto).subscribe(proyecto => {
            if (!this.proyecto.id) {
                this.alertService.success('Paquete guardado', 'El proyecto fue guardado exitosamente.');
            }else{
                this.alertService.success('Paquete creado', 'El proyecto fue añadido exitosamente.');
            }
            this.router.navigate(['/proyectos']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
