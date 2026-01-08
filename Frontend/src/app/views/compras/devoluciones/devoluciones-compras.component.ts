import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-devoluciones-compras',
    templateUrl: './devoluciones-compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class DevolucionesComprasComponent extends BaseCrudComponent<any> implements OnInit {

    public compras:any = {};
    public compra:any = {};
    public id_compra:any = null;
    public downloading:boolean = false;
    public proveedores:any = [];
    public usuarios:any = [];
    public comprasList:any = [];
    public sucursales:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'devolucion/compra',
            itemsProperty: 'compras',
            itemProperty: 'compra',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La devolución de compra fue actualizada exitosamente.',
                updated: 'La devolución de compra fue actualizada exitosamente.',
                createTitle: 'Compra actualizada',
                updateTitle: 'Compra actualizada'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarCompras();
    }

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => { 
                this.proveedores = proveedores;
            }, error => {this.alertService.error(error); });
    }

    public override loadAll() {
        this.loading = true;
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarCompras();
    }

    public filtrarCompras(){
        this.loading = true;
        this.apiService.getAll('devoluciones/compras', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(compras => { 
                this.compras = compras;
                this.loading = false;
                this.closeModal();
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public setEstado(compra:any, enable:string){
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, anularlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            this.compra = compra;
            this.compra.enable = enable;
            this.onSubmit();
          }
        });
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('devolucion/compra/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (deletedItem: any) => {
                    const index = this.compras.data?.findIndex((c: any) => c.id === deletedItem.id);
                    if (index !== -1 && index >= 0) {
                        this.compras.data.splice(index, 1);
                    }
                    this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (error: any) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            });
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

    openFilter(template: TemplateRef<any>) {     
        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => { 
                this.proveedores = proveedores;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => { 
                this.usuarios = usuarios;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.openModal(template);
    }

    override openModal(template: TemplateRef<any>) {
        this.id_compra = null;
        this.loading = true;
        this.apiService.getAll('compras/sin-devolucion')
            .pipe(this.untilDestroyed())
            .subscribe(compras => {
                this.comprasList = compras;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
        super.openModal(template);
    }

    public imprimir(compra:any){
        window.open(this.apiService.baseUrl + '/api/devolucion/facturacion/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('devoluciones/compras/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'devoluciones-compras.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
          }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

}
