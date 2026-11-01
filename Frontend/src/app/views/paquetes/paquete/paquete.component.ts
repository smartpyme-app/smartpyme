import { Component, OnInit,TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { SelectSearchComponent } from '@shared/parts/select-search/select-search.component';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { Observable, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';

@Component({
    selector: 'app-paquete',
    templateUrl: './paquete.component.html',
    styleUrls: ['./paquete.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, CrearClienteComponent, SelectSearchComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaqueteComponent implements OnInit {

    public paquete:any = {};
    public usuarios:any = [];
    public sucursales:any = [];
    /** Solo el cliente ya elegido (edición); la búsqueda va por API. */
    public clienteLookup: any[] = [];
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
        this.loadCatalogos();
    }

    private loadCatalogos(): void {
        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => {
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('usuarios/list').pipe(this.untilDestroyed()).subscribe(usuarios => {
            this.usuarios = usuarios;
            this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.cdr.markForCheck(); });
    }

    searchClientes = (term: string): Observable<any[]> => {
        if (!term || term.length < 2) {
            return of([]);
        }
        return this.apiService.getAll(`clientes/search?q=${encodeURIComponent(term)}`).pipe(
            map((response: any) => (Array.isArray(response) ? response : response?.data ?? [])),
            catchError(() => of([])),
        );
    };

    getClienteDisplay = (cliente: any): string => {
        const legal = cliente?.tipo === 'Empresa' ? cliente.nombre_empresa : cliente.nombre_completo;
        const comercial = String(cliente?.nombre_comercial ?? '').trim();
        if (!comercial || comercial.toLowerCase() === String(legal ?? '').trim().toLowerCase()) {
            return legal;
        }
        return `${legal} (${comercial})`;
    };

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('paquete/', id).pipe(this.untilDestroyed()).subscribe(paquete => {
                this.paquete = paquete;
                this.clienteLookup = paquete?.cliente ? [paquete.cliente] : [];
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

    public setCliente(cliente: any): void {
        this.clienteLookup = [cliente];
        this.paquete.id_cliente = cliente.id;
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
