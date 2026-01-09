import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-abonos-compras',
    templateUrl: './abonos-compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PopoverModule, TooltipModule, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class AbonosComprasComponent extends BaseCrudComponent<any> implements OnInit {

    public abonos:any = {};
    public abono:any = {};
    public downloading:boolean = false;
    public formaPagos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public documentos:any = [];
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'compra/abono',
            itemsProperty: 'abonos',
            itemProperty: 'abono',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El abono fue guardada exitosamente.',
                updated: 'El abono fue guardada exitosamente.',
                createTitle: 'Abono guardado',
                updateTitle: 'Abono guardado'
            },
            afterSave: () => {
                this.abono = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarAbonos();
    }

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('proveedores/list').pipe(this.untilDestroyed()).subscribe(proveedores => { 
            this.proveedores = proveedores;
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

        this.filtrarAbonos();
    }

    public override loadAll() {
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
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public async setEstado(cotizacion:any){
        try {
            await this.apiService.store('compras/abonos/change-estado', cotizacion)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.alertService.success('Orden de compra actualizada', 'La orden de compra fue actualizada exitosamente.');
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        }
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            // Nota: El endpoint original usa 'orden-de-compra/' pero debería ser 'compra/abono/'
            const deletedItem = await this.apiService.delete('compra/abono/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.abonos.data?.findIndex((a: any) => a.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.abonos.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

    public reemprimir(abono:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + abono.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    openModalEdit(template: TemplateRef<any>, abono:any) {
        this.abono = abono;
        
        this.apiService.getAll('documentos')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.openModal(template, abono);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
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
            this.cdr.markForCheck();
          }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

    generarPartidaContable(abono:any){
        this.apiService.store('contabilidad/partida/cxp', abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.cdr.markForCheck();});
    }

}
