import { Component, OnInit, TemplateRef } from '@angular/core';
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
    selector: 'app-producto-ventas',
    templateUrl: './producto-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})

export class ProductoVentasComponent extends BasePaginatedModalComponent implements OnInit {

    public producto_id?:number;
    public ventas: PaginatedResponse<any> = {} as PaginatedResponse;
    public buscador:any = '';

    public proveedores:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.ventas;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.ventas = data;
    }

    ngOnInit() {
        this.producto_id = +this.route.snapshot.paramMap.get('id')!;
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('producto/ventas/'+ this.producto_id)
          .pipe(this.untilDestroyed())
          .subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('ventas/buscar/', this.buscador)
              .pipe(this.untilDestroyed())
              .subscribe(ventas => { 
                this.ventas = ventas;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(venta:any, estado:string){
        venta.estado = estado;
        this.apiService.store('venta', venta)
          .pipe(this.untilDestroyed())
          .subscribe(venta => { 
            this.alertService.success('Venta guardada', 'La venta fue guardada exitosamente');
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('venta/', id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                for (let i = 0; i < this.ventas['data'].length; i++) { 
                    if (this.ventas['data'][i].id == data.id )
                        this.ventas['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('ventas/filtrar/' + filtro + '/', txt)
          .pipe(this.untilDestroyed())
          .subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); });

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
            }, error => {this.alertService.error(error); });
        }
        this.openModal(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('ventas/filtrar', this.filtro)
          .pipe(this.untilDestroyed())
          .subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false; this.filtrado = true;
            this.closeModal();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
