import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
    selector: 'app-bodega',
    templateUrl: './bodega.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class BodegaComponent extends BasePaginatedModalComponent implements OnInit {

    public productos: PaginatedResponse<any> = {} as PaginatedResponse;
    public producto:any = {};
    public ajuste:any = {};
    public id:any;

    public filtro:any = {};
    public filtrado:boolean = false;
    public categorias:any =[];
    public buscador:any = '';

    constructor( 
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute,
        private router: Router,
        private cdr: ChangeDetectorRef
    ) {
        super(apiService, alertService, modalManager);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.productos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.productos = data;
    }

    ngOnInit() {
        this.id = +this.route.snapshot.paramMap.get('id')!;
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('bodega/productos/' + this.id)
          .pipe(this.untilDestroyed())
          .subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    override openModal(template: TemplateRef<any>, producto:any) {
        this.producto = producto;
        super.openModal(template, {class: 'modal-md'});
    }

    public async onSubmit() {
        this.loading = true;
        try {
            const productoGuardado = await this.apiService.store('inventario', this.producto)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.alertService.success("Bodega guardada", 'La bodega fue guardada exitosamente.');
            this.closeModal();
            this.loadAll();
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error._body || error);
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.apiService.getAll('bodega/productos/buscar/' + this.id + '/' + this.buscador)
              .pipe(this.untilDestroyed())
              .subscribe(productos => { 
                this.productos = productos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        }
    }

    imprimir(){
        window.open(this.apiService.baseUrl + '/api/reporte/bodegas/' + this.id + '/' + this.filtro.subcategorias_id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }


    // Ajustes

        public openModalAjuste(template: TemplateRef<any>, bodega:any) {
            this.producto = bodega;
            this.ajuste.producto_id = bodega.producto_id;
            this.ajuste.bodega_id = bodega.bodega_id;
            this.ajuste.stock_inicial = bodega.stock;
            this.openModal(template, null);
        }
        
        public onSubmitAjuste() {

            this.loading = true;
            this.ajuste.usuario_id = this.apiService.auth_user().id;
            this.apiService.store('ajuste', this.ajuste)
              .pipe(this.untilDestroyed())
              .subscribe(ajuste => {
                this.ajuste = {};
                this.producto.stock = ajuste.stock_final;
                this.loading = false;
                this.alertService.success("Bodega guardada", 'La bodega fue guardada exitosamente.');
                this.closeModal();
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error._body); this.loading = false; this.cdr.markForCheck(); });


        }

    // setPagination() ahora se hereda de BasePaginatedComponent

    // Filtros
        public openFilter(template: TemplateRef<any>) {
            if(!this.filtro.categorias_id) {
                this.filtro.categorias_id = [];
            }
            if(!this.categorias.length){
                this.apiService.getAll('categorias')
                  .pipe(this.untilDestroyed())
                  .subscribe(categorias => { 
                    this.categorias = categorias;
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
            }
            this.openModal(template, null);
        }

        public onFiltrar(){
            this.loading = true;
            if (this.filtro.categorias_id[0] == '') {
                this.filtro.categorias_id = null;
            }
            this.filtro.bodega_id = this.id;
            this.apiService.store('bodega/productos/filtrar', this.filtro)
              .pipe(this.untilDestroyed())
              .subscribe(productos => { 
                this.productos = productos;
                this.loading = false; this.filtrado = true;
                this.closeModal();
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

        }

}
