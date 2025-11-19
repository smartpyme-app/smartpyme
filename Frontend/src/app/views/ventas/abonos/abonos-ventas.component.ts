import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';


@Component({
    selector: 'app-abonos-ventas',
    templateUrl: './abonos-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, TruncatePipe, PopoverModule, TooltipModule, LazyImageDirective],
    
})

export class AbonosVentasComponent extends BasePaginatedModalComponent implements OnInit {

    public abonos: PaginatedResponse<any> = {} as PaginatedResponse;
    public abono:any = {};
    public downloading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public formaPagos:any = [];
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
        return this.abonos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.abonos = data;
    }

    ngOnInit() {

        this.loadAll();

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
        this.filtros.id_cliente = '';
        this.filtros.estado = '';
        this.filtros.forma_pago = '';
        this.filtros.id_documento = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarAbonos();
    }

    public filtrarAbonos(){
        this.loading = true;
        this.apiService.getAll('ventas/abonos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(abonos => { 
                this.abonos = abonos;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
            }, error => {this.alertService.error(error); });
    }

    public setEstado(abono:any){
        this.apiService.store('venta/abono/update', abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => { 
                this.alertService.success('Abono actualizado', 'El abono fue actualizado exitosamente.');
            }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('orden-de-venta/', id)
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

    public imprimir(abono:any){
        window.open(this.apiService.baseUrl + '/api/venta/abono/imprimir/' + abono.id + '?token=' + this.apiService.auth_token());
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
        this.apiService.store('venta/abono', this.abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
                this.abono = {};
                if (this.modalRef) {
                    this.closeModal();
                }
                this.loading = false;
                this.alertService.success('Abono guardado', 'El abono fue guardada exitosamente.');
            },error => {this.alertService.error(error); this.loading = false; });

    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => {
                this.clientes = clientes;
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });

        if (!this.documentos.length) {
            this.apiService.getAll('documentos/list-nombre')
                .pipe(this.untilDestroyed())
                .subscribe(
                    (documentos) => {
                        this.documentos = documentos;
                    },
                    (error) => {
                        this.alertService.error(error);
                    }
                );
        }

        this.openModal(template);
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('ventas/abonos/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'abonos-clientes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    generarPartidaContable(abono:any){
        this.apiService.store('contabilidad/partida/cxc', abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
                this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
            },error => {this.alertService.error(error);});
    }


}
