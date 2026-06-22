import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import {
  ExportLimiteTipo,
  ExportPeriodoState,
  MESES_EXPORT_PERIODO,
  MAX_DIAS_EXPORT_DETALLES,
  MAX_DIAS_EXPORT_VENTAS,
  aniosDisponiblesExportDesde,
  buildFechasExportValidadas,
  crearEstadoExportPeriodoDefault,
  diasEntreFechasIso,
  esErrorTimeoutExport,
  maxDiasExportPorTipo,
  mensajeErrorTimeoutExport,
  prefillExportPeriodoDesdeFiltros,
  validarPeriodoExport,
} from '../../../../helpers/export-period.helper';

declare var $:any;

@Component({
    selector: 'app-gastos-recurrentes',
    templateUrl: './gastos-recurrentes.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe],
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
    public downloadingGastos = false;
    public downloadingDetalles = false;

    public reporteSeleccionado = '';
    public exportPeriodo: ExportPeriodoState = crearEstadoExportPeriodoDefault();
    public readonly mesesExportPeriodo = MESES_EXPORT_PERIODO;
    public readonly aniosDisponiblesExport = aniosDisponiblesExportDesde();
    public readonly maxDiasExportPorTipoFn = maxDiasExportPorTipo;

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
        this.reporteSeleccionado = '';
        this.exportPeriodo = crearEstadoExportPeriodoDefault();
        this.openModal(template);
    }

    public cerrarModalDescargar(): void {
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }

    public get anioEnCursoParaMes(): number {
        return new Date().getFullYear();
    }

    public seleccionarReporteGastos(reporte: string): void {
        this.reporteSeleccionado = reporte;
        this.exportPeriodo = crearEstadoExportPeriodoDefault();
        prefillExportPeriodoDesdeFiltros(this.filtros, this.exportPeriodo);
    }

    public get tipoLimiteExportGastos(): ExportLimiteTipo | null {
        if (!this.reporteSeleccionado) return null;
        if (this.reporteSeleccionado === 'detalles') return 'detalles';
        return 'ventas';
    }

    public get puedeDescargarReporteGastos(): boolean {
        const tipo = this.tipoLimiteExportGastos;
        return !!tipo && buildFechasExportValidadas(this.exportPeriodo, tipo) !== null;
    }

    public rangoExportSuperaLimiteGastos(tipo: ExportLimiteTipo): boolean {
        if (buildFechasExportValidadas(this.exportPeriodo, tipo)) return false;
        const ini = this.exportPeriodo.rangoInicio?.trim();
        const fin = this.exportPeriodo.rangoFin?.trim();
        if (this.exportPeriodo.tipo === 'rango' && ini && fin && ini <= fin) {
            return diasEntreFechasIso(ini, fin) > maxDiasExportPorTipo(tipo);
        }
        return false;
    }

    public descargarReporteGastosSeleccionado(): void {
        const tipo = this.tipoLimiteExportGastos;
        if (!tipo) return;
        const fechas = buildFechasExportValidadas(this.exportPeriodo, tipo);
        if (!fechas) {
            const max = maxDiasExportPorTipo(tipo);
            const check = validarPeriodoExport(this.exportPeriodo.rangoInicio, this.exportPeriodo.rangoFin, max);
            this.alertService.error(check.error ?? 'Período inválido.');
            return;
        }
        const filtrosExport = { ...this.filtros, inicio: fechas.inicio, fin: fechas.fin };
        if (this.reporteSeleccionado === 'detalles') {
            this.descargarDetalles(filtrosExport);
        } else {
            this.descargarGastos(filtrosExport);
        }
    }

    public descargarGastos(filtrosExport?: Record<string, unknown>){
        const filtros = filtrosExport ?? { ...this.filtros };
        this.downloadingGastos = true;
        this.saving = true;
        this.apiService.export('gastos/exportar', filtros)
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
            this.downloadingGastos = false;
            this.saving = false;
            this.cerrarModalDescargar();
          }, (error) => {
            if (esErrorTimeoutExport(error)) {
                this.alertService.error(mensajeErrorTimeoutExport(MAX_DIAS_EXPORT_VENTAS));
            } else {
                this.alertService.error(error);
            }
            this.downloadingGastos = false;
            this.saving = false;
          }
        );
    }

    public descargarDetalles(filtrosExport?: Record<string, unknown>){
        const filtros = filtrosExport ?? { ...this.filtros };
        this.downloadingDetalles = true;
        this.saving = true;
        this.apiService.export('gastos-detalles/exportar', filtros)
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
            this.downloadingDetalles = false;
            this.saving = false;
            this.cerrarModalDescargar();
          }, (error) => {
            if (esErrorTimeoutExport(error)) {
                this.alertService.error(mensajeErrorTimeoutExport(MAX_DIAS_EXPORT_DETALLES));
            } else {
                this.alertService.error(error);
            }
            this.downloadingDetalles = false;
            this.saving = false;
          }
        );
    }

}
