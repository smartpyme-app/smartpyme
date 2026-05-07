import { Component, OnInit, OnDestroy, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { CompraJsonBulkService } from '@services/compra-json-bulk.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { Subject } from 'rxjs';

/** Debe coincidir con el slug en Backend (FuncionalidadesSeeder / verificar-acceso). */
const SLUG_IMPORTACION_MASIVA_COMPRAS_JSON = 'importacion-masiva-compras-json';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

declare var $:any;

@Component({
  selector: 'app-compras',
  templateUrl: './compras.component.html'
})
export class ComprasComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private searchSubject$ = new Subject<void>();

    public compras:any = [];
    public compra:any = {};
    public formaPagos:any = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public sucursales:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    public saving:boolean = false;
    public sending:boolean = false;
    public downloadingDetalles:boolean = false;
    public downloadingCompras:boolean = false;
    public modalRefAcumulado!: BsModalRef;
    public modalRefRentabilidad!: BsModalRef;
    public filtrosRentabilidad:any = {
        inicio: '',
        fin: '',
        sucursales: [],
        categorias: [],
        marcas: [],
    };
    public numeros_ids:any = [];
    public downloadingRentabilidad:boolean = false;
    

    public filtros:any = {};

    modalRef!: BsModalRef;

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
        public apiService: ApiService,
        public mhService: MHService,
        private alertService: AlertService,
        private modalService: BsModalService,
        private router: Router,
        private route: ActivatedRoute,
        private compraJsonBulk: CompraJsonBulkService,
        private funcionalidadesService: FuncionalidadesService
    ) {
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

    ngOnDestroy() {
        this.destroy$.next();
        this.destroy$.complete();
    }

    protected untilDestroyed() {
        return takeUntil(this.destroy$);
    }

    ngOnInit() {
        this.searchSubject$.pipe(
            debounceTime(400),
            takeUntil(this.destroy$)
        ).subscribe(() => this.filtrarCompras());

        this.route.queryParams.subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_proyecto: +params['id_proyecto'] || '',
                id_documento: +params['id_documento'] || '',
                id_proveedor: +params['id_proveedor'] || '',
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

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
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
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;
        
        if(!this.filtros.id_proveedor){
            this.filtros.id_proveedor = '';
        }

        if(!this.filtros.id_usuario){
            this.filtros.id_usuario = '';
        }

        this.apiService.getAll('compras', this.filtros).subscribe(compras => { 
            this.compras = compras;
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

        this.filtrarCompras();
    }

    public setEstado(compra:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }

    }
    
    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('compra/', id) .subscribe(data => {
                for (let i = 0; i < this.compras['data'].length; i++) { 
                    if (this.compras['data'][i].id == data.id )
                        this.compras['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public openModalEdit(template: TemplateRef<any>, compra:any) {
        this.compra = compra;

        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.apiService.getAll('proyectos/list').subscribe(proyectos => { 
                this.proyectos = proyectos;
            }, error => {this.alertService.error(error); });
        }

        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        if(!this.formaPagos.length){
            this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });
        }

        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        }

        this.modalRef = this.modalService.show(template);
    }


    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('compras/filtrar/' + filtro + '/', txt).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('compra', this.compra).subscribe(compra => {
            this.compra = {};
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardado', 'La compra fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

        this.filtrarCompras(false);

    }

    public setRecurrencia(compra:any){
        this.compra = compra;
        this.compra.recurrente = true;
        
        this.apiService.store('compra', this.compra).subscribe(compra => {
            this.compra = {};
            this.alertService.success('Compra guardada', 'La compra se marco como recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.filtros.page = event.page;
        this.filtrarCompras(false);
    }

    public openDescargar(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargarCompras(){
        this.downloadingCompras = true; this.saving = true;
        this.apiService.export('compras/exportar', this.filtros).subscribe((data:Blob) => {
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
          }, (error) => {this.alertService.error(error); this.downloadingCompras = false; this.saving = false;}
        );
    }

    public descargarDetalles(){
        this.downloadingDetalles = true; this.saving = true;
        this.apiService.export('compras-detalles/exportar', this.filtros).subscribe((data:Blob) => {
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
          }, (error) => {this.alertService.error(error); this.downloadingDetalles = false; this.saving = false; }
        );
    }

    public openAbono(template: TemplateRef<any>, compra: any){
        this.compra = { ...compra, saldo: this.getSaldo(compra) };
        this.modalRef = this.modalService.show(template);
    }

    public openFilter(template: TemplateRef<any>) {

        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        if(!this.formaPagos.length){
            this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });
        }

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

        this.modalRef = this.modalService.show(template);
    }

    openDTE(template: TemplateRef<any>, compra:any){
        const abrir = (c: any) => {
            this.compra = c;
            this.modalRef = this.modalService.show(template);
            this.alertService.modal = true;
            if (!this.compra.dte && !this.compra.dte_en_s3) {
                this.emitirDTE();
            }
        };

        if (compra.dte_en_s3 && !compra.dte) {
            this.apiService.read('compra/', compra.id)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (c: any) => abrir(c),
                    error: (e) => this.alertService.error(e),
                });
            return;
        }

        abrir(compra);
    }

    imprimirDTEPDF(compra:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + compra.id + '/14/' + '?tipo=compra&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(compra:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + compra.id + '/14/' + '?tipo=compra&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    emitirDTE(){
        this.saving = true;
        this.mhService.emitirDTESujetoExcluidoCompra(this.compra).then((compra) => {
            this.compra = compra;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
            this.enviarDTE();
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }


    enviarDTE(){
        this.sending = true;
        this.compra.tipo = 'compra';
        this.apiService.store('enviarDTE', this.compra).subscribe(dte => {
            this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
            this.sending = false;
            setTimeout(()=>{
                this.modalRef?.hide();
            },5000);
        },error => {this.alertService.error(error); this.sending = false; });
    }

    anularDTE(compra:any){
        const conDte = (c: any) => {
            this.compra = c;
            if(c.dte || c.dte_en_s3){
                if (confirm('¿Confirma anular la compra y el DTE?')) {
                    this.compra = c;
                    this.saving = true;
                    this.apiService.store('generarDTEAnuladoSujetoExcluidoCompra', this.compra).subscribe(dte => {
                        this.compra.dte_invalidacion = dte;
                        this.mhService.firmarDTE(dte).subscribe(dteFirmado => {
                            this.compra.dte_invalidacion.firmaElectronica = dteFirmado.body;

                            this.mhService.anularDTE(this.compra, dteFirmado.body).subscribe(dte => {
                                if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                    this.compra.dte_invalidacion.sello = dte.selloRecibido;
                                    this.compra.estado = 'Anulada';
                                    this.apiService.store('compra', this.compra).subscribe(data => {
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
                    c.estado = 'Anulada';
                    this.compra = c;
                    this.onSubmit();
                }
            }
        };

        if (compra.dte_en_s3 && !compra.dte) {
            this.apiService.read('compra/', compra.id)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (c: any) => conDte(c),
                    error: (e) => this.alertService.error(e),
                });
            return;
        }

        conDte(compra);
    }

   

    public abrirModalFiltrosRentabilidad(template: TemplateRef<any>) {
        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

        this.modalRefRentabilidad = this.modalService.show(template, {
          class: 'modal-lg',
        });
      }

      
  public descargarReporteRentabilidad() {
    this.downloadingRentabilidad = true;
    this.saving = true;

    this.apiService.exportAcumulado('compras-rentabilidad/exportar', this.filtrosRentabilidad).subscribe(
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

        // Cerrar ambos modales
        if (this.modalRefRentabilidad) {
          this.modalRefRentabilidad.hide();
        }
        if (this.modalRefRentabilidad) {
          this.modalRefRentabilidad.hide();
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
    this.apiService.getAll('compras/nums-ids').subscribe(numsIds => { 
        this.numeros_ids = numsIds;
    }, error => {this.alertService.error(error); });
  } 

  public imprimir(compra:any){
    window.open(this.apiService.baseUrl + '/api/compra/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
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
