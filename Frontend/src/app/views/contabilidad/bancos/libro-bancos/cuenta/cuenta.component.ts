import { Component, OnInit,TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

import * as moment from 'moment';

@Component({
    selector: 'app-cuenta',
    templateUrl: './cuenta.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class CuentaComponent extends BaseComponent implements OnInit {

    public cuenta:any = {};
    public categorias:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public clientes:any = [];
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

	constructor( 
	    protected apiService: ApiService, 
	    protected alertService: AlertService,
	    private route: ActivatedRoute, 
	    private router: Router, 
	    private modalService: BsModalService,
	    private cdr: ChangeDetectorRef
	) {
        super();
    }

	ngOnInit() {
        this.loadAll();

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe(sucursales => {
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('usuarios/list')
          .pipe(this.untilDestroyed())
          .subscribe(usuarios => {
            this.usuarios = usuarios;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.apiService.getAll('proveedores/list')
          .pipe(this.untilDestroyed())
          .subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

        this.apiService.getAll('clientes/list')
          .pipe(this.untilDestroyed())
          .subscribe(clientes => {
            this.clientes = clientes;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('cuenta/', id)
              .pipe(this.untilDestroyed())
              .subscribe(cuenta => {
                this.cuenta = cuenta;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
        }else{
            this.cuenta = {};
            this.cuenta.estado = 'Pendiente';
            this.cuenta.id_cliente = '';
            this.cuenta.fecha = this.apiService.date();
            this.cuenta.id_empresa = this.apiService.auth_user().id_empresa;
            this.cuenta.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.cuenta.id_usuario = this.apiService.auth_user().id;
        }

    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.cuenta.id_proveedor = proveedor.id;
        this.cdr.markForCheck();
    }


    public onSubmit(){
        this.saving = true;

        this.apiService.store('cuenta', this.cuenta)
          .pipe(this.untilDestroyed())
          .subscribe(cuenta => {
            if (!this.cuenta.id) {
                this.alertService.success('Paquete guardado', 'El cuenta fue guardado exitosamente.');
            }else{
                this.alertService.success('Paquete creado', 'El cuenta fue añadido exitosamente.');
            }
            this.router.navigate(['/cuentas']);
            this.saving = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});
    }

}
