import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseFilteredPaginatedComponent } from '@shared/base/base-filtered-paginated.component';

@Component({
    selector: 'app-servicios',
    templateUrl: './servicios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, ImportarExcelComponent, PaginationComponent],

})
export class ServiciosComponent extends BaseFilteredPaginatedComponent implements OnInit {

    public servicios:any = [];
    public buscador:any = '';
    public downloading:boolean = false;
    public servicio:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    public categorias:any = [];
    modalRef!: BsModalRef;

    constructor(apiService: ApiService, alertService: AlertService,
                private modalService: BsModalService, private router: Router, private route: ActivatedRoute
    ){
        super(apiService, alertService);
    }

    protected aplicarFiltros(): void {
        this.filtrarServicios();
    }

    ngOnInit() {

        this.route.queryParams.pipe(this.untilDestroyed()).subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_categoria: +params['id_categoria'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                estado: params['estado'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarServicios();
        });

        this.apiService.getAll('categorias/list').pipe(this.untilDestroyed()).subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.loading = true;
        this.filtrarServicios();

    }

    public filtrarServicios(){

        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;
        if(!this.filtros.id_categoria){
            this.filtros.id_categoria = '';
        }
        this.apiService.getAll('servicios', this.filtros).pipe(this.untilDestroyed()).subscribe(servicios => { 
            this.servicios = servicios;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('servicio/', id).pipe(this.untilDestroyed()).subscribe(data => {
                for (let i = 0; i < this.servicios['data'].length; i++) { 
                    if (this.servicios['data'][i].id == data.id )
                        this.servicios['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    // setPagination() ahora se hereda de BaseFilteredPaginatedComponent

    openModalPrecio(template: TemplateRef<any>, servicio:any) {
        // if(this.apiService.auth_user().tipo == 'Administrador') {
        //     this.servicio = servicio;
        //     this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        // }
        if(this.apiService.validateRole('super_admin', true) || this.apiService.validateRole('admin', true)) {
            this.servicio = servicio;
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        }

    }

    public setEstado(producto:any){
        this.apiService.store('producto', producto).pipe(this.untilDestroyed()).subscribe(producto => { 
            this.alertService.success('Producto actualizado', 'El producto fue guardado exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public onSubmit() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('servicio', this.servicio).pipe(this.untilDestroyed()).subscribe(servicio=> {
            this.servicio= {};
            this.alertService.success('Servicio guardado', 'El servicio fue guardado exitosamente.');
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;
        });
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('servicios/exportar', this.filtros).pipe(this.untilDestroyed()).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'servicios.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

}
