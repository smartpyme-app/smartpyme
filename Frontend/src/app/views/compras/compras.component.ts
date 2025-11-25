import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
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
import { MHService } from '@services/MH.service';
import { SharedDataService } from '@services/shared-data.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../directives/lazy-image.directive';

declare var $:any;

@Component({
    selector: 'app-compras',
    templateUrl: './compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, LazyImageDirective],

})

export class ComprasComponent extends BaseCrudComponent<any> implements OnInit {

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

    constructor(
        apiService: ApiService, 
        public mhService: MHService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router, 
        private route: ActivatedRoute,
        private sharedDataService: SharedDataService
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

    ngOnInit() {

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
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarCompras();
        });

        this.getNumsIds();
        this.sharedDataService.getProveedores()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (proveedores) => {
                    this.proveedores = proveedores;
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
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.num_identificacion = '';

        this.filtrarCompras();
    }

    public filtrarCompras(){
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

        this.apiService.getAll('compras', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(compras => {
                this.compras = compras;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
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
        this.compra = compra;

        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.sharedDataService.getProyectos()
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (proyectos) => {
                        this.proyectos = proyectos;
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
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }

        this.openModal(template);
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
            },error => {this.alertService.error(error); this.saving = false; });

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
          }, (error) => {this.alertService.error(error); this.downloadingCompras = false; this.saving = false;}
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
          }, (error) => {this.alertService.error(error); this.downloadingDetalles = false; this.saving = false; }
        );
    }

    public openAbono(template: TemplateRef<any>, compra:any){
        this.compra = compra;
        this.alertService.modal = true;
        this.openModal(template);
    }

    public openFilter(template: TemplateRef<any>) {

        this.sharedDataService.getDocumentos()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (documentos) => {
                    this.documentos = documentos;
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
        },error => {this.alertService.error(error); this.sending = false; });
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
        }, error => {this.alertService.error(error); });
  }

  generarPartidaContable(compra:any){
    this.apiService.store('contabilidad/partida/compra', compra)
        .pipe(this.untilDestroyed())
        .subscribe(compra => {
      this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
    },error => {this.alertService.error(error);});
  }

}

