import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { EditarAbonoComponent } from '@shared/modals/editar-abono/editar-abono.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import {
    FeCrErrorEmisionPayload,
    mensajeErrorHttpFeCr,
} from '@services/facturacion-electronica/fe-cr-http-error.util';
import { AlertsHaciendaComponent } from '@shared/parts/alerts-hacienda/alerts-hacienda.component';
import { FeCrEmisionAvanzadoComponent } from '@shared/parts/fe-cr-emision-avanzado/fe-cr-emision-avanzado.component';
import { FeEnviarCorreoComponent } from '@shared/parts/fe-enviar-correo/fe-enviar-correo.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

@Component({
    selector: 'app-abonos-ventas',
    templateUrl: './abonos-ventas.component.html',
    standalone: true,
    imports: [
        CommonModule,
        PipesModule,
        RouterModule,
        FormsModule,
        NgSelectModule,
        PaginationComponent,
        TruncatePipe,
        PopoverModule,
        TooltipModule,
        LazyImageDirective,
        EditarAbonoComponent,
        CurrencyPipe,
        AlertsHaciendaComponent,
        FeCrEmisionAvanzadoComponent,
        FeEnviarCorreoComponent,
        NotificacionesContainerComponent,
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,

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
    public contabilidadHabilitada: boolean = false;
    public sending = false;
    public correoEnvioDte = '';
    private readonly countryI18n = inject(CountryI18nService);

    private static readonly TIPOS_REP = ['01', '04', '07', '08', '10', '20'];

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private funcionalidadesService: FuncionalidadesService,
        private facturacionElectronica: FacturacionElectronicaService,
        private cdr: ChangeDetectorRef
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
        this.verificarAccesoContabilidad();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }
        this.cdr.markForCheck();
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
        this.filtros.page = 1;

        this.filtrarAbonos(false);
    }

    /** @param resetPage true al buscar/filtrar/ordenar/cambiar paginate; false al paginar o tras loadAll. */
    public filtrarAbonos(resetPage = true): void {
        if (resetPage) {
            this.filtros.page = 1;
        }
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.getAll('ventas/abonos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(abonos => {
                this.abonos = abonos;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
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

  public override setPagination(event:any):void{
    this.filtros.page = event.page;
    this.filtrarAbonos(false);
  }

    public imprimir(abono:any){
        window.open(this.apiService.baseUrl + '/api/venta/abono/imprimir/' + abono.id + '?token=' + this.apiService.auth_token());
    }

    openModalEdit(template: TemplateRef<any>, abono:any) {
        this.openModal(template, abono);
    }

    public onAbonoSaved() {
        this.closeModal();
        this.filtrarAbonos(false);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => {
                this.clientes = clientes;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
                this.formaPagos = formaPagos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        if (!this.documentos.length) {
            this.apiService.getAll('documentos/list-nombre')
                .pipe(this.untilDestroyed())
                .subscribe(
                    (documentos) => {
                        this.documentos = documentos;
                        this.cdr.markForCheck();
                    },
                    (error) => {
                        this.alertService.error(error);
                        this.cdr.markForCheck();
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

    verificarAccesoContabilidad() {
        this.funcionalidadesService.verificarAcceso('contabilidad')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (acceso) => {
                    this.contabilidadHabilitada = !!acceso;
                    this.cdr.markForCheck();
                },
                error: () => {
                    this.contabilidadHabilitada = false;
                    this.cdr.markForCheck();
                }
            });
    }

    puedeEmitirRep(abono: any): boolean {
        if (!this.facturacionElectronica.isCostaRicaFe() || !abono?.id) {
            return false;
        }
        if (abono.sello_mh || abono.codigo_generacion) {
            return false;
        }
        const venta = abono.venta;
        if (!venta) {
            return false;
        }
        // "Crédito" → crédito; sin normalizar, includes('cred') falla por la é.
        const condicion = String(venta.condicion || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/\p{M}/gu, '');
        if (!condicion.includes('cred')) {
            return false;
        }
        const clave = String(venta.codigo_generacion || venta.sello_mh || '').trim();
        if (clave.length < 50) {
            return false;
        }
        const tipo = String(venta.tipo_dte || '').trim();
        return AbonosVentasComponent.TIPOS_REP.includes(tipo);
    }

    puedeVerRep(abono: any): boolean {
        return this.facturacionElectronica.isCostaRicaFe()
            && !!abono?.id
            && !!(abono.sello_mh || abono.codigo_generacion);
    }

    openDTE(template: TemplateRef<any>, abono: any): void {
        this.correoEnvioDte = '';
        this.abono = { ...abono, errores: null, fe_cr_intento_emision: abono?.fe_cr_intento_emision };
        this.openModal(template, this.abono);
        this.alertService.modal = true;
        if (!this.abono.sello_mh && !this.abono.codigo_generacion) {
            this.emitirDTE();
        }
        this.cdr.markForCheck();
    }

    imprimirDTEPDF(abono: any): void {
        window.open(
            this.apiService.baseUrl + '/api/reporte/dte/' + abono.id + '/10/?tipo=abono&token=' + this.apiService.auth_token(),
            'hola',
            'width=400'
        );
    }

    imprimirDTEXML(abono: any): void {
        window.open(
            this.apiService.baseUrl + '/api/reporte/dte-xml/' + abono.id + '/10/?tipo=abono&token=' + this.apiService.auth_token(),
            'hola',
            'width=400'
        );
    }

    /** Envío automático tras emitir: correo escrito o el del cliente de la venta. */
    private tieneCorreoDestino(abono: any): boolean {
        return !!((this.correoEnvioDte ?? '').trim() || (abono?.venta?.cliente?.correo ?? '').trim());
    }

    enviarDTE(abono: any): void {
        const correo = (this.correoEnvioDte ?? '').trim();
        if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            this.alertService.error('Escriba un correo válido o déjelo vacío para usar el del cliente.');
            return;
        }
        this.sending = true;
        this.cdr.markForCheck();
        const payload: Record<string, unknown> = {
            id: abono.id,
            tipo_dte: '10',
            tipo: 'abono',
        };
        if (correo) {
            payload['correo'] = correo;
        }
        this.apiService.store('enviarDTE', payload)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.alertService.success(this.countryI18n.fe('sendSuccessTitle'), this.countryI18n.fe('sendSuccessBody'));
                    this.sending = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.sending = false;
                    this.cdr.markForCheck();
                },
            });
    }

    private esPayloadErrorEmisionFeCr(e: unknown): e is FeCrErrorEmisionPayload {
        return (
            typeof e === 'object'
            && e !== null
            && 'message' in e
            && 'documento' in e
            && typeof (e as FeCrErrorEmisionPayload).message === 'string'
        );
    }

    private sincronizarAbonoEnLista(abono: any): void {
        if (!abono?.id || !this.abonos?.data) {
            return;
        }
        const idx = this.abonos.data.findIndex((a: any) => a.id === abono.id);
        if (idx !== -1) {
            this.abonos.data[idx] = { ...this.abonos.data[idx], ...abono };
        }
    }

    emitirDTE(): void {
        if (!this.abono?.id || this.saving) {
            return;
        }
        if (!this.puedeEmitirRep(this.abono) && !this.abono.errores && !this.abono.fe_cr_intento_emision) {
            return;
        }
        this.saving = true;
        this.cdr.markForCheck();
        const ventaCtx = this.abono.venta;
        const conCorreoDestino = this.tieneCorreoDestino(this.abono);
        this.facturacionElectronica.emitirDTEReciboPagoAbono(this.abono).then((actualizado) => {
            this.abono = {
                ...actualizado,
                venta: actualizado?.venta ?? ventaCtx,
                errores: null,
                fe_cr_intento_emision: undefined,
            };
            this.sincronizarAbonoEnLista(this.abono);
            this.alertService.success('REP emitido', 'El recibo electrónico de pago fue aceptado por Hacienda.');
            this.saving = false;
            this.cdr.markForCheck();
            if (conCorreoDestino) {
                this.enviarDTE(this.abono);
            }
        }).catch((error: unknown) => {
            this.saving = false;
            if (error && typeof error === 'object' && 'abono' in error && (error as { abono?: unknown }).abono) {
                const a = (error as { abono: any }).abono;
                this.abono = { ...a, venta: a?.venta ?? ventaCtx };
                this.sincronizarAbonoEnLista(this.abono);
            }
            let msg: string;
            let feCrIntento: FeCrErrorEmisionPayload | undefined;
            if (this.esPayloadErrorEmisionFeCr(error)) {
                msg = error.message;
                feCrIntento = error;
            } else if (typeof error === 'string') {
                msg = error;
            } else {
                msg = mensajeErrorHttpFeCr(error);
            }
            this.abono = {
                ...this.abono,
                venta: this.abono.venta ?? ventaCtx,
                errores: msg,
                ...(feCrIntento ? { fe_cr_intento_emision: feCrIntento } : {}),
            };
            this.sincronizarAbonoEnLista(this.abono);
            this.alertService.info(
                'Comprobante no emitido',
                'Revise los problemas indicados en esta ventana.'
            );
            this.cdr.detectChanges();
        });
    }

}
