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
import { ModalManagerService } from '@services/modal-manager.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { MHService } from '@services/MH.service';
import { SharedDataService } from '@services/shared-data.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../directives/lazy-image.directive';
import { Subject } from 'rxjs';
import { debounceTime, takeUntil } from 'rxjs/operators';

declare var $:any;

@Component({
    selector: 'app-compras',
    templateUrl: './compras.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
})

export class ComprasComponent extends BaseCrudComponent<any> implements OnInit, OnDestroy {
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
    public override saving:boolean = false;
    public sending:boolean = false;
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

    constructor(
        apiService: ApiService,
        public mhService: MHService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router,
        private route: ActivatedRoute,
        private sharedDataService: SharedDataService,
        private funcionalidadesService: FuncionalidadesService,
        private cdr: ChangeDetectorRef
    ){
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
                this.filtrarCompras();
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarCompras();
    }

    public override setPagination(event: any): void {
        this.filtros.page = event.page;
        this.filtrarCompras();
    }

    ngOnDestroy() {
        this.destroy$.next();
        this.destroy$.complete();
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

            this.filtrarCompras();
            this.cdr.markForCheck();
        });

        this.getNumsIds();
        this.sharedDataService.getProveedores()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (proveedores) => {
                    this.proveedores = proveedores;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
    }

    public override loadAll() {
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

        this.filtrarCompras();
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

    public filtrarCompras(){
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

        this.apiService.getAll('compras', this.filtros)
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

                    // Abrir el modal pasando compraCompleta como parámetro
                    // BaseCrudComponent hará una copia con { ...item }, pero eso está bien para las propiedades de primer nivel
                    this.openModal(template, compraCompleta);
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

    public override async onSubmit(item?: any, isStatusChange: boolean = false) {
        await super.onSubmit(item, isStatusChange);
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

    // setPagination() ahora se hereda de BaseFilteredPaginatedComponent

    public openDescargar(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public descargarCompras(){
        this.downloadingCompras = true; this.saving = true;
        this.apiService.export('compras/exportar', this.filtros)
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
        this.apiService.export('compras-detalles/exportar', this.filtros)
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

    openDTE(template: TemplateRef<any>, compra:any){
        this.compra = compra;
        this.openModal(template);
        this.alertService.modal = true;
        if(!this.compra.dte){
            this.emitirDTE();
        }
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
        if(compra.dte){
            if (confirm('¿Confirma anular la compra y el DTE?')) {
                this.compra = compra;
                this.saving = true;
                this.apiService.store('generarDTEAnuladoSujetoExcluidoCompra', this.compra)
                    .pipe(this.untilDestroyed())
                    .subscribe(dte => {
                        // this.alertService.success('DTE generado.');
                        this.compra.dte_invalidacion = dte;
                        this.mhService.firmarDTE(dte)
                            .pipe(this.untilDestroyed())
                            .subscribe(dteFirmado => {
                                this.compra.dte_invalidacion.firmaElectronica = dteFirmado.body;
                                // this.alertService.success('DTE firmado.');

                                this.mhService.anularDTE(this.compra, dteFirmado.body)
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

}

