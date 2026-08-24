import { Component, OnInit, TemplateRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { CrearAbonoGastoComponent } from '@shared/modals/crear-abono-gasto/crear-abono-gasto.component';
import { CrearProveedorComponent } from '@shared/modals/crear-proveedor/crear-proveedor.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { PipesModule } from '@pipes/pipes.module';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { DocumentoImportService } from '@services/compras/documento-import.service';
import { GastoJsonBulkService } from '@services/gasto-json-bulk.service';
import {
  ExportPeriodoState,
  MESES_EXPORT_PERIODO,
  MAX_DIAS_EXPORT_VENTAS,
  aniosDisponiblesExportDesde,
  buildFechasExportValidadas,
  crearEstadoExportPeriodoDefault,
  diasEntreFechasIso,
  esErrorTimeoutExport,
  mensajeErrorTimeoutExport,
  maxDiasExportPorTipo,
  prefillExportPeriodoDesdeFiltros,
  validarPeriodoExport,
} from '../../../helpers/export-period.helper';

const SLUG_IMPORTACION_MASIVA_GASTOS_JSON = 'importacion-masiva-gastos-json';

export interface BulkGastoItem {
  uid: string;
  fileName: string;
  gasto: any;
  detalles: any[];
  varios_items: boolean;
  jsonData?: any;
  error?: string;
  estado: 'error' | 'lista' | 'guardando' | 'guardada';
}

@Component({
    selector: 'app-gastos',
    templateUrl: './gastos.component.html',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        RouterModule,
        NgSelectModule,
        TooltipModule,
        PopoverModule,
        PipesModule,
        PaginationComponent,
        CrearAbonoGastoComponent,
        CrearProveedorComponent,
        NotificacionesContainerComponent,
    ]
})

export class GastosComponent implements OnInit {

    private readonly countryI18n = inject(CountryI18nService);

    public gastos:any = [];
    public gasto:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public sending:boolean = false;
    public downloading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public sucursales:any = [];
    public proveedores:any = [];
    public areas:any = [];
    public filtros:any = {};

    public exportPeriodo: ExportPeriodoState = crearEstadoExportPeriodoDefault();
    public readonly mesesExportPeriodo = MESES_EXPORT_PERIODO;
    public readonly aniosDisponiblesExport = aniosDisponiblesExportDesde();
    public readonly maxDiasExportPorTipo = maxDiasExportPorTipo;

    /** Importación masiva JSON (listado de gastos) */
    public bulkModalRef!: BsModalRef;
    public bulkItems: BulkGastoItem[] = [];
    public bulkTabIndex = 0;
    public bulkProcesandoArchivos = false;
    public bulkGuardandoTodas = false;
    public readonly maxBulkJsonFiles = 20;
    public permiteImportacionMasivaGastosJson = false;
    public readonly tiposGastoBulk = [
        'Alquiler', 'Combustible', 'Costo de venta', 'Gastos varios', 'Insumos',
        'Impuestos', 'Activo Fijo', 'Gastos Administrativos', 'Mantenimiento',
        'Marketing', 'Materia Prima', 'Servicios', 'Pago comisión', 'Planilla',
        'Préstamos', 'Publicidad',
    ];
    public categoriasBulk: any[] = [];
    public contabilidadHabilitada = false;

    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService,
        public mhService: MHService,
        public documentoImportService: DocumentoImportService,
        public alertService: AlertService,
        private modalService: BsModalService,
        private router: Router,
        private route: ActivatedRoute,
        private gastoJsonBulk: GastoJsonBulkService,
        private funcionalidadesService: FuncionalidadesService,
    ){}

    ngOnInit() {
        this.route.queryParams.subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_proyecto: +params['id_proyecto'] || '',
                id_documento: +params['id_documento'] || '',
                id_proveedor: +params['id_proveedor'] || '',
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

            this.filtrarGastos(false);
        });

        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('area-empresa/list').subscribe(areas => { 
            this.areas = areas;
        }, error => {this.alertService.error(error); });

        this.funcionalidadesService
            .verificarAcceso(SLUG_IMPORTACION_MASIVA_GASTOS_JSON)
            .subscribe((ok) => {
                this.permiteImportacionMasivaGastosJson = !!ok;
            });

        this.funcionalidadesService.verificarAcceso('contabilidad').subscribe({
            next: (acceso) => {
                this.contabilidadHabilitada = !!acceso;
            },
            error: () => {
                this.contabilidadHabilitada = false;
            },
        });
    }

    get mostrarCategoriaBulk(): boolean {
        return this.apiService.mostrarMenuConfigGastos(this.contabilidadHabilitada);
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
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
        this.filtros.num_identificacion = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarGastos(false);
    }

    /** @param resetPage true al buscar/filtrar/ordenar/cambiar paginate; false al paginar o cargar desde URL. */
    public filtrarGastos(resetPage = true){
        if (resetPage) {
            this.filtros.page = 1;
        }
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

        if(!this.filtros.id_proveedor){
            this.filtros.id_proveedor = '';
        }

        if(!this.filtros.id_usuario){
            this.filtros.id_usuario = '';
        }
        
        this.apiService.getAll('gastos', this.filtros).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
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

    public openAbono(template: TemplateRef<any>, gasto: any) {
        this.gasto = gasto;
        this.modalRef = this.modalService.show(template);
    }
    
    public onSubmit(){
        this.apiService.store('gasto', this.gasto).subscribe(gasto => { 
            this.gasto = gasto;
            this.alertService.success('Gasto guardado', 'El gasto fue cambiado a ' + this.gasto.estado.toLowerCase() + ' exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public setRecurrencia(gasto:any){
        this.gasto = gasto;
        this.gasto.recurrente = true;
        
        this.apiService.store('gasto', this.gasto).subscribe(gasto => {
            this.gasto = {};
            this.alertService.success('Gasto guardado', 'El gasto se marco como recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id) .subscribe(data => {
                for (let i = 0; i < this.gastos['data'].length; i++) { 
                    if (this.gastos['data'][i].id == data.id )
                        this.gastos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.filtros.page = event.page;
        this.filtrarGastos(false);
    }


    public openDescargar(template: TemplateRef<any>) {
        this.exportPeriodo = crearEstadoExportPeriodoDefault();
        prefillExportPeriodoDesdeFiltros(this.filtros, this.exportPeriodo);
        this.modalRef = this.modalService.show(template);
    }

    public cerrarModalDescargar(): void {
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }

    public get anioEnCursoParaMes(): number {
        return new Date().getFullYear();
    }

    public get puedeDescargarGastos(): boolean {
        return buildFechasExportValidadas(this.exportPeriodo, 'ventas') !== null;
    }

    public rangoExportSuperaLimiteGastos(): boolean {
        const fechas = buildFechasExportValidadas(this.exportPeriodo, 'ventas');
        if (fechas) return false;
        const ini = this.exportPeriodo.rangoInicio?.trim();
        const fin = this.exportPeriodo.rangoFin?.trim();
        if (this.exportPeriodo.tipo === 'rango' && ini && fin && ini <= fin) {
            return diasEntreFechasIso(ini, fin) > maxDiasExportPorTipo('ventas');
        }
        return false;
    }

    public descargar(){
        const fechas = buildFechasExportValidadas(this.exportPeriodo, 'ventas');
        if (!fechas) {
            const check = validarPeriodoExport(
                this.exportPeriodo.rangoInicio,
                this.exportPeriodo.rangoFin,
                MAX_DIAS_EXPORT_VENTAS
            );
            this.alertService.error(check.error ?? 'Período inválido.');
            return;
        }
        const filtrosExport = { ...this.filtros, inicio: fechas.inicio, fin: fechas.fin };
        this.downloading = true;
        this.apiService.export('gastos/exportar', filtrosExport).subscribe((data:Blob) => {
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
            this.cerrarModalDescargar();
          }, (error) => {
            if (esErrorTimeoutExport(error)) {
                this.alertService.error(mensajeErrorTimeoutExport(MAX_DIAS_EXPORT_VENTAS));
            } else {
                this.alertService.error(error);
            }
            this.downloading = false;
          }
        );
    }

    public openFilter(template: TemplateRef<any>) {
        if(!this.sucursales.length){
            this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }

        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        }

        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.apiService.getAll('proyectos/list').subscribe(proyectos => { 
                this.proyectos = proyectos;
            }, error => {this.alertService.error(error); });
        }

        if(!this.numeros_ids.length && this.isColumnEnabled('columna_proyecto')){
            this.getNumsIds();
        }

        this.modalRef = this.modalService.show(template);
    }

    openDTE(template: TemplateRef<any>, gasto:any){
        this.gasto = gasto;
        this.modalRef = this.modalService.show(template);
        this.alertService.modal = true;
        if(!this.gasto.dte){
            this.emitirDTE();
        }
    }

    imprimirDTEPDF(gasto:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + gasto.id + '/14/' + '?tipo=gasto&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(gasto:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + gasto.id + '/14/' + '?tipo=gasto&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    emitirDTE(){
        this.saving = true;
        this.mhService.emitirDTESujetoExcluidoGasto(this.gasto).then((gasto) => {
            this.gasto = gasto;
            this.alertService.success(this.countryI18n.fe('emitSuccessTitle'), this.countryI18n.fe('emitSuccessBody'));
            this.saving = false;
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }


    enviarDTE(){
        this.sending = true;
        this.gasto.tipo = 'gasto';
        this.apiService.store('enviarDTE', this.gasto).subscribe(dte => {
            this.alertService.success(this.countryI18n.fe('sendSuccessTitle'), this.countryI18n.fe('sendSuccessBody'));
            this.sending = false;
            setTimeout(()=>{
                this.modalRef?.hide();
            },5000);
        },error => {this.alertService.error(error); this.sending = false; });
    }

    anularDTE(gasto:any){
        this.gasto = gasto;
        if(gasto.dte){
            if (confirm(this.countryI18n.fe('annulExpenseConfirm'))) {
                this.gasto = gasto;
                this.saving = true;
                this.apiService.store('generarDTEAnuladoSujetoExcluidoGasto', this.gasto).subscribe(dte => {
                    // this.alertService.success('DTE generado.');
                    this.gasto.dte_invalidacion = dte;
                    this.mhService.firmarDTE(dte).subscribe(dteFirmado => {
                        this.gasto.dte_invalidacion.firmaElectronica = dteFirmado.body;
                        // this.alertService.success('DTE firmado.');
                        
                        this.mhService.anularDTE(this.gasto, dteFirmado.body).subscribe(dte => {
                            if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                this.gasto.dte_invalidacion.sello = dte.selloRecibido;
                                this.gasto.estado = 'Anulada';
                                this.apiService.store('gasto', this.gasto).subscribe(data => {
                                    // this.alertService.success('Compra guardada.');
                                },error => {this.alertService.error(error); this.saving = false; });
                            }

                            this.alertService.success(this.countryI18n.fe('annulSuccessTitle'), this.countryI18n.fe('annulSuccessBody'));
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
            if (confirm('¿Confirma anular la gasto?')){
                gasto.estado = 'Anulada';
                this.onSubmit();
            }
        }
    }

    generarPartidaContable(gasto:any){
        this.apiService.store('contabilidad/partida/gasto', gasto).subscribe(gasto => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
        },error => {this.alertService.error(error);});
    }

    openImportacionJsonMasivo(template: TemplateRef<any>) {
        if (!this.permiteImportacionMasivaGastosJson) {
            this.alertService.warning(
                'Importación masiva',
                'Su empresa no tiene habilitada la importación masiva de gastos desde documentos electrónicos. Solicite la activación al administrador.'
            );
            return;
        }
        this.bulkItems = [];
        this.bulkTabIndex = 0;
        this.alertService.modal = true;
        this.cargarCategoriasBulk(() => {
            this.bulkModalRef = this.modalService.show(template, {
                class: 'modal-xl modal-dialog-scrollable',
                backdrop: 'static',
            });
            this.bulkModalRef.onHidden?.subscribe(() => {
                this.bulkItems = [];
                this.bulkTabIndex = 0;
                this.alertService.modal = false;
            });
        });
    }

    cerrarImportacionBulk() {
        this.bulkModalRef?.hide();
    }

    private cargarCategoriasBulk(done?: () => void) {
        if (!this.mostrarCategoriaBulk) {
            this.categoriasBulk = [];
            done?.();
            return;
        }
        this.apiService.getAll('gastos/categorias/list').subscribe(
            (categorias) => {
                this.categoriasBulk = categorias || [];
                done?.();
            },
            (error) => {
                this.alertService.error(error);
                this.categoriasBulk = [];
                done?.();
            }
        );
    }

    /** Prefill id_categoria si el import ya lo trae o si coincide el nombre con `tipo`. */
    private aplicarCategoriaImportada(item: BulkGastoItem) {
        if (!this.mostrarCategoriaBulk || !this.categoriasBulk?.length) {
            return;
        }
        const idActual = item.gasto?.id_categoria;
        if (idActual != null && idActual !== '') {
            item.gasto.id_categoria = Number(idActual);
            this.sincronizarCategoriaDetalles(item);
            return;
        }
        const tipo = String(item.gasto?.tipo || '').trim().toLowerCase();
        if (!tipo) {
            return;
        }
        const cat = this.categoriasBulk.find(
            (c: any) => String(c?.nombre || '').trim().toLowerCase() === tipo
        );
        if (cat?.id != null) {
            item.gasto.id_categoria = cat.id;
            this.sincronizarCategoriaDetalles(item);
        }
    }

    onBulkCategoriaChange(item: BulkGastoItem) {
        const id = item.gasto?.id_categoria;
        item.gasto.id_categoria = id != null && id !== '' ? Number(id) : null;
        this.sincronizarCategoriaDetalles(item);
        if (item.gasto.id_categoria != null) {
            const cat = this.categoriasBulk.find((c: any) => c.id == item.gasto.id_categoria);
            if (cat?.nombre && !item.gasto.tipo) {
                item.gasto.tipo = cat.nombre;
            }
        }
    }

    onBulkTipoChange(item: BulkGastoItem) {
        if (!this.mostrarCategoriaBulk) {
            return;
        }
        // Rematch solo si aún no eligió categoría o la actual no cuadra con el tipo
        const tipo = String(item.gasto?.tipo || '').trim().toLowerCase();
        if (!tipo) {
            return;
        }
        const catActual = this.categoriasBulk.find((c: any) => c.id == item.gasto.id_categoria);
        if (catActual && String(catActual.nombre || '').trim().toLowerCase() === tipo) {
            return;
        }
        const cat = this.categoriasBulk.find(
            (c: any) => String(c?.nombre || '').trim().toLowerCase() === tipo
        );
        item.gasto.id_categoria = cat?.id ?? null;
        this.sincronizarCategoriaDetalles(item);
    }

    private sincronizarCategoriaDetalles(item: BulkGastoItem) {
        if (!item.varios_items || !item.detalles?.length) {
            return;
        }
        const id = item.gasto?.id_categoria ?? null;
        item.detalles.forEach((d: any) => {
            d.id_categoria = id;
        });
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
        for (const f of list) {
            const uid = 'b-' + Math.random().toString(36).slice(2, 11);
            try {
                const text = await this.readFileText(f);
                const prep = await this.gastoJsonBulk.prepararGastoDesdeContenido(text);
                if (prep.error) {
                    this.bulkItems.push({
                        uid,
                        fileName: f.name,
                        gasto: prep.gasto,
                        detalles: [],
                        varios_items: false,
                        jsonData: prep.jsonData,
                        error: prep.error,
                        estado: 'error',
                    });
                } else {
                    const item: BulkGastoItem = {
                        uid,
                        fileName: f.name,
                        gasto: prep.gasto,
                        detalles: prep.detalles,
                        varios_items: prep.varios_items,
                        jsonData: prep.jsonData,
                        estado: 'lista',
                    };
                    this.aplicarCategoriaImportada(item);
                    this.bulkItems.push(item);
                }
            } catch (e: any) {
                this.bulkItems.push({
                    uid,
                    fileName: f.name,
                    gasto: this.gastoJsonBulk.crearGastoBase(),
                    detalles: [],
                    varios_items: false,
                    jsonData: {},
                    error: e?.message || 'No se pudo leer el comprobante',
                    estado: 'error',
                });
            }
        }
        this.bulkProcesandoArchivos = false;
        input.value = '';
        if (this.bulkItems.length && this.bulkTabIndex >= this.bulkItems.length) {
            this.bulkTabIndex = 0;
        }
    }

    get bulkItemActivo(): BulkGastoItem | null {
        return this.bulkItems[this.bulkTabIndex] ?? null;
    }

    labelEstadoBulk(estado: string): string {
        const m: Record<string, string> = {
            lista: 'Listo para procesar',
            guardada: 'Registrada',
            guardando: 'Guardando…',
            error: 'Error',
        };
        return m[estado] ?? estado;
    }

    setProveedorBulk(item: BulkGastoItem, proveedor: any) {
        if (!item.gasto.id_proveedor) {
            this.proveedores.push(proveedor);
        }
        item.gasto.id_proveedor = proveedor.id;
    }

    puedeGuardarBulkItem(item: BulkGastoItem): boolean {
        if (item.estado === 'error' || item.estado === 'guardada' || item.estado === 'guardando') {
            return false;
        }
        if (!item.gasto?.id_proveedor) {
            return false;
        }
        if (item.varios_items) {
            if (!item.detalles?.length) {
                return false;
            }
            return item.detalles.every(
                (d) => d.concepto && String(d.concepto).trim() && d.tipo && String(d.tipo).trim()
            );
        }
        return !!(item.gasto.concepto && String(item.gasto.concepto).trim() && item.gasto.tipo);
    }

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

    guardarBulkItem(item: BulkGastoItem) {
        if (!this.puedeGuardarBulkItem(item)) {
            this.alertService.warning(
                'Revisión',
                'Complete proveedor, concepto y tipo de gasto antes de guardar.'
            );
            return;
        }
        if (!confirm(`¿Registrar el gasto del archivo "${item.fileName}"?`)) {
            return;
        }
        item.estado = 'guardando';
        this.apiService
            .store('gasto', this.gastoJsonBulk.payloadStoreImportacionMasiva(item))
            .subscribe(
                () => {
                    item.estado = 'guardada';
                    this.alertService.success('Gasto registrado', item.fileName);
                    this.filtrarGastos(false);
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
            this.alertService.warning('Nada que guardar', 'No hay gastos listos para registrar.');
            return;
        }
        if (!confirm(`Se registrarán ${listas.length} gasto(s). ¿Continuar?`)) {
            return;
        }
        this.guardarBulkSecuencial(listas, 0);
    }

    private guardarBulkSecuencial(items: BulkGastoItem[], idx: number) {
        if (idx >= items.length) {
            this.bulkGuardandoTodas = false;
            this.alertService.success(
                'Importación',
                `Se registraron ${items.length} gasto(s).`
            );
            this.filtrarGastos(false);
            this.cerrarImportacionBulk();
            return;
        }
        const item = items[idx];
        this.bulkGuardandoTodas = true;
        item.estado = 'guardando';
        this.apiService
            .store('gasto', this.gastoJsonBulk.payloadStoreImportacionMasiva(item))
            .subscribe(
                () => {
                    item.estado = 'guardada';
                    this.guardarBulkSecuencial(items, idx + 1);
                },
                (err) => {
                    item.estado = 'lista';
                    this.bulkGuardandoTodas = false;
                    this.alertService.error(err);
                }
            );
    }

}
