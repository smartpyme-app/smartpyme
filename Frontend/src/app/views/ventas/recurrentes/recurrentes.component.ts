import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-recurrentes',
    templateUrl: './recurrentes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, TruncatePipe, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,

})

export class RecurrentesComponent extends BaseCrudComponent<any> implements OnInit {

    public ventas:any = {};
    public venta:any = {};
    public override saving:boolean = false;
    public downloading:boolean = false;
    public clientes:any = [];
    public usuario:any = {};
    public usuarios:any = [];
    public sucursales:any = [];
    public formaPagos:any = [];
    public documentos:any = [];
    public canales:any = [];
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'venta',
            itemsProperty: 'ventas',
            itemProperty: 'venta',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La venta fue guardada exitosamente.',
                updated: 'La venta fue guardada exitosamente.',
                createTitle: 'Venta guardada',
                updateTitle: 'Venta guardada'
            },
            afterSave: () => {
                this.venta = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarVentas();
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }
        this.cdr.markForCheck();
        this.filtrarVentas();
    }

    public override loadAll() {
        const filtrosGuardados = localStorage.getItem('ventasRecurrentesFiltros');

        if (filtrosGuardados) {
            this.filtros = JSON.parse(filtrosGuardados);
          } else {
            this.filtros.id_sucursal = '';
            this.filtros.id_cliente = '';
            this.filtros.id_usuario = '';
            this.filtros.id_canal = '';
            this.filtros.id_documento = '';
            this.filtros.forma_pago = '';
            this.filtros.recurrente = true;
            this.filtros.estado = '';
            this.filtros.buscador = '';
            this.filtros.orden = 'fecha';
            this.filtros.direccion = 'desc';
            this.filtros.paginate = 10;
          }

        this.filtrarVentas();
    }

    public filtrarVentas(){
        localStorage.setItem('ventasRecurrentesFiltros', JSON.stringify(this.filtros));
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.getAll('ventas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(ventas => {
                this.ventas = ventas;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public async setEstado(venta:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la venta?')){
                venta.estado = estado;
                await this.onSubmit(venta, true);
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la venta?')){
                venta.estado = estado;
                await this.onSubmit(venta, true);
            }
        }
    }

    public async setRecurrencia(venta:any){
        this.venta = venta;
        this.venta.recurrente = false;

        try {
            await this.apiService.store('venta', this.venta)
                .pipe(this.untilDestroyed())
                .toPromise();

            this.venta = {};
            this.loadAll();
            this.alertService.success('Venta guardada', 'La venta se marco como no recurrente exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
            this.saving = false;
        }
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;

        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            const deletedItem = await this.apiService.delete('venta/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();

            const index = this.ventas.data?.findIndex((v: any) => v.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.ventas.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

    public reemprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public openModalEdit(template: TemplateRef<any>, venta:any) {
        this.venta = venta;

        this.apiService.getAll('documentos')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('formas-de-pago')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
                this.formaPagos = formaPagos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.openModal(template, venta);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => {
                this.clientes = clientes;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('formas-de-pago')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
                this.formaPagos = formaPagos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('documentos/list-nombre')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('canales')
            .pipe(this.untilDestroyed())
            .subscribe(canales => {
                this.canales = canales;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.openModal(template);
    }

    public openDescargar(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('ventas/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas-recurrentes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => {this.alertService.error(error); this.downloading = false;}
        );
    }

    public descargarVentas(){
        this.apiService.export('ventas/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar ventas:', error); }
        );
    }

    public descargarDetalles(){
        this.apiService.export('ventas-detalles/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar ventas:', error); }
        );
    }

    public imprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public linkWompi(venta:any){
        window.open(this.apiService.baseUrl + '/api/venta/wompi-link/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public setDocumento(id_documento: any) {
        let documento = this.documentos.find((x: any) => x.id == id_documento);
        if (documento) {
            this.venta.nombre_documento = documento.nombre;
            this.venta.id_documento = documento.id;
            this.venta.correlativo = documento.correlativo;
        }
    }

    public override async onSubmit(item?: any, isStatusChange?: boolean): Promise<void> {
        const ventaToSave = item || this.venta;
        this.saving = true;
        this.cdr.markForCheck();
        try {
            const venta = await this.apiService.store('venta', ventaToSave)
                .pipe(this.untilDestroyed())
                .toPromise();
            this.venta = {};
            this.saving = false;
            if(this.modalRef){
                this.closeModal();
            }
            this.alertService.success('Venta guardada', 'La venta fue guardada exitosamente.');
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.saving = false;
            this.cdr.markForCheck();
        }
    }

    public limpiarFiltros() {
        localStorage.removeItem('ventasRecurrentesFiltros');
        this.loadAll();
    }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.openModal(template, venta);
    }

}
