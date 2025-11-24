import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { SharedDataService } from '@services/shared-data.service';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { CrearAbonoVentaComponent } from '@shared/modals/crear-abono-venta/crear-abono-venta.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';
import { LazyImageDirective } from '../../directives/lazy-image.directive';

@Component({
    selector: 'app-ventas',
    templateUrl: './ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ImportarExcelComponent, PaginationComponent, CrearAbonoVentaComponent, TruncatePipe, PopoverModule, TooltipModule, NgSelectModule, LazyImageDirective],

})
export class VentasComponent extends BaseCrudComponent<any> implements OnInit {
  public ventas: any = {};
  public venta: any = {};
  public sending: boolean = false;
  public downloadingDetalles: boolean = false;
  public downloadingVentas: boolean = false;
  public reporteSeleccionado: string = '';

  public clientes: any = [];
  public usuario: any = {};
  public usuarios: any = [];
  public sucursales: any = [];
  public formaPagos: any = [];
  public documentos: any = [];
  public canales: any = [];
  public proyectos: any = [];
  public override filtros: any = {};
  public filtrado: boolean = false;
  public consulting: boolean = false;
  public categorias: any[] = [];
  public marcas: any[] = [];
  public numeros_ids: any = [];
  public filtrosAcumulado: any = {
    inicio: '',
    fin: '',
    sucursales: [],
    categorias: [],
    marcas: [],
  };
  public filtrosPorMarca: any = {
    inicio: '',
    fin: '',
    id_empresa: this.apiService.auth_user().empresa.id,
  };

  public modalRefDescargar!: any; // BsModalRef
  public modalRefAcumulado!: any; // BsModalRef
  public modalRefPorMarca!: any; // BsModalRef
  downloadingPorMarca: boolean = false;

  constructor(
    protected override apiService: ApiService,
    private mhService: MHService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private sharedDataService: SharedDataService
  ) {
    super(apiService, alertService, modalManager, {
      endpoint: 'venta',
      itemsProperty: 'ventas',
      itemProperty: 'venta',
      reloadAfterSave: false,
      reloadAfterDelete: false,
      messages: {
        created: 'La venta fue guardada exitosamente.',
        updated: 'La venta fue guardada exitosamente.',
        deleted: 'Venta eliminada exitosamente.',
        createTitle: 'Venta guardada',
        updateTitle: 'Venta guardada',
        deleteTitle: 'Venta eliminada',
        deleteConfirm: '¿Desea eliminar el Registro?'
      },
      afterSave: () => {
        this.venta = {};
        this.filtrarVentas();
      }
    });
  }

  protected aplicarFiltros(): void {
    this.filtrarVentas();
  }

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.loadAll();
    this.getNumsIds();

    // Cargar datos compartidos usando SharedDataService
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

    this.sharedDataService.getCategorias()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (categorias) => {
          this.categorias = categorias;
        },
        error: (error) => {
          this.alertService.error(error);
        }
      });

    this.sharedDataService.getMarcas()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (marcas) => {
          this.marcas = marcas;
        },
        error: (error) => {
          this.alertService.error(error);
        }
      });
  }

  public abrirModalFiltrosAcumulado(template: TemplateRef<any>) {
    this.modalRefAcumulado = this.modalManager.openModal(template, {
      class: 'modal-lg',
    });
  }

  public abrirModalFiltrosPorMarca(template: TemplateRef<any>) {
    if (this.modalRefDescargar) {
      this.modalManager.closeModal(this.modalRefDescargar);
      this.modalRefDescargar = undefined;
    }

    setTimeout(() => {
      this.modalRefPorMarca = this.modalManager.openModal(template, {
        class: 'modal-md',
      });
    }, 100);

  }

  public abrirModalFiltrosPorUtilidades(template: TemplateRef<any>) {
    if (this.modalRefDescargar) {
      this.modalManager.closeModal(this.modalRefDescargar);
      this.modalRefDescargar = undefined;
    }

    setTimeout(() => {
      this.modalRefPorMarca = this.modalManager.openModal(template, {
        class: 'modal-md',
      });
    }, 100);

  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.filtrarVentas();
  }

  public override loadAll() {
    const filtrosGuardados = localStorage.getItem('ventasFiltros');

    if (filtrosGuardados) {
      this.filtros = JSON.parse(filtrosGuardados);
      // console.log(this.filtros);
    } else {

      this.filtros = {
        id_sucursal: '',
        id_cliente: '',
        id_usuario: '',
        id_vendedor: '',
        id_canal: '',
        id_documento: '',
        id_proyecto: '',
        num_identificacion: '',
        dte: '',
        forma_pago: '',
        estado: '',
        buscador: '',
        orden: 'fecha',
        direccion: 'desc',
        paginate: 10
      };

      // Aplicar filtro de sucursal para usuarios no administradores
      if((this.apiService.validateRole('super_admin', false) || this.apiService.validateRole('admin', false)) ){
        this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
      }
    }

        this.filtrarVentas();
    }

  public filtrarVentas() {
    localStorage.setItem('ventasFiltros', JSON.stringify(this.filtros));
    this.loading = true;
    this.apiService.getAll('ventas', this.filtros).subscribe(
      (ventas) => {
        this.ventas = ventas;
        this.loading = false;
        if (this.modalRef) {
          this.closeModal();
        }
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public setEstado(venta: any, estado: any) {
    if (estado == 'Pagada') {
      if (confirm('¿Confirma el pago de la venta?')) {
        venta.estado = estado;
        this.onSubmit(venta, true);
      }
    }
    if (estado == 'Anulada') {
      if (confirm('¿Confirma la anulación de la venta?')) {
        venta.estado = estado;
        this.onSubmit(venta, true);
      }
    }
  }

  public override delete(id: number) {
    super.delete(id);
  }

  // setPagination() ahora se hereda de BasePaginatedComponent

  public reemprimir(venta: any) {
    window.open(
      this.apiService.baseUrl +
      '/api/reporte/facturacion/' +
      venta.id +
      '?token=' +
      this.apiService.auth_token(),
      'Impresión',
      'width=400'
    );
  }

  // Editar

  public openModalEdit(template: TemplateRef<any>, venta: any) {
    this.venta = venta;

    if (!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos) {
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

    if (!this.documentos.length) {
      this.sharedDataService.getDocumentos()
        .pipe(this.untilDestroyed())
        .subscribe({
          next: (documentos) => {
            this.documentos = documentos;
            this.documentos = this.documentos.filter(
              (x: any) => x.id_sucursal == this.venta.id_sucursal
            );
          },
          error: (error) => {
            this.alertService.error(error);
          }
        });
    }

    if (!this.formaPagos.length) {
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

    if (!this.usuarios.length) {
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

    if (!this.canales.length) {
      this.sharedDataService.getCanales()
        .pipe(this.untilDestroyed())
        .subscribe({
          next: (canales) => {
            this.canales = canales;
          },
          error: (error) => {
            this.alertService.error(error);
          }
        });
    }

    this.openModal(template);
  }

  public openFilter(template: TemplateRef<any>) {
    if (!this.clientes.length) {
      this.sharedDataService.getClientes()
        .pipe(this.untilDestroyed())
        .subscribe({
          next: (clientes) => {
            this.clientes = clientes;
          },
          error: (error) => {
            this.alertService.error(error);
          }
        });
    }

    if (!this.documentos.length) {
      this.sharedDataService.getDocumentosPorNombre()
        .pipe(this.untilDestroyed())
        .subscribe({
          next: (documentos) => {
            this.documentos = documentos;
          },
          error: (error) => {
            this.alertService.error(error);
          }
        });
    }

    if (!this.formaPagos.length) {
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

    if (!this.usuarios.length) {
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

    if (!this.canales.length) {
      this.sharedDataService.getCanales()
        .pipe(this.untilDestroyed())
        .subscribe({
          next: (canales) => {
            this.canales = canales;
          },
          error: (error) => {
            this.alertService.error(error);
          }
        });
    }

    if (
      !this.proyectos.length &&
      this.apiService.auth_user().empresa.modulo_proyectos
    ) {
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

  // public openDescargar(template: TemplateRef<any>) {
  //   this.openModal(template);
  // }
  public openDescargar(template: TemplateRef<any>) {
    this.reporteSeleccionado = '';
    this.modalRefDescargar = this.modalManager.openModal(template);
  }

  public descargarVentas() {
    this.downloadingVentas = true;
    this.saving = true;
    this.apiService.export('ventas/exportar', this.filtros).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ventas.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloadingVentas = false;
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloadingVentas = false;
        this.saving = false;
      }
    );
  }



  public descargarAcumulado() {
    this.downloadingVentas = true;
    this.saving = true;

    this.apiService.exportAcumulado('ventas-acumulado/exportar', this.filtrosAcumulado).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ventas-acumulado.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Cerrar ambos modales
        if (this.modalRefAcumulado) {
          this.modalManager.closeModal(this.modalRefAcumulado);
          this.modalRefAcumulado = undefined;
        }
        if (this.modalRefDescargar) {
          this.modalManager.closeModal(this.modalRefDescargar);
          this.modalRefDescargar = undefined;
        }

        this.downloadingVentas = false;
        this.saving = false;


        this.filtrosAcumulado = {
          inicio: '',
          fin: '',
          sucursales: [],
          categorias: [],
          marcas: [],
        };
      },
      (error) => {
        this.alertService.error(error);
        this.downloadingVentas = false;
        this.saving = false;
      }
    );
  }


  public descargarDetalles() {
    this.downloadingDetalles = true; this.saving = true;
    this.apiService.export('ventas-detalles/exportar', this.filtros).subscribe((data: Blob) => {
      const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'ventas-detalles.xlsx';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      this.downloadingDetalles = false; this.saving = false;
    }, (error) => { this.alertService.error(error); this.downloadingDetalles = false; this.saving = false; }
    );
  }

  public descargarDetallesDiario() {
    this.downloadingDetalles = true; this.saving = true;
    this.apiService.export('ventas-detalles/exportar/diario', null).subscribe((data: Blob) => {
      const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'ventas-detalles-diario.xlsx';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      this.downloadingDetalles = false; this.saving = false;
    }, (error) => { this.alertService.error(error); this.downloadingDetalles = false; this.saving = false; }
    );
  }

  public imprimir(venta: any) {
    window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token());
  }

  public linkWompi(venta: any) {
    window.open(this.apiService.baseUrl + '/api/venta/wompi-link/' + venta.id + '?token=' + this.apiService.auth_token());
  }

    public override async onSubmit(item?: any, isStatusChange: boolean = false) {
        await super.onSubmit(item, isStatusChange);
    }

    public setRecurrencia(venta:any){
        this.venta = venta;
        this.venta.recurrente = true;

        this.apiService.store('venta', this.venta).subscribe(venta => {
            this.venta = {};
            this.alertService.success('Venta guardada', 'La venta se marco como recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

  }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.openModal(template);
    }


  // DTE

    openDTE(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.openModal(template);
        if(!this.venta.dte){
            this.emitirDTE();
        }
    }

  imprimirDTEPDF(venta: any) {
    window.open(this.apiService.baseUrl + '/api/reporte/dte/' + venta.id + '/' + venta.tipo_dte + '/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
  }

  imprimirDTEJSON(venta: any) {
    window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + venta.id + '/' + venta.tipo_dte + '/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
  }

  emitirDTE() {
    this.saving = true;
    this.mhService.emitirDTE(this.venta).then((ventaActualizada) => {
      this.venta = { ...ventaActualizada };
      const index = this.ventas.data.findIndex((v: any) => v.id === ventaActualizada.id);
      if (index !== -1) {
        this.ventas.data[index] = { ...ventaActualizada };
      }

      this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
      this.saving = false;
      this.enviarDTE(this.venta);
    }).catch((error) => {
      this.saving = false;
      console.log(error);
      if (error == '[identificacion.codigoGeneracion] YA EXISTE UN REGISTRO CON ESE VALOR') {
        this.consultarDTE();
      }
      else if (error.status) {
        this.alertService.warning('Hubo un problema', error);
      } else {
        this.venta.errores = error;
      }
    });
  }

  enviarDTE(venta: any) {
    this.sending = true;
    this.apiService.store('enviarDTE', venta).subscribe(dte => {
      this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
      this.sending = false;
      setTimeout(() => {
        this.modalRef?.hide();
      }, 5000);
    }, error => { this.alertService.error(error); this.sending = false; });
  }

  emitirEnContingencia(venta: any) {
    this.venta = venta;
    this.saving = true;
    this.mhService.emitirDTEContingencia(this.venta).then((venta) => {
      this.venta = venta;
      this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
      this.saving = false;
    }).catch((error) => {
      this.saving = false;
      this.alertService.warning('Hubo un problema', error);
    });
  }

  anularDTE(venta: any) {
    this.venta = venta;
    if (venta.sello_mh && !venta.dte_invalidacion) {
      if (confirm('¿Confirma anular la venta y el DTE?')) {
        this.venta = venta;
        this.saving = true;
        this.apiService.store('generarDTEAnulado', this.venta).subscribe(dte => {
          // this.alertService.success('DTE generado.');
          this.venta.dte_invalidacion = dte;
          this.mhService.firmarDTE(dte).subscribe(dteFirmado => {
            this.venta.dte_invalidacion.firmaElectronica = dteFirmado.body;

            if (dteFirmado.status == 'ERROR') {
              this.alertService.warning('Hubo un problema', dteFirmado.body.mensaje);
            }

            this.mhService.anularDTE(this.venta, dteFirmado.body).subscribe(dte => {
              if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                this.venta.dte_invalidacion.sello = dte.selloRecibido;
                this.venta.sello_mh = dte.selloRecibido;
                this.venta.estado = 'Anulada';
                this.onSubmit();
                if (this.venta.id_cliente) {
                  setTimeout(() => {
                    this.enviarDTE(this.venta);
                  }, 3000);
                }
              }

              this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
            }, error => {
              if (error.error.descripcionMsg) {
                this.alertService.warning('Hubo un problema', error.error.descripcionMsg);
              }
              if (error.error.observaciones.length > 0) {
                this.alertService.warning('Hubo un problema', error.error.observaciones);
              }
              this.saving = false;
            });

          }, error => { this.alertService.error(error); this.saving = false; });

        }, error => { this.alertService.error(error); this.saving = false; });
      }
    }
    else {
      if (confirm('¿Confirma anular la venta?')) {
        this.venta.estado = 'Anulada';
        this.onSubmit();
      }
    }
  }

  consultarDTE() {
    this.consulting = true;
    let data = {
      codigoGeneracion: this.venta.dte.identificacion.codigoGeneracion,
      fechaEmi: this.venta.dte.identificacion.fecEmi,
      ambiente: this.venta.dte.identificacion.ambiente
    };

    setTimeout(() => {

      this.apiService.store('consultarDTE', data).subscribe(dte => {
        if (dte && dte.selloVal) {
          this.venta.dte.sello = dte.selloVal;
          this.venta.dte.selloRecibido = dte.selloVal;
          this.venta.sello_mh = dte.selloVal;
          this.apiService.store('venta', this.venta).subscribe(data => {
            this.alertService.success('Sello recibido', 'El DTE ha sido sellado.');
            if (this.venta.cliente_id) {
              this.enviarDTE(this.venta);
            }
            setTimeout(() => {
              this.modalRef?.hide();
            }, 500);
            this.consulting = false;
          }, error => { this.alertService.error(error); });
        }
        else if (dte) {
          this.consulting = false;
          this.alertService.info('No se obtuvo el sello', 'El DTE no ha sido emitido.');
        } else {
          this.consulting = false;
          this.alertService.info('No se obtuvo el sello', 'Hacienda no devolvió el sello.');
        }
      }, error => {
        this.consulting = false;
        this.alertService.warning('Hubo un problema', error);
      });
    }, 1000);
  }

  public limpiarFiltros() {
    localStorage.removeItem('ventasFiltros');
    this.loadAll();
  }

  public filtrosActivos(): boolean {
    return Object.values(this.filtros).some(valor => {
      if (Array.isArray(valor)) {
        return valor.length > 0;
      }
      return valor !== '' && valor !== null && valor !== undefined;
    });
  }

  public isColumnEnabled(columnName: string): boolean {
    return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
  }


  getNumsIds() {
    this.apiService.getAll('ventas/nums-ids').subscribe(numsIds => {
      this.numeros_ids = numsIds;
    }, error => { this.alertService.error(error); });
  }

  generarPartidaContable(venta:any){
    this.apiService.store('contabilidad/partida/venta', venta).subscribe(venta => {
      this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
    },error => {this.alertService.error(error);});
  }

  descargarPorMarcasPorMes() {
    this.downloadingPorMarca = true;
    this.saving = true;
    this.filtrosPorMarca.inicio = this.filtrosPorMarca.inicio;
    this.filtrosPorMarca.fin = this.filtrosPorMarca.fin;
    this.apiService.export('ventas-por-marcas/exportar', this.filtrosPorMarca).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ventas-por-marcas_' + this.filtrosPorMarca.inicio + '_' + this.filtrosPorMarca.fin + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloadingPorMarca = false;
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloadingPorMarca = false;
        this.saving = false;
      }
    );
  }

  public descargarPorUtilidades() {
    this.downloadingPorMarca = true;
    this.saving = true;
    this.filtrosPorMarca.inicio = this.filtrosPorMarca.inicio;
    this.filtrosPorMarca.fin = this.filtrosPorMarca.fin;
    this.apiService.export('ventas-por-utilidades/exportar', this.filtrosPorMarca).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ventas-por-utilidades_' + this.filtrosPorMarca.inicio + '_' + this.filtrosPorMarca.fin + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloadingPorMarca = false;
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloadingPorMarca = false;
        this.saving = false;
      }
    );
  }

  public descargarReportePorMarcaOUtilidades() {
    if (this.reporteSeleccionado === 'marca') {
      this.descargarPorMarcasPorMes();
    } else if (this.reporteSeleccionado === 'utilidades') {
      this.descargarPorUtilidades();
    }
  }

  public generarTrasladoEmpresa(venta: any) {
    Swal.fire({
      title: '¿Confirmar traslado?',
      text: '¿Está seguro de generar el traslado a empresa para esta venta?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, generar traslado',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.store('compra/generar-compra-desde-orden', venta).subscribe(venta => {
          this.alertService.success('Traslado generado.', 'El traslado fue generado exitosamente.');
        }, error => { this.alertService.error(error); });
      }
    });
  }



}
