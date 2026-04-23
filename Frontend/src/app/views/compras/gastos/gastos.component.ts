import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import {
  mensajeErrorHttpFeCr,
  type FeCrErrorEmisionPayload,
} from '@services/facturacion-electronica/fe-cr-http-error.util';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertsHaciendaComponent } from '@shared/parts/alerts-hacienda/alerts-hacienda.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { CrearAbonoGastoComponent } from '@shared/modals/crear-abono-gasto/crear-abono-gasto.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import { esTipoFacturaElectronicaCompraCr } from '@views/ventas/documentos/documento-nombre-options';

@Component({
    selector: 'app-gastos',
    templateUrl: './gastos.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, CrearAbonoGastoComponent, LazyImageDirective, AlertsHaciendaComponent, NotificacionesContainerComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})

export class GastosComponent extends BaseCrudComponent<any> implements OnInit {

    public gastos:any = [];
    public gasto:any = {};
    public override saving:boolean = false;
    public sending:boolean = false;
    public consulting:boolean = false;
    public downloading:boolean = false;
    public clientes:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public sucursales:any = [];
    public proveedores:any = [];
    public areas:any = [];
    public numeros_ids:any = [];
    public override modalRef!: BsModalRef;
    public contabilidadHabilitada: boolean = false;

    constructor(
        apiService: ApiService,
        private facturacionElectronica: FacturacionElectronicaService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router,
        private route: ActivatedRoute,
        private modalService: BsModalService,
        private funcionalidadesService: FuncionalidadesService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'gasto',
            itemsProperty: 'gastos',
            itemProperty: 'gasto',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El gasto fue cambiado exitosamente.',
                updated: 'El gasto fue cambiado exitosamente.',
                createTitle: 'Gasto guardado',
                updateTitle: 'Gasto guardado'
            },
            afterSave: (item) => {
                this.gasto = item;
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarGastos();
    }

    private parseOptionalIdParam(params: Record<string, string | undefined>, key: string): number | null {
        const raw = params[key];
        if (raw === undefined || raw === null || raw === '') {
            return null;
        }
        const n = Number(raw);
        return Number.isFinite(n) && n > 0 ? n : null;
    }

    private filtrosParaApi(): Record<string, unknown> {
        const out: Record<string, unknown> = {};
        for (const key of Object.keys(this.filtros)) {
            const v = this.filtros[key];
            if (v !== '' && v !== null && v !== undefined) {
                out[key] = v;
            }
        }
        return out;
    }

    public override setPagination(event: any): void {
        this.filtros.page = event.page;
        this.filtrarGastos();
    }

    ngOnInit() {
        this.verificarAccesoContabilidad();
        this.route.queryParams
            .pipe(this.untilDestroyed())
            .subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_proyecto: +params['id_proyecto'] || '',
                id_documento: +params['id_documento'] || '',
                id_proveedor: this.parseOptionalIdParam(params, 'id_proveedor'),
                id_sucursal: +params['id_sucursal'] || '',
                id_usuario: +params['id_usuario'] || '',
                forma_pago: params['forma_pago'] || '',
                tipo: params['tipo'] || '',
                dte: params['dte'] || '',
                estado: params['estado'] || '',
                id_area_empresa: +params['id_area_empresa'] || '',
                inicio: params['inicio'] || '',
                fin: params['fin'] || '',
                num_identificacion: params['num_identificacion'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: +params['paginate'] || 10,
                page: +params['page'] || 1,
            };

            this.filtrarGastos();
            this.cdr.markForCheck();
        });

        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => {
                this.proveedores = proveedores;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('area-empresa/list')
            .pipe(this.untilDestroyed())
            .subscribe(areas => {
                this.areas = areas;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = null;
        this.filtros.id_usuario = '';
        this.filtros.id_proyecto = '';
        this.filtros.forma_pago = '';
        this.filtros.dte = '';
        this.filtros.estado = '';
        this.filtros.tipo = '';
        this.filtros.id_area_empresa = '';
        this.filtros.buscador = '';
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.filtros.num_identificacion = '';

        this.loading = true;
        this.filtrarGastos();
        this.getNumsIds();
    }

    public filtrarGastos(){
        // Limpiar valores vacíos antes de navegar
        const queryParams: any = {};
        Object.keys(this.filtros).forEach(key => {
            const value = this.filtros[key];
            if (value !== '' && value !== null && value !== undefined) {
                queryParams[key] = value;
            }
        });

        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: queryParams,
        });

        this.loading = true;

        if(!this.filtros.id_usuario){
            this.filtros.id_usuario = '';
        }

        this.apiService.getAll('gastos', this.filtrosParaApi())
            .pipe(this.untilDestroyed())
            .subscribe(gastos => {
                this.gastos = gastos;
                this.loading = false;
                this.closeModal();
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
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

    public setEstado(gasto:any){
        this.gasto = gasto;
        this.onSubmit();
    }

    public async setRecurrencia(gasto:any){
        this.gasto = gasto;
        this.gasto.recurrente = true;

        try {
            await this.apiService.store('gasto', this.gasto)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.gasto = {};
            this.alertService.success('Gasto guardado', 'El gasto se marco como recurrente exitosamente.');
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

    public descargar(){
        this.downloading = true;
        this.apiService.export('gastos/exportar', this.filtrosParaApi())
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
          }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

    public openFilter(template: TemplateRef<any>) {
        if(!this.sucursales.length){
            this.apiService.getAll('sucursales/list')
                .pipe(this.untilDestroyed())
                .subscribe(sucursales => {
                    this.sucursales = sucursales;
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); });
        }

        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/list')
                .pipe(this.untilDestroyed())
                .subscribe(usuarios => {
                    this.usuarios = usuarios;
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); });
        }

        if(!this.proyectos.length &&
            this.apiService.auth_user().empresa.modulo_proyectos &&
            this.isColumnEnabled('columna_proyecto')){
             this.apiService.getAll('proyectos/list')
                 .pipe(this.untilDestroyed())
                 .subscribe(proyectos => {
                     this.proyectos = proyectos;
                     this.cdr.markForCheck();
                 }, error => {this.alertService.error(error); });
         }

        this.openModal(template);
    }

    public isColumnEnabled(columnName: string): boolean {
        return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
    }

    public getNumsIds() {
        this.apiService.getAll('gastos/nums-ids')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (numsIds) => {
                    this.numeros_ids = numsIds;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
    }

    openDTE(template: TemplateRef<any>, gasto: any) {
        this.openModal(template, gasto);
        this.alertService.modal = true;
        if (!this.gasto.dte) {
            this.emitirDTE();
        }
    }

    puedeEmitirFeGasto(gasto: any): boolean {
        if (this.facturacionElectronica.isCostaRicaFe()) {
            return esTipoFacturaElectronicaCompraCr(gasto.tipo_documento);
        }
        return gasto.tipo_documento === 'Sujeto excluido';
    }

    esFeCostaRica(): boolean {
        return this.facturacionElectronica.isCostaRicaFe();
    }

    private esPayloadErrorEmisionFeCr(e: unknown): e is FeCrErrorEmisionPayload {
        return (
            typeof e === 'object' &&
            e !== null &&
            'message' in e &&
            'documento' in e &&
            typeof (e as FeCrErrorEmisionPayload).message === 'string'
        );
    }

    imprimirDTEPDF(gasto:any){
        const tipoRuta = this.esFeCostaRica() ? '08' : '03';
        const q = this.esFeCostaRica() ? '?tipo=gasto&token=' : '?token=';
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + gasto.id + '/' + tipoRuta + '/' + q + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(gasto:any){
        const tipoRuta = this.esFeCostaRica() ? '08' : '03';
        const q = this.esFeCostaRica() ? '?tipo=gasto&token=' : '?token=';
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + gasto.id + '/' + tipoRuta + '/' + q + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEXML(gasto: any) {
        const tipoRuta = this.esFeCostaRica() ? '08' : '03';
        const q = this.esFeCostaRica() ? '?tipo=gasto&token=' : '?token=';
        window.open(this.apiService.baseUrl + '/api/reporte/dte-xml/' + gasto.id + '/' + tipoRuta + '/' + q + this.apiService.auth_token(), 'hola', 'width=400');
    }

    consultarDTE(): void {
        if (this.esFeCostaRica()) {
            this.consultarDTECostaRica();
            return;
        }
        this.alertService.info('Consultar estado', 'Use el flujo de facturación de su país.');
    }

    consultarDTECostaRica(): void {
        this.consulting = true;
        this.cdr.markForCheck();
        this.facturacionElectronica
            .consultarEstadoFeCrGasto(this.gasto.id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (res: any) => {
                    if (res?.gasto) {
                        this.gasto = { ...res.gasto };
                        const idx = this.gastos.data?.findIndex((g: any) => g.id === res.gasto.id);
                        if (idx !== undefined && idx !== -1 && this.gastos.data) {
                            this.gastos.data[idx] = { ...res.gasto };
                        }
                    }
                    const ok = !!res?.detalle_estado?.success;
                    const messages = res?.detalle_estado?.messages;
                    if (res?.rechazado) {
                        this.alertService.warning(
                            'Comprobante rechazado en Hacienda',
                            typeof messages === 'string' && messages
                                ? messages
                                : 'Se quitó la clave en el sistema; corrija los datos y vuelva a emitir.'
                        );
                    } else if (ok) {
                        this.alertService.success('Estado en Hacienda', 'Comprobante aceptado.');
                    } else {
                        this.alertService.info(
                            'Estado en Hacienda',
                            typeof messages === 'string' && messages
                                ? messages
                                : 'Aún no consta como aceptado o está en proceso.'
                        );
                    }
                    this.consulting = false;
                    this.cdr.markForCheck();
                },
                error: (err) => {
                    this.consulting = false;
                    this.alertService.error(err);
                    this.cdr.markForCheck();
                },
            });
    }

    emitirDTE(){
        this.saving = true;
        this.cdr.markForCheck();
        this.facturacionElectronica.emitirDTESujetoExcluidoGasto(this.gasto).then((gasto: any) => {
            this.gasto = gasto;
            const idx = this.gastos.data?.findIndex((g: any) => g.id === gasto.id);
            if (idx !== undefined && idx !== -1 && this.gastos.data) {
                this.gastos.data[idx] = { ...gasto };
            }
            if (this.facturacionElectronica.requiereFlujoEnviarDteSeparado()) {
                this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
                this.saving = false;
                this.enviarDTE();
            } else {
                const aceptada = gasto?.dte?.cr?.aceptada;
                if (aceptada === false) {
                    this.alertService.info(
                        'Comprobante enviado',
                        'Hacienda aún no lo marca como aceptado. Revise el estado en unos momentos.'
                    );
                } else {
                    this.alertService.success('Comprobante electrónico', 'Enviado a Hacienda.');
                }
                this.saving = false;
                setTimeout(() => this.closeModal(), 1500);
            }
            this.cdr.markForCheck();
        }).catch((error: unknown) => {
            this.saving = false;
            if (error && typeof error === 'object' && 'gasto' in error && (error as { gasto?: unknown }).gasto) {
                const g = (error as { gasto: any }).gasto;
                this.gasto = { ...g };
                const i = this.gastos.data?.findIndex((x: any) => x.id === g.id);
                if (i !== undefined && i !== -1 && this.gastos.data) {
                    this.gastos.data[i] = { ...g };
                }
            }
            let msg: string;
            let feCrIntento: FeCrErrorEmisionPayload | undefined;
            if (this.esPayloadErrorEmisionFeCr(error)) {
                msg = error.message;
                feCrIntento = error;
            } else if (typeof error === 'string') {
                msg = error;
            } else {
                msg = this.esFeCostaRica()
                    ? mensajeErrorHttpFeCr(error)
                    : String((error as { message?: unknown })?.message ?? error);
            }
            if ((error as { status?: number })?.status && !this.esFeCostaRica()) {
                this.alertService.warning('Hubo un problema', error);
            } else {
                this.gasto = {
                    ...this.gasto,
                    errores: msg,
                    ...(feCrIntento ? { fe_cr_intento_emision: feCrIntento } : {}),
                };
                const j = this.gastos.data?.findIndex((v: any) => v.id === this.gasto.id);
                if (j !== undefined && j !== -1 && this.gastos.data) {
                    this.gastos.data[j] = { ...this.gasto };
                }
                if (this.esFeCostaRica()) {
                    this.alertService.info(
                        'Comprobante no emitido',
                        feCrIntento
                            ? 'Revise el mensaje abajo. Abra «XML del comprobante» (recomendado) o «JSON interno» si necesita depurar.'
                            : 'Revise el mensaje en el recuadro de esta ventana.'
                    );
                } else {
                    this.alertService.warning('Comprobante electrónico', msg);
                }
            }
            this.cdr.detectChanges();
        });
    }

    enviarDTE(){
        this.sending = true;
        this.gasto.tipo = 'gasto';
        this.apiService.store('enviarDTE', this.gasto)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
                    this.sending = false;
                    setTimeout(() => {
                        this.closeModal();
                    }, 5000);
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.sending = false;
                }
            });
    }

    anularDTE(gasto:any){
        this.gasto = gasto;
        if (this.esFeCostaRica()) {
            if (confirm('¿Confirma anular el egreso?')) {
                gasto.estado = 'Anulada';
                this.onSubmit();
            }
            return;
        }
        if(gasto.dte){
            if (confirm('¿Confirma anular la gasto y el DTE?')) {
                this.gasto = gasto;
                this.saving = true;
                this.apiService.store('generarDTEAnuladoSujetoExcluidoGasto', this.gasto)
                    .pipe(this.untilDestroyed())
                    .subscribe({
                        next: (dte) => {
                            this.gasto.dte_invalidacion = dte;
                            this.facturacionElectronica.firmarDTE(dte)
                                .pipe(this.untilDestroyed())
                                .subscribe({
                                    next: (dteFirmado) => {
                                        this.gasto.dte_invalidacion.firmaElectronica = dteFirmado.body;
                                        this.facturacionElectronica.anularDTE(this.gasto, dteFirmado.body)
                                            .pipe(this.untilDestroyed())
                                            .subscribe({
                                                next: (dte) => {
                                                    if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                                        this.gasto.dte_invalidacion.sello = dte.selloRecibido;
                                                        this.gasto.estado = 'Anulada';
                                                        this.apiService.store('gasto', this.gasto)
                                                            .pipe(this.untilDestroyed())
                                                            .subscribe({
                                                                next: () => {
                                                                    // this.alertService.success('Compra guardada.');
                                                                },
                                                                error: (error) => {
                                                                    this.alertService.error(error);
                                                                    this.saving = false;
                                                                }
                                                            });
                                                    }
                                                    this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
                                                },
                                                error: (error) => {
                                                    if(error.error.descripcionMsg){
                                                        this.alertService.warning('Hubo un problema', error.error.descripcionMsg);
                                                    }
                                                    if(error.error.observaciones?.length > 0){
                                                        this.alertService.warning('Hubo un problema', error.error.observaciones);
                                                    }
                                                    this.saving = false;
                                                }
                                            });
                                    },
                                    error: (error) => {
                                        this.alertService.error(error);
                                        this.saving = false;
                                    }
                                });
                        },
                        error: (error) => {
                            this.alertService.error(error);
                            this.saving = false;
                        }
                    });
            }
        }
        else{
            if (confirm('¿Confirma anular la gasto?')){
                gasto.estado = 'Anulada';
                this.onSubmit();
            }
        }
    }

    public openAbono(template: TemplateRef<any>, gasto:any){
        this.gasto = gasto;
        this.modalRef = this.modalService.show(template);
    }

    generarPartidaContable(gasto:any){
        this.apiService.store('contabilidad/partida/gasto', gasto)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
    }

    verificarAccesoContabilidad() {
        this.funcionalidadesService.verificarAcceso('contabilidad')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (acceso) => {
                    this.contabilidadHabilitada = acceso;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    console.error('Error al verificar acceso a contabilidad:', error);
                    this.contabilidadHabilitada = false;
                }
            });
    }

}
