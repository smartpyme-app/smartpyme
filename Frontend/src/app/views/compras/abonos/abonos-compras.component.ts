import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';


@Component({
    selector: 'app-abonos-compras',
    templateUrl: './abonos-compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PopoverModule, TooltipModule, PaginationComponent],

})

export class AbonosComprasComponent extends BasePaginatedModalComponent implements OnInit {

    public abonos: PaginatedResponse<any> = {} as PaginatedResponse;
    public abono:any = {};
    public downloading:boolean = false;
    public formaPagos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public documentos:any = [];
    public override filtros:any = {};
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.abonos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.abonos = data;
    }

    ngOnInit() {

        this.loadAll();

        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
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

        this.filtrarAbonos();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.forma_pago = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        
        this.filtrarAbonos();
    }

    public filtrarAbonos(){
        this.loading = true;
        this.apiService.getAll('compras/abonos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(abonos => { 
                this.abonos = abonos;
                this.loading = false;
                this.closeModal();
            }, error => {this.alertService.error(error); });
    }

    public setEstado(cotizacion:any){
        this.apiService.store('compras/abonos/change-estado', cotizacion)
            .pipe(this.untilDestroyed())
            .subscribe(cotizacion => { 
                this.alertService.success('Orden de compra actualizada', 'La orden de compra fue actualizada exitosamente.');
            }, error => {this.alertService.error(error); });
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('orden-de-compra/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                    for (let i = 0; i < this.abonos['data'].length; i++) { 
                        if (this.abonos['data'][i].id == data.id )
                            this.abonos['data'].splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
                   
        }

    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public reemprimir(abono:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + abono.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // Editar

    openModalEdit(template: TemplateRef<any>, abono:any) {
        this.abono = abono;
        
        this.apiService.getAll('documentos')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
            }, error => {this.alertService.error(error);});

        this.openModal(template);
    }

    public onSubmit() {
        this.loading = true;            
        this.apiService.store('compra/abono', this.abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
            this.abono = {};
            this.closeModal();
            this.loading = false;
            this.alertService.success('Abono guardado', 'El abono fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });
        this.openModal(template);
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('compras/abonos/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'abonos-proveedores.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    generarPartidaContable(abono:any){
        this.apiService.store('contabilidad/partida/cxp', abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
        },error => {this.alertService.error(error);});
    }


}
