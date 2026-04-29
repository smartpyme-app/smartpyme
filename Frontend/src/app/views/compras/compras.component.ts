import { Component, OnInit, OnDestroy, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { CompraJsonBulkService } from '@services/compra-json-bulk.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import {
  mensajeErrorHttpFeCr,
  type FeCrErrorEmisionPayload,
} from '@services/facturacion-electronica/fe-cr-http-error.util';
import { SharedDataService } from '@services/shared-data.service';
import { AlertsHaciendaComponent } from '@shared/parts/alerts-hacienda/alerts-hacienda.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../directives/lazy-image.directive';
import { Subject } from 'rxjs';

/** Debe coincidir con el slug en Backend (FuncionalidadesSeeder / verificar-acceso). */
const SLUG_IMPORTACION_MASIVA_COMPRAS_JSON = 'importacion-masiva-compras-json';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { esTipoFacturaElectronicaCompraCr } from '@views/ventas/documentos/documento-nombre-options';

declare var $:any;

@Component({
    selector: 'app-compras',
    templateUrl: './compras.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, LazyImageDirective, AlertsHaciendaComponent, NotificacionesContainerComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})

export class ComprasComponent extends BaseCrudComponent<any> implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private searchSubject$ = new Subject<void>();

    public compras:any = [];
    public compra:any = {};
    public formaPagos:any = [];
    /** Cuentas bancarias para el modal de edición (mismo origen que facturación de compra). */
    public bancos: any[] = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public sucursales:any = [];
    public buscador:any = '';
    public override saving:boolean = false;
    public sending:boolean = false;
    public consulting:boolean = false;
    public downloadingDetalles:boolean = false;
    public downloadingCompras:boolean = false;
    public modalRefAcumulado: any;
    public modalRefRentabilidad: any;
    public filtrosRentabilidad:any = {
        inicio: '',
        fin: '',
        sucursales: [],
        categorias: [],
        marcas: [],
    };
    public numeros_ids:any = [];
    public downloadingRentabilidad:boolean = false;
    public contabilidadHabilitada: boolean = false;

    protected aplicarFiltros(): void {
        this.filtrarCompras();
    }

    /** Importación masiva JSON (listado de compras) */
    public bulkModalRef!: BsModalRef;
    public bulkItems: BulkCompraItem[] = [];
    public bulkTabIndex = 0;
    public bulkProcesandoArchivos = false;
    public bulkGuardandoTodas = false;
    public impuestosCompra: any[] = [];
    public bodegasBulk: any[] = [];
    public readonly maxBulkJsonFiles = 20; // este es el limite de archivos que se pueden cargar
    /** Documentos de venta filtrados para compras (correlativo por tipo y sucursal) */
    public documentosBulk: any[] = [];
    public bulkSearchProductos$ = new Subject<string>();
    public bulkSearchResults: any[] = [];
    public bulkSearchLoading = false;
    public bulkSearchTerm = '';
    /** Importación masiva JSON en listado de compras (funcionalidad por empresa). */
    public permiteImportacionMasivaComprasJson = false;

    constructor(
        protected override apiService: ApiService,
        public mhService: MHService,
        private facturacionElectronica: FacturacionElectronicaService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private modalService: BsModalService,
        private router: Router,
        private route: ActivatedRoute,
        private sharedDataService: SharedDataService,
        private compraJsonBulk: CompraJsonBulkService,
        private funcionalidadesService: FuncionalidadesService,
        private cdr: ChangeDetectorRef
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'compra',
            itemsProperty: 'compras',
            itemProperty: 'compra',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La compra fue guardada exitosamente.',
                updated: 'La compra fue guardada exitosamente.',
                deleted: 'Compra eliminada exitosamente.',
                createTitle: 'Compra guardada',
                updateTitle: 'Compra guardada',
                deleteTitle: 'Compra eliminada',
                deleteConfirm: '¿Desea eliminar el Registro?'
            },
            afterSave: () => {
                this.compra = {};
                this.filtrarCompras(false);
            }
        });
        this.bulkSearchProductos$
            .pipe(
                debounceTime(300),
                distinctUntilChanged(),
                switchMap((term) => {
                    if (!term || term.length < 2) {
                        return of([]);
                    }
                    this.bulkSearchLoading = true;
                    this.bulkSearchTerm = term;
                    return this.apiService
                        .store('productos/buscar-modal', {
                            termino: term,
                            id_empresa: this.apiService.auth_user().id_empresa,
                            limite: 15,
                        })
                        .pipe(catchError(() => of([])));
                }),
                takeUntil(this.destroy$)
            )
            .subscribe((results) => {
                this.bulkSearchResults = results || [];
                this.bulkSearchLoading = false;
            });
    }

  public override setPagination(event: any): void {
    this.filtros.page = event.page;
    this.filtrarCompras(false);
  }

    ngOnDestroy() {
        this.destroy$.next();
        this.destroy$.complete();
    }

    private parseOptionalIdParam(params: Record<string, string | undefined>, key: string): number | null {
        const raw = params[key];
        if (raw === undefined || raw === null || raw === '') {
            return null;
        }
        const n = Number(raw);
        return Number.isFinite(n) && n > 0 ? n : null;
    }

    /** Parámetros listos para GET/export (sin null/undefined/'' para no confundir al backend ni la caché HTTP). */
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

    ngOnInit() {
        this.searchSubject$.pipe(
            debounceTime(400),
            takeUntil(this.destroy$)
        ).subscribe(() => this.filtrarCompras());
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
                dte: params['dte'] || '',
                estado: params['estado'] || '',
                inicio: params['inicio'] || '',
                fin: params['fin'] || '',
                num_identificacion: params['num_identificacion'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: +params['paginate'] || 10,
                page: +params['page'] || 1,
            };

            this.filtrarCompras(false);
            this.cdr.markForCheck();
        });

        this.getNumsIds();
        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });

        this.funcionalidadesService
            .verificarAcceso(SLUG_IMPORTACION_MASIVA_COMPRAS_JSON)
            .subscribe((ok) => {
                this.permiteImportacionMasivaComprasJson = !!ok;
            });
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = null;
        this.filtros.id_usuario = '';
        this.filtros.id_usuario = '';
        this.filtros.id_canal = '';
        this.filtros.id_documento = '';
        this.filtros.id_proyecto = '';
        this.filtros.forma_pago = '';
        this.filtros.dte = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.filtros.num_identificacion = '';

        this.filtrarCompras(false);
    }

    public onBuscadorInput() {
        this.searchSubject$.next();
    }

    public getSaldo(compra: any): number {
        const total = parseFloat(compra?.total || 0);
        const abonos = parseFloat(compra?.abonos_sum_total || 0);
        const devoluciones = parseFloat(compra?.devoluciones_sum_total || 0);
        return Math.round((total - abonos - devoluciones) * 100) / 100;
    }

    /**
     * @param resetPage Si es true (por defecto), vuelve a la página 1 (búsqueda, filtros, orden, paginate).
     *                  false al paginar, sincronizar URL o refrescar tras guardar sin cambiar de página.
     */
    public filtrarCompras(resetPage = true): void {
        if (resetPage) {
            this.filtros.page = 1;
        }

        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtrosParaApi(),
        });

        this.loading = true;

        if(!this.filtros.id_usuario){
            this.filtros.id_usuario = '';
        }

        this.apiService.getAll('compras', this.filtrosParaApi())
            .pipe(this.untilDestroyed())
            .subscribe(compras => {
                this.compras = compras;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
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

        this.filtrarCompras();
    }

    public setEstado(compra: any, estado: any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la compra?')){
                compra.estado = estado;
                this.onSubmit(compra, true);
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la compra?')){
                compra.estado = estado;
                this.onSubmit(compra, true);
            }
        }
    }

    public override delete(id: number) {
        super.delete(id);
    }

    /** Sincroniza banco al cambiar forma de pago en el modal (igual que en facturación de compra). */
    public cambioMetodoDePagoCompraModal(): void {
        const fp = this.compra?.forma_pago;
        if (fp === 'Efectivo' || fp === 'Wompi') {
            this.compra.detalle_banco = '';
            this.cdr.markForCheck();
            return;
        }
        if (this.apiService.isModuloBancos() && fp) {
            const sel = this.formaPagos.find((f: any) => f.nombre === fp);
            if (sel?.banco?.nombre_banco) {
                this.compra.detalle_banco = sel.banco.nombre_banco;
            } else {
                this.compra.detalle_banco = '';
            }
        }
        this.cdr.markForCheck();
    }

    public openModalEdit(template: TemplateRef<any>, compra:any) {
        // Cargar los datos completos de la compra antes de abrir el modal
        this.loading = true;
        this.apiService.read('compra/', compra.id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (compraCompleta) => {
                    this.loading = false;
                    this.cdr.markForCheck();

                    // Cargar datos auxiliares
                    if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
                        this.sharedDataService.getProyectos()
                            .pipe(this.untilDestroyed())
                            .subscribe({
                                next: (proyectos) => {
                                    this.proyectos = proyectos;
                                    this.cdr.markForCheck();
                                },
                                error: (error) => {
                                    this.alertService.error(error);
                                }
                            });
                    }

                    this.sharedDataService.getDocumentos()
                        .pipe(this.untilDestroyed())
                        .subscribe({
                            next: (documentos) => {
                                this.documentos = documentos;
                                this.cdr.markForCheck();
                            },
                            error: (error) => {
                                this.alertService.error(error);
                            }
                        });

                    if(!this.formaPagos.length){
                        this.sharedDataService.getFormasDePago()
                            .pipe(this.untilDestroyed())
                            .subscribe({
                                next: (formaPagos) => {
                                    this.formaPagos = formaPagos;
                                    this.cdr.markForCheck();
                                },
                                error: (error) => {
                                    this.alertService.error(error);
                                }
                            });
                    }

                    if(!this.usuarios.length){
                        this.sharedDataService.getUsuarios()
                            .pipe(this.untilDestroyed())
                            .subscribe({
                                next: (usuarios) => {
                                    this.usuarios = usuarios;
                                    this.cdr.markForCheck();
                                },
                                error: (error) => {
                                    this.alertService.error(error);
                                }
                            });
                    }

                    const abrirModalEdicion = () => {
                        this.openModal(template, compraCompleta);
                        this.cdr.markForCheck();
                    };

                    if (!this.bancos.length) {
                        this.apiService.getAll('banco/cuentas/list')
                            .pipe(this.untilDestroyed())
                            .subscribe({
                                next: (cuentas) => {
                                    this.bancos = cuentas;
                                    abrirModalEdicion();
                                },
                                error: (err) => {
                                    this.alertService.error(err);
                                    abrirModalEdicion();
                                }
                            });
                    } else {
                        abrirModalEdicion();
                    }
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }


    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('compras/filtrar/' + filtro + '/', txt)
            .pipe(this.untilDestroyed())
            .subscribe(compras => {
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public setRecurrencia(compra:any){
        this.compra = compra;
        this.compra.recurrente = true;

        this.apiService.store('compra', this.compra)
            .pipe(this.untilDestroyed())
            .subscribe(compra => {
                this.compra = {};
                this.alertService.success('Compra guardada', 'La compra se marco como recurrente exitosamente.');
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck(); });

    }

    public openDescargar(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public descargarCompras(){
        this.downloadingCompras = true; this.saving = true;
        this.apiService.export('compras/exportar', this.filtrosParaApi())
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'compras.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloadingCompras = false; this.saving = false;
            this.cdr.markForCheck();
          }, (error) => {this.alertService.error(error); this.downloadingCompras = false; this.saving = false; this.cdr.markForCheck();}
        );
    }

    public descargarDetalles(){
        this.downloadingDetalles = true; this.saving = true;
        this.apiService.export('compras-detalles/exportar', this.filtrosParaApi())
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'compras-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloadingDetalles = false; this.saving = false;
            this.cdr.markForCheck();
          }, (error) => {this.alertService.error(error); this.downloadingDetalles = false; this.saving = false; this.cdr.markForCheck(); }
        );
    }

    public openAbono(template: TemplateRef<any>, compra: any){
      this.compra = { ...compra, saldo: this.getSaldo(compra) };
      this.alertService.modal = true;
      this.openModal(template);    }

    public openFilter(template: TemplateRef<any>) {

        this.sharedDataService.getDocumentos()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (documentos) => {
                    this.documentos = documentos;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });

        if(!this.formaPagos.length){
            this.sharedDataService.getFormasDePago()
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (formaPagos) => {
                        this.formaPagos = formaPagos;
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }

        if(!this.sucursales.length){
            this.sharedDataService.getSucursales()
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (sucursales) => {
                        this.sucursales = sucursales;
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }

        if(!this.usuarios.length){
            this.sharedDataService.getUsuarios()
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (usuarios) => {
                        this.usuarios = usuarios;
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }

        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.sharedDataService.getProyectos()
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (proyectos) => {
                        this.proyectos = proyectos;
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }

        this.openModal(template);
    }

    openDTE(template: TemplateRef<any>, compra: any) {
        /** Pasar `compra` a openModal: si solo se llama openModal(template), BaseCrudComponent pisa `this.compra` con un ítem nuevo sin `id`. */
        this.openModal(template, compra);
        this.alertService.modal = true;
        if (!this.compra.dte) {
            this.emitirDTE();
        }
    }

    /** SV: Sujeto excluido; CR: FEC 08 («Factura Electrónica de Compra» o nombre histórico). */
    puedeEmitirFeCompra(compra: any): boolean {
        if (this.facturacionElectronica.isCostaRicaFe()) {
            return esTipoFacturaElectronicaCompraCr(compra.tipo_documento);
        }
        return compra.tipo_documento === 'Sujeto excluido';
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

    imprimirDTEPDF(compra:any){
        const tipoRuta = this.esFeCostaRica() ? '08' : '14';
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + compra.id + '/' + tipoRuta + '/' + '?tipo=compra&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(compra:any){
        const tipoRuta = this.esFeCostaRica() ? '08' : '14';
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + compra.id + '/' + tipoRuta + '/' + '?tipo=compra&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEXML(compra: any) {
        const tipoRuta = this.esFeCostaRica() ? '08' : '14';
        window.open(this.apiService.baseUrl + '/api/reporte/dte-xml/' + compra.id + '/' + tipoRuta + '/' + '?tipo=compra&token=' + this.apiService.auth_token(), 'hola', 'width=400');
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
            .consultarEstadoFeCrCompra(this.compra.id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (res: any) => {
                    if (res?.compra) {
                        this.compra = { ...res.compra };
                        const idx = this.compras.data?.findIndex((c: any) => c.id === res.compra.id);
                        if (idx !== undefined && idx !== -1 && this.compras.data) {
                            this.compras.data[idx] = { ...res.compra };
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
        this.facturacionElectronica.emitirDTESujetoExcluidoCompra(this.compra).then((compra) => {
            this.compra = compra;
            const idx = this.compras.data?.findIndex((c: any) => c.id === compra.id);
            if (idx !== undefined && idx !== -1 && this.compras.data) {
                this.compras.data[idx] = { ...compra };
            }
            if (this.facturacionElectronica.requiereFlujoEnviarDteSeparado()) {
                this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
                this.saving = false;
                this.enviarDTE();
            } else {
                const aceptada = compra?.dte?.cr?.aceptada;
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
            if (error && typeof error === 'object' && 'compra' in error && (error as { compra?: unknown }).compra) {
                const c = (error as { compra: any }).compra;
                this.compra = { ...c };
                const i = this.compras.data?.findIndex((x: any) => x.id === c.id);
                if (i !== undefined && i !== -1 && this.compras.data) {
                    this.compras.data[i] = { ...c };
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
                this.compra = {
                    ...this.compra,
                    errores: msg,
                    ...(feCrIntento ? { fe_cr_intento_emision: feCrIntento } : {}),
                };
                const j = this.compras.data?.findIndex((v: any) => v.id === this.compra.id);
                if (j !== undefined && j !== -1 && this.compras.data) {
                    this.compras.data[j] = { ...this.compra };
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
        this.compra.tipo = 'compra';
        this.apiService.store('enviarDTE', this.compra)
            .pipe(this.untilDestroyed())
            .subscribe(dte => {
            this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
            this.sending = false;
            setTimeout(()=>{
                this.closeModal();
            },5000);
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.sending = false; this.cdr.markForCheck(); });
    }

    anularDTE(compra:any){
        this.compra = compra;
        if (this.esFeCostaRica()) {
            if (confirm('¿Confirma anular la compra?')) {
                compra.estado = 'Anulada';
                this.onSubmit();
            }
            return;
        }
        if(compra.dte){
            if (confirm('¿Confirma anular la compra y el DTE?')) {
                this.compra = compra;
                this.saving = true;
                this.apiService.store('generarDTEAnuladoSujetoExcluidoCompra', this.compra)
                    .pipe(this.untilDestroyed())
                    .subscribe(dte => {
                        // this.alertService.success('DTE generado.');
                        this.compra.dte_invalidacion = dte;
                        this.facturacionElectronica.firmarDTE(dte)
                            .pipe(this.untilDestroyed())
                            .subscribe(dteFirmado => {
                                this.compra.dte_invalidacion.firmaElectronica = dteFirmado.body;
                                // this.alertService.success('DTE firmado.');

                                this.facturacionElectronica.anularDTE(this.compra, dteFirmado.body)
                                    .pipe(this.untilDestroyed())
                                    .subscribe(dte => {
                                        if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                            this.compra.dte_invalidacion.sello = dte.selloRecibido;
                                            this.compra.estado = 'Anulada';
                                            this.apiService.store('compra', this.compra)
                                                .pipe(this.untilDestroyed())
                                                .subscribe(data => {
                                    // this.alertService.success('Compra guardada.');
                                },error => {this.alertService.error(error); this.saving = false; });
                            }

                            this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
                        },error => {
                            if(error.error.descripcionMsg){
                                this.alertService.warning('Hubo un problema', error.error.descripcionMsg);
                            }
                            if(error.error.observaciones.length > 0){
                                this.alertService.warning('Hubo un problema', error.error.observaciones);
                            }
                            this.saving = false;
                        });

                    },error => {this.alertService.error(error);this.saving = false; });

                },error => {this.alertService.error(error);this.saving = false; });
            }
        }
        else{
            if (confirm('¿Confirma anular la compra?')){
                compra.estado = 'Anulada';
                this.onSubmit();
            }
        }
    }



    public abrirModalFiltrosRentabilidad(template: TemplateRef<any>) {
        this.sharedDataService.getSucursales()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (sucursales) => {
                    this.sucursales = sucursales;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });

        this.modalRefRentabilidad = this.modalManager.openModal(template, {
          class: 'modal-lg',
        });
      }


  public descargarReporteRentabilidad() {
    this.downloadingRentabilidad = true;
    this.saving = true;

    this.apiService.exportAcumulado('compras-rentabilidad/exportar', this.filtrosRentabilidad)
      .pipe(this.untilDestroyed())
      .subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'compras-rentabilidad.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Cerrar modal de rentabilidad
        if (this.modalRefRentabilidad) {
          this.modalManager.closeModal(this.modalRefRentabilidad);
          this.modalRefRentabilidad = undefined;
        }

        this.downloadingRentabilidad = false;
        this.saving = false;


        this.filtrosRentabilidad = {
          inicio: '',
          fin: '',
          sucursales: [],
          categorias: [],
          marcas: [],
        };
      },
      (error) => {
        this.alertService.error(error);
        this.downloadingRentabilidad = false;
        this.saving = false;
      }
    );
  }

  public isColumnEnabled(columnName: string): boolean {
    return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
  }

  getNumsIds(){
    this.apiService.getAll('compras/nums-ids')
        .pipe(this.untilDestroyed())
        .subscribe(numsIds => {
            this.numeros_ids = numsIds;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); });
  }

  public imprimir(compra:any){
    window.open(this.apiService.baseUrl + '/api/compra/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
  }

  generarPartidaContable(compra:any){
    this.apiService.store('contabilidad/partida/compra', compra)
        .pipe(this.untilDestroyed())
        .subscribe(compra => {
      this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
      this.cdr.markForCheck();
    },error => {this.alertService.error(error);});
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

  // --- Importación masiva desde JSON (listado compras) ---

  private filterDocumentosBulkLista(documentos: any[]): any[] {
    const auth = this.apiService.auth_user();
    const documentosPermitidos = [
      'Factura',
      'Crédito fiscal',
      'Ticket',
      'Recibo',
      'Sujeto excluido',
      'Factura de exportación',
    ];
    return (documentos || []).filter(
      (x: any) =>
        x.id_sucursal == auth.id_sucursal &&
        documentosPermitidos.includes(x.nombre) &&
        x.nombre != 'Nota de crédito' &&
        x.nombre != 'Nota de débito'
    );
  }

  /**
   * Recarga `documentos/list` y reasigna referencias en pestañas pendientes.
   * Tras guardar, se pasa `despuesDeGuardar` para numerar desde la referencia realmente registrada (+1, +2…),
   * porque el correlativo del GET no siempre refleja de inmediato el incremento en servidor.
   */
  private refrescarCorrelativosBulkTrasGuardar(
    done?: () => void,
    despuesDeGuardar?: {
      referenciaGuardada: any;
      tipo_documento: string;
      id_sucursal: any;
    }
  ) {
    this.apiService.getAll('documentos/list').subscribe(
      (documentos) => {
        this.documentosBulk = this.filterDocumentosBulkLista(documentos);
        this.compraJsonBulk.aplicarReferenciasSecuencialesImportacion(
          this.bulkItems,
          this.documentosBulk,
          despuesDeGuardar ? { despuesDeGuardar } : undefined
        );
        done?.();
      },
      (e) => {
        this.alertService.error(e);
        if (despuesDeGuardar) {
          this.compraJsonBulk.aplicarReferenciasSecuencialesImportacion(
            this.bulkItems,
            this.documentosBulk,
            { despuesDeGuardar }
          );
        }
        done?.();
      }
    );
  }

  openImportacionJsonMasivo(template: TemplateRef<any>) {
    if (!this.permiteImportacionMasivaComprasJson) {
      this.alertService.warning(
        'Importación masiva',
        'Su empresa no tiene habilitada la importación masiva de compras desde JSON. Solicite la activación al administrador.'
      );
      return;
    }
    this.bulkItems = [];
    this.bulkTabIndex = 0;
    this.documentosBulk = [];
    this.apiService.getAll('impuestos').subscribe(
      (impuestos) => {
        this.impuestosCompra = (impuestos || []).filter(
          (i: any) => i.aplica_compras !== false && i.aplica_compras !== 0
        );
        this.apiService.getAll('bodegas/list').subscribe(
          (bodegas) => {
            this.bodegasBulk = bodegas || [];
            this.apiService.getAll('documentos/list').subscribe(
              (documentos) => {
                this.documentosBulk = this.filterDocumentosBulkLista(documentos);
                this.bulkModalRef = this.modalService.show(template, {
                  class: 'modal-xl modal-dialog-scrollable',
                  backdrop: 'static',
                });
              },
              (e) => this.alertService.error(e)
            );
          },
          (e) => this.alertService.error(e)
        );
      },
      (e) => this.alertService.error(e)
    );
  }

  cerrarImportacionBulk() {
    this.bulkModalRef?.hide();
    this.bulkItems = [];
    this.bulkTabIndex = 0;
  }

  private readFileText(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
      const r = new FileReader();
      r.onload = () => resolve(String(r.result ?? ''));
      r.onerror = () => reject(r.error);
      r.readAsText(file);
    });
  }

  async onBulkJsonFilesChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = input.files;
    if (!files?.length) {
      return;
    }
    const list = Array.from(files).slice(0, this.maxBulkJsonFiles);
    if (files.length > this.maxBulkJsonFiles) {
      this.alertService.warning(
        'Límite',
        `Solo se procesan los primeros ${this.maxBulkJsonFiles} archivos.`
      );
    }
    this.bulkProcesandoArchivos = true;
    const proveedores = this.proveedores || [];
    for (const f of list) {
      const uid = 'b-' + Math.random().toString(36).slice(2, 11);
      try {
        const text = await this.readFileText(f);
        const jsonData = JSON.parse(text);
        const prep = await this.compraJsonBulk.prepararCompraDesdeJson(
          jsonData,
          this.impuestosCompra,
          proveedores,
          this.documentosBulk
        );
        if (prep.error) {
          this.bulkItems.push({
            uid,
            fileName: f.name,
            compra: prep.compra,
            jsonData,
            noEncontrados: [],
            error: prep.error,
            estado: 'error',
          });
        } else {
          const estado: BulkCompraItem['estado'] =
            prep.noEncontrados.length > 0 ? 'pendiente_productos' : 'lista';
          this.bulkItems.push({
            uid,
            fileName: f.name,
            compra: prep.compra,
            jsonData,
            noEncontrados: prep.noEncontrados,
            estado,
          });
        }
      } catch (e: any) {
        this.bulkItems.push({
          uid,
          fileName: f.name,
          compra: this.compraJsonBulk.crearCompraBase(this.impuestosCompra),
          jsonData: {},
          noEncontrados: [],
          error: e?.message || 'JSON inválido',
          estado: 'error',
        });
      }
    }
    this.bulkProcesandoArchivos = false;
    input.value = '';
    this.compraJsonBulk.aplicarReferenciasSecuencialesImportacion(
      this.bulkItems,
      this.documentosBulk
    );
    if (this.bulkItems.length && this.bulkTabIndex >= this.bulkItems.length) {
      this.bulkTabIndex = 0;
    }
  }

  get bulkItemActivo(): BulkCompraItem | null {
    return this.bulkItems[this.bulkTabIndex] ?? null;
  }

  /** Texto UX en pestañas (el estado interno sigue siendo `lista`, `pendiente_productos`, etc.). */
  labelEstadoBulk(estado: string): string {
    const m: Record<string, string> = {
      lista: 'Listo para procesar',
      pendiente_productos: 'Pendiente vinculación',
      guardada: 'Registrada',
      guardando: 'Guardando…',
      error: 'Error',
    };
    return m[estado] ?? estado;
  }

  /**
   * "Guardar todas" solo si hay compras por registrar y todas las pestañas activas están listas
   * (productos vinculados, proveedor, lotes si aplica, etc.).
   */
  puedeGuardarTodasBulk(): boolean {
    if (!this.bulkItems.length || this.bulkProcesandoArchivos || this.bulkGuardandoTodas) {
      return false;
    }
    const activos = this.bulkItems.filter(
      (i) => i.estado !== 'guardada' && i.estado !== 'error'
    );
    if (!activos.length) {
      return false;
    }
    return activos.every((i) => i.estado === 'lista' && this.puedeGuardarBulkItem(i));
  }

  setProveedorBulk(item: BulkCompraItem, proveedor: any) {
    if (!item.compra.id_proveedor) {
      this.proveedores.push(proveedor);
    }
    item.compra.id_proveedor = proveedor.id;
    if (proveedor.tipo_contribuyente === 'Grande') {
      item.compra.retencion = 1;
    }
    this.compraJsonBulk.recalcularTotales(item.compra, this.proveedores);
  }

  onBulkProveedorChange(item: BulkCompraItem) {
    this.compraJsonBulk.recalcularTotales(item.compra, this.proveedores);
  }

  onBulkBodegaChange(item: BulkCompraItem) {
    const b = this.bodegasBulk.find((x: any) => x.id == item.compra.id_bodega);
    if (b) {
      item.compra.id_sucursal = b.id_sucursal;
    }
    this.compraJsonBulk.aplicarReferenciasSecuencialesImportacion(
      this.bulkItems,
      this.documentosBulk
    );
  }

  /** Mismo flujo que facturación: líneas editables, búsqueda y lotes vía `app-compra-detalles`. */
  onBulkDetallesRecalc(item: BulkCompraItem) {
    if (item.estado === 'guardada' || item.estado === 'error' || item.estado === 'guardando') {
      return;
    }
    this.compraJsonBulk.recalcularTotales(item.compra, this.proveedores);
    item.estado = item.noEncontrados?.length ? 'pendiente_productos' : 'lista';
  }

  incorporarProductosPendientes(item: BulkCompraItem) {
    if (!item.noEncontrados?.length) {
      return;
    }
    const faltan = item.noEncontrados.filter((x) => !x.productoSeleccionado?.id);
    if (faltan.length) {
      this.alertService.warning(
        'Productos',
        'Seleccione un producto del sistema para cada línea pendiente.'
      );
      return;
    }
    for (const row of item.noEncontrados) {
      item.compra.detalles.push(
        this.compraJsonBulk.crearDetalleDesdeItem(row, row.productoSeleccionado)
      );
    }
    item.noEncontrados = [];
    this.compraJsonBulk.recalcularTotales(item.compra, this.proveedores);
    item.estado = 'lista';
    if (this.apiService.isLotesActivo()) {
      const sinLote = item.compra.detalles.filter(
        (d: any) => d.inventario_por_lotes && !d.lote_id
      );
      if (sinLote.length) {
        this.alertService.info(
          'Lotes / partidas',
          'Hay líneas con control por lotes. Use el botón de lote en la tabla del detalle para elegir un lote existente o crear uno nuevo (no se infiere del JSON).'
        );
      }
    }
  }

  /** Solo importación masiva: pide al backend avanzar el correlativo del documento en catálogo (no afecta facturación normal). */
  private payloadFacturacionImportacionMasiva(compra: any): object {
    return { ...compra, incrementar_correlativo_importacion_massiva: true };
  }

  puedeGuardarBulkItem(item: BulkCompraItem): boolean {
    if (item.estado === 'error' || item.estado === 'guardada' || item.estado === 'guardando') {
      return false;
    }
    if (!item.compra?.id_proveedor) {
      return false;
    }
    if (!item.compra.detalles?.length) {
      return false;
    }
    if (item.noEncontrados?.length) {
      return false;
    }
    if (this.apiService.isLotesActivo()) {
      for (const d of item.compra.detalles) {
        if (d.inventario_por_lotes && !d.lote_id) {
          return false;
        }
      }
    }
    return true;
  }

  guardarBulkItem(item: BulkCompraItem) {
    if (!this.puedeGuardarBulkItem(item)) {
      this.alertService.warning(
        'Revisión',
        'Complete proveedor, líneas de detalle y productos pendientes antes de guardar.'
      );
      return;
    }
    if (!confirm(`¿Registrar la compra del archivo "${item.fileName}"?`)) {
      return;
    }
    item.estado = 'guardando';
    item.compra.recibido = item.compra.total;
    this.apiService
      .store('compra/facturacion', this.payloadFacturacionImportacionMasiva(item.compra))
      .subscribe(
      () => {
        item.estado = 'guardada';
        const ref = item.compra.referencia;
        const td = item.compra.tipo_documento;
        const suc = item.compra.id_sucursal;
        this.refrescarCorrelativosBulkTrasGuardar(() => {
          this.alertService.success('Compra registrada', item.fileName);
          this.filtrarCompras(false);
        }, { referenciaGuardada: ref, tipo_documento: td, id_sucursal: suc });
      },
      (err) => {
        item.estado = 'lista';
        this.alertService.error(err);
      }
    );
  }

  guardarTodasBulkListas() {
    const listas = this.bulkItems.filter((i) => this.puedeGuardarBulkItem(i));
    if (!listas.length) {
      this.alertService.warning('Nada que guardar', 'No hay compras listas para registrar.');
      return;
    }
    if (
      !confirm(
        `Se registrarán ${listas.length} compra(s). ¿Continuar?`
      )
    ) {
      return;
    }
    this.guardarBulkSecuencial(listas, 0);
  }

  private guardarBulkSecuencial(items: BulkCompraItem[], idx: number) {
    if (idx >= items.length) {
      this.bulkGuardandoTodas = false;
      this.alertService.success(
        'Importación',
        `Se registraron ${items.length} compra(s).`
      );
      this.filtrarCompras(false);
      this.cerrarImportacionBulk();
      return;
    }
    const item = items[idx];
    this.bulkGuardandoTodas = true;
    item.estado = 'guardando';
    item.compra.recibido = item.compra.total;
    this.apiService
      .store('compra/facturacion', this.payloadFacturacionImportacionMasiva(item.compra))
      .subscribe(
      () => {
        item.estado = 'guardada';
        const ref = item.compra.referencia;
        const td = item.compra.tipo_documento;
        const suc = item.compra.id_sucursal;
        this.refrescarCorrelativosBulkTrasGuardar(
          () => this.guardarBulkSecuencial(items, idx + 1),
          { referenciaGuardada: ref, tipo_documento: td, id_sucursal: suc }
        );
      },
      (err) => {
        item.estado = 'lista';
        this.bulkGuardandoTodas = false;
        this.alertService.error(err);
      }
    );
  }

  compareProductosBulk(a: any, b: any): boolean {
    return a && b && a.id === b.id;
  }

}

export interface BulkCompraItem {
  uid: string;
  fileName: string;
  compra: any;
  jsonData: any;
  noEncontrados: any[];
  error?: string;
  estado: 'error' | 'pendiente_productos' | 'lista' | 'guardando' | 'guardada';
}
