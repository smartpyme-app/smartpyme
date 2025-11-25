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
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-abonos-ventas',
    templateUrl: './abonos-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, TruncatePipe, PopoverModule, TooltipModule, LazyImageDirective],
    
})

export class AbonosVentasComponent extends BaseCrudComponent<any> implements OnInit {

    public abonos:any = {};
    public abono:any = {};
    public downloading:boolean = false;
    public clientes:any = [];
    public usuarios:any = [];
    public formaPagos:any = [];
    public documentos:any = [];
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'venta/abono',
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
            }, error => {this.alertService.error(error); this.loading = false; });
    }

    public async setEstado(abono:any){
        try {
            await this.apiService.store('venta/abono/update', abono)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.alertService.success('Abono actualizado', 'El abono fue actualizado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        }
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            // Nota: El endpoint original usa 'orden-de-venta/' pero debería ser 'venta/abono/'
            const deletedItem = await this.apiService.delete('venta/abono/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.abonos.data?.findIndex((a: any) => a.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.abonos.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

    public imprimir(abono:any){
        window.open(this.apiService.baseUrl + '/api/venta/abono/imprimir/' + abono.id + '?token=' + this.apiService.auth_token());
    }

    openModalEdit(template: TemplateRef<any>, abono:any) {
        this.abono = abono;
        
        this.apiService.getAll('documentos')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
            }, error => {this.alertService.error(error);});

        this.openModal(template, abono);
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
