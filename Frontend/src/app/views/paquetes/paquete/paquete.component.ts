import { Component, OnInit,TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';

@Component({
    selector: 'app-paquete',
    templateUrl: './paquete.component.html',
    styleUrls: ['./paquete.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, CrearClienteComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
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

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
	    private cdr: ChangeDetectorRef
	) { }

	ngOnInit() {
        this.loadAll();

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => {
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('usuarios/list').pipe(this.untilDestroyed()).subscribe(usuarios => {
            this.usuarios = usuarios;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('proveedores/list').pipe(this.untilDestroyed()).subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

        this.apiService.getAll('clientes/list').pipe(this.untilDestroyed()).subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('paquete/', id).pipe(this.untilDestroyed()).subscribe(paquete => {
                this.paquete = paquete;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
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
            this.cdr.markForCheck();
        }

    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.paquete.id_proveedor = proveedor.id;
        this.cdr.markForCheck();
    }


    public async onSubmit(){
        this.saving = true;
        try {
            // ponytail: prevent database integrity violations by default-initializing numeric fields to 0 if null/empty
            if (this.paquete.otros === undefined || this.paquete.otros === null || this.paquete.otros === '') {
                this.paquete.otros = 0;
            }
            if (this.paquete.cuenta_a_terceros === undefined || this.paquete.cuenta_a_terceros === null || this.paquete.cuenta_a_terceros === '') {
                this.paquete.cuenta_a_terceros = 0;
            }
            if (this.paquete.precio === undefined || this.paquete.precio === null || this.paquete.precio === '') {
                this.paquete.precio = 0;
            }
            if (this.paquete.total === undefined || this.paquete.total === null || this.paquete.total === '') {
                this.paquete.total = 0;
            }

            const isNew = !this.paquete.id;
            await this.apiService.store('paquete', this.paquete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const titulo = isNew ? 'Paquete creado' : 'Paquete guardado';
            const mensaje = isNew 
                ? 'El paquete fue añadido exitosamente.' 
                : 'El paquete fue guardado exitosamente.';
            
            this.alertService.success(titulo, mensaje);
            this.router.navigate(['/paquetes']);
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.saving = false;
            this.cdr.markForCheck();
        }
    }

}
