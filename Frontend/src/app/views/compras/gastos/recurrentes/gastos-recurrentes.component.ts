import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

declare var $:any;

@Component({
    selector: 'app-gastos-recurrentes',
    templateUrl: './gastos-recurrentes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class GastosRecurrentesComponent extends BaseCrudComponent<any> implements OnInit {

    public gastos:any = {};
    public gasto:any = {};
    public formaPagos:any = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public buscador:any = '';

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'gasto',
            itemsProperty: 'gastos',
            itemProperty: 'gasto',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La gasto fue guardada exitosamente.',
                updated: 'La gasto fue guardada exitosamente.',
                createTitle: 'Venta guardado',
                updateTitle: 'Venta guardado'
            },
            afterSave: () => {
                this.gasto = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarGastos();
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
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.id_canal = '';
        this.filtros.id_documento = '';
        this.filtros.recurrente = true;
        this.filtros.forma_pago = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarGastos();
    }

    public filtrarGastos(){
        this.loading = true;
        this.apiService.getAll('gastos', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
              next: (gastos) => {
                  this.gastos = gastos;
                  this.loading = false;
                  this.closeModal();
              },
              error: (error) => {
                  this.alertService.error(error);
                  this.loading = false;
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

        this.filtrarGastos();
    }

    public setEstado(gasto:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la gasto?')){
                this.gasto = gasto;
                this.gasto.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la gasto?')){
                this.gasto = gasto;
                this.gasto.estado = estado;
                this.onSubmit();
            }
        }
    }

    public async setRecurrencia(gasto:any){
        this.gasto = gasto;
        this.gasto.recurrente = false;
        
        try {
            await this.apiService.store('gasto', this.gasto)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.gasto = {};
            this.loadAll();
            this.alertService.success('Gasto guardada', 'La gasto se marco como no recurrente exitosamente.');
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
            const deletedItem = await this.apiService.delete('gasto/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.gastos.data?.findIndex((g: any) => g.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.gastos.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

    public openModalEdit(template: TemplateRef<any>, gasto:any) {
        this.gasto = gasto;

        this.apiService.getAll('documentos')
          .pipe(this.untilDestroyed())
          .subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago')
          .pipe(this.untilDestroyed())
          .subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });

        this.openModal(template, gasto);
    }

    public openFilter(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('gastos/filtrar/' + filtro + '/', txt)
          .pipe(this.untilDestroyed())
          .subscribe({
              next: (gastos) => {
                  this.gastos = gastos;
                  this.loading = false;
              },
              error: (error) => {
                  this.alertService.error(error);
                  this.loading = false;
              }
          });
    }

    public openDescargar(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public descargarGastos(){
        this.apiService.export('gastos/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos-recurrentes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar gastos:', error); }
        );
    }

    public descargarDetalles(){
        this.apiService.export('gastos-detalles/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar gastos:', error); }
        );
    }

}
