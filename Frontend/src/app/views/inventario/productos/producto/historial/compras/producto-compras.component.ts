import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

declare var $:any;

@Component({
    selector: 'app-producto-compras',
    templateUrl: './producto-compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})

export class ProductoComprasComponent extends BasePaginatedModalComponent implements OnInit {

    public producto_id?:number;
    public compras: PaginatedResponse<any> = {} as PaginatedResponse;
    public buscador:any = '';

    public proveedores:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.compras;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.compras = data;
    }

    ngOnInit() {
        this.producto_id = +this.route.snapshot.paramMap.get('id')!;
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.getAll('producto/compras/'+ this.producto_id)
          .pipe(this.untilDestroyed())
          .subscribe(compras => { 
            this.compras = compras;
            this.loading = false;this.filtrado = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.cdr.markForCheck();
            this.apiService.read('compras/buscar/', this.buscador)
              .pipe(this.untilDestroyed())
              .subscribe(compras => { 
                this.compras = compras;
                this.loading = false;this.filtrado = true;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; this.cdr.markForCheck(); });
        }
    }

    public setEstado(compra:any, estado:string){
        compra.estado = estado;
        this.apiService.store('compra', compra)
          .pipe(this.untilDestroyed())
          .subscribe(compra => { 
            this.alertService.success('Compra guardada', 'La compra fue guardada exitosamente');
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('compra/', id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                for (let i = 0; i < this.compras['data'].length; i++) { 
                    if (this.compras['data'][i].id == data.id )
                        this.compras['data'].splice(i, 1);
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
                   
        }

    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.read('compras/filtrar/' + filtro + '/', txt)
          .pipe(this.untilDestroyed())
          .subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    // Filtros

    openFilter(template: TemplateRef<any>) {

        if(!this.filtrado) {
            this.filtro.inicio = null;
            this.filtro.fin = null;
            this.filtro.proveedor_id = '';
            this.filtro.estado = '';
        }
        if(!this.proveedores.length){
            this.apiService.getAll('proveedores/list')
              .pipe(this.untilDestroyed())
              .subscribe(proveedores => { 
                this.proveedores = proveedores;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        }
        this.openModal(template);
    }

    onFiltrar(){
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.store('compras/filtrar', this.filtro)
          .pipe(this.untilDestroyed())
          .subscribe(compras => { 
            this.compras = compras;
            this.loading = false; this.filtrado = true;
            this.cdr.markForCheck();
            this.closeModal();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

    }

}
