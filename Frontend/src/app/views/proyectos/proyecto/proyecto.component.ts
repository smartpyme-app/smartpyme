import { Component, OnInit,TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { SumPipe } from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SharedDataService } from '@services/shared-data.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';

@Component({
    selector: 'app-proyecto',
    templateUrl: './proyecto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, CrearClienteComponent, SumPipe],
    
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
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    private apiService: ApiService, 
	    private alertService: AlertService,
	    private route: ActivatedRoute, 
	    private router: Router, 
	    private modalService: BsModalService,
	    private sharedDataService: SharedDataService
	) { }

	ngOnInit() {
        this.loadAll();

        // Cargar datos compartidos usando SharedDataService
        this.sharedDataService.getSucursales()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (sucursales) => {
                    this.sucursales = sucursales;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });

        this.sharedDataService.getUsuarios()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (usuarios) => {
                    this.usuarios = usuarios;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });

        this.sharedDataService.getProveedores()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (proveedores) => {
                    this.proveedores = proveedores;
                    this.loading = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });

        this.sharedDataService.getClientes()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (clientes) => {
                    this.clientes = clientes;
                    this.loading = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('proyecto/', id).pipe(this.untilDestroyed()).subscribe(proyecto => {
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

    public setCliente(cliente:any){
        this.clientes.push(cliente);
        this.proyecto.id_cliente = cliente.id;
    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.proyecto.id_proveedor = proveedor.id;
    }


    public async onSubmit(){
        this.saving = true;

        try {
            const proyecto = await this.apiService.store('proyecto', this.proyecto)
                .pipe(this.untilDestroyed())
                .toPromise();

            const isNew = !this.proyecto.id;
            const title = isNew ? 'Paquete creado' : 'Paquete guardado';
            const message = isNew 
                ? 'El proyecto fue añadido exitosamente.' 
                : 'El proyecto fue guardado exitosamente.';
            
            this.alertService.success(title, message);
            this.router.navigate(['/proyectos']);
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.saving = false;
        }
    }

}
