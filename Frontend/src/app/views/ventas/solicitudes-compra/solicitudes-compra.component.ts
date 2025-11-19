import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';


@Component({
    selector: 'app-solicitudes-compra',
    templateUrl: './solicitudes-compra.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, LazyImageDirective],
    
})

export class SolicitudesCompraComponent extends BasePaginatedModalComponent implements OnInit {

    public compras: PaginatedResponse<any> = {} as PaginatedResponse;
    public compra:any = {};
    public downloading:boolean = false;

    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public documentos:any = [];
    public override filtros:any = {};
    public filtrado:boolean = false;

    constructor(
        protected override apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
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

        this.loadAll();

        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => { 
                this.proveedores = proveedores;
            }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCompras();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        
        this.filtrarCompras();
    }

    public filtrarCompras(){
        this.loading = true;
        if(!this.filtros.id_proveedor){
            this.filtros.id_proveedor = '';
        }
        this.apiService.getAll('ordenes-de-compras/solicitudes', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(compras => { 
                this.compras = compras;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
            }, error => {this.alertService.error(error); });
    }

    public setEstado(cotizacion:any){
        this.apiService.store('orden-de-compra', cotizacion)
            .pipe(this.untilDestroyed())
            .subscribe(cotizacion => { 
                this.alertService.success('Solicitud de compra actualizada', 'La solicitud de compra fue actualizada exitosamente.');
            }, error => {this.alertService.error(error); });
    }
    
    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('orden-de-compra/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                    for (let i = 0; i < this.compras['data'].length; i++) { 
                        if (this.compras['data'][i].id == data.id )
                            this.compras['data'].splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
                   
        }

    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public reemprimir(compra:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + compra.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // Editar

    openModalEdit(template: TemplateRef<any>, compra:any) {
        this.compra = compra;
        
        this.apiService.getAll('documentos')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
            }, error => {this.alertService.error(error);});

        this.openModal(template);
    }

    public onSubmit() {
        this.loading = true;            
        this.apiService.store('orden-de-compra', this.compra)
            .pipe(this.untilDestroyed())
            .subscribe(compra => {
                this.compra = {};
                if (this.modalRef) {
                    this.closeModal();
                }
                this.loading = false;
                this.alertService.success('Solicitud de compra guardada', 'La solicitud de compra fue guardada exitosamente.');
            },error => {this.alertService.error(error); this.loading = false; });

    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });

        this.openModal(template);
    }

    public imprimir(compra:any){
        window.open(this.apiService.baseUrl + '/api/orden-de-compra/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('ordenes-de-compras/solicitudes/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'solicitudes-de-compra.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }


}
