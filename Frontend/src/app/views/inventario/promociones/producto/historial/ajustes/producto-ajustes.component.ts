import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../../../services/alert.service';
import { ApiService } from '../../../../../../services/api.service';
import { CommonModule } from '@angular/common';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

declare var $:any;

@Component({
  selector: 'app-producto-ajustes',
  templateUrl: './producto-ajustes.component.html'
})

export class ProductoAjustesComponent extends BasePaginatedComponent implements OnInit {

    public producto_id?:number;
    public ajustes: PaginatedResponse<any> = {} as PaginatedResponse;
    public buscador:any = '';

    public proveedores:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(apiService: ApiService, alertService: AlertService,
                private modalService: BsModalService,  private route: ActivatedRoute, private router: Router,
    ){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.ajustes;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.ajustes = data;
    }

    ngOnInit() {
        this.producto_id = +this.route.snapshot.paramMap.get('id')!;
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('producto/ajustes/'+ this.producto_id).pipe(this.untilDestroyed()).subscribe(ajustes => { 
            this.ajustes = ajustes;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('ajustes/buscar/', this.buscador).pipe(this.untilDestroyed()).subscribe(ajustes => { 
                this.ajustes = ajustes;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(compra:any, estado:string){
        compra.estado = estado;
        this.apiService.store('compra', compra).pipe(this.untilDestroyed()).subscribe(compra => { 
            this.alertService.success('Actualizado', 'El ajuste fue actualizado exitosamente');
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('compra/', id).pipe(this.untilDestroyed()).subscribe(data => {
                for (let i = 0; i < this.ajustes['data'].length; i++) { 
                    if (this.ajustes['data'][i].id == data.id )
                        this.ajustes['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('ajustes/filtrar/' + filtro + '/', txt).pipe(this.untilDestroyed()).subscribe(ajustes => { 
            this.ajustes = ajustes;
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
            this.apiService.getAll('proveedores/list').pipe(this.untilDestroyed()).subscribe(proveedores => { 
                this.proveedores = proveedores;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('ajustes/filtrar', this.filtro).pipe(this.untilDestroyed()).subscribe(ajustes => { 
            this.ajustes = ajustes;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
