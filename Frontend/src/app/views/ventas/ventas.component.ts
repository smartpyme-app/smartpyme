import { Component, OnInit, OnDestroy, TemplateRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { MHService } from '@services/MH.service';
import Swal from 'sweetalert2';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

@Component({
    selector: 'app-ventas',
    templateUrl: './ventas.component.html'
})
export class VentasComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  public ventas: any = {};
  public venta: any = {};
  public loading: boolean = false;
  public saving: boolean = false;
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
  public filtros: any = {};
  public filtrado: boolean = false;
  public consulting: boolean = false;
  public categorias: any[] = [];
  public marcas: any[] = [];
  public numeros_ids: any = [];
  public contabilidadHabilitada: boolean = false;
  public filtrosAcumulado: any = {
    inicio: '',
    fin: '',
    sucursales: [],
    categorias: [],
    marcas: [],
    detallePorSucursal: false,
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

  public modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private mhService: MHService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private router: Router,
    private route: ActivatedRoute,
    private funcionalidadesService: FuncionalidadesService
  ) {
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  protected untilDestroyed() {
    return takeUntil(this.destroy$);
  }

  protected openModal(template: TemplateRef<any>, item?: any) {
    if (item) {
      this.venta = item;
    }
    this.modalRef = this.modalService.show(template);
  }

  protected closeModal() {
    if (this.modalRef) {
      this.modalRef.hide();
    }
  }


  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.verificarAccesoContabilidad();

    this.route.queryParams.subscribe(params => {
      this.filtros = {
        buscador: params['buscador'] || '',
        id_proyecto: +params['id_proyecto'] || '',
        id_documento: +params['id_documento'] || '',
        id_cliente: +params['id_cliente'] || '',
        id_sucursal: +params['id_sucursal'] || '',
        id_usuario: +params['id_usuario'] || '',
        id_vendedor: +params['id_vendedor'] || '',
        id_canal: +params['id_canal'] || '',
        forma_pago: params['forma_pago'] || '',
        dte: params['dte'] || '',
        estado: params['estado'] || '',
        num_identificacion: params['num_identificacion'] || '',
        inicio: params['inicio'] || '',
        fin: params['fin'] || '',
        orden: params['orden'] || 'fecha',
        direccion: params['direccion'] || 'desc',
        paginate: +params['paginate'] || 10,
        page: +params['page'] || 1,
      };

      // Aplicar filtro de sucursal para usuarios no administradores si no hay filtro en URL
      if (this.apiService.auth_user().tipo != 'Administrador' && !params['id_sucursal']) {
        this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
      }

      this.filtrarVentas();
    });

    this.getNumsIds();

    // Cargar datos compartidos
    this.apiService.getAll('sucursales/list')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (sucursales) => {
          this.sucursales = sucursales;
        },
        error: (error) => {
          this.alertService.error(error);
        }
      });

    this.apiService.getAll('categorias/list')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (categorias: any) => {
          this.categorias = categorias;
        },
        error: (error) => {
          this.alertService.error(error);
        }
      });

    this.apiService.getAll('marcas/list')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (marcas: any) => {
          this.marcas = marcas;
        },
        error: (error) => {
          this.alertService.error(error);
        }
      });
  }

  public abrirModalFiltrosAcumulado(template: TemplateRef<any>) {
    this.modalRefAcumulado = this.modalService.show(template, {
      class: 'modal-lg',
    });
  }

  public abrirModalFiltrosPorMarca(template: TemplateRef<any>) {
    if (this.modalRefDescargar) {
      this.modalRefDescargar.hide();
      this.modalRefDescargar = undefined;
    }

    setTimeout(() => {
      this.modalRefPorMarca = this.modalService.show(template, {
        class: 'modal-md',
      });
    }, 100);

  }

  public abrirModalFiltrosPorUtilidades(template: TemplateRef<any>) {
    if (this.modalRefDescargar) {
      this.modalRefDescargar.hide();
      this.modalRefDescargar = undefined;
    }

    setTimeout(() => {
      this.modalRefPorMarca = this.modalService.show(template, {
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

  public loadAll() {
    this.filtros = {
      buscador: '',
      id_proyecto: '',
      id_documento: '',
      id_cliente: '',
      id_sucursal: '',
      id_usuario: '',
      id_vendedor: '',
      id_canal: '',
      forma_pago: '',
      dte: '',
      estado: '',
      num_identificacion: '',
      inicio: '',
      fin: '',
      orden: 'fecha',
      direccion: 'desc',
      paginate: 10,
      page: 1,
    };

      // Aplicar filtro de sucursal para usuarios no administradores
      const userTipo = this.apiService.auth_user().tipo;
      if((userTipo === 'Super Admin' || userTipo === 'Administrador') ){
        this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
      }

      this.filtrarVentas();
      
    }


  public filtrarVentas() {
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

    if (!this.filtros.id_cliente) {
      this.filtros.id_cliente = '';
    }

    if (!this.filtros.id_usuario) {
      this.filtros.id_usuario = '';
    }

    if (!this.filtros.id_vendedor) {
      this.filtros.id_vendedor = '';
    }

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

  public async setEstado(venta: any, estado: any) {
    if (estado == 'Pagada') {
      if (confirm('¿Confirma el pago de la venta?')) {
        venta.estado = estado;
        await this.onSubmit(venta, true);
      }
    }
    if (estado == 'Anulada') {
      if (confirm('¿Confirma la anulación de la venta?')) {
        venta.estado = estado;
        await this.onSubmit(venta, true);
      }
    }
  }

  public delete(id: number) {
    if (confirm('¿Desea eliminar el Registro?')) {
      this.apiService.delete('venta/', id).subscribe(
        (data) => {
          for (let i = 0; i < this.ventas['data'].length; i++) {
            if (this.ventas['data'][i].id == data.id)
              this.ventas['data'].splice(i, 1);
          }
          this.alertService.success('Venta eliminada', 'Venta eliminada exitosamente.');
        },
        (error) => {
          this.alertService.error(error);
        }
      );
    }
  }

  // setPagination() ahora se hereda de BasePaginatedComponent
  public setPagination(event: any): void {
    this.filtros.page = event.page;
    this.filtrarVentas();
  }

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
    // Cargar los datos completos de la venta antes de abrir el modal
    this.loading = true;
    this.apiService.read('venta/', venta.id)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (ventaCompleta: any) => {
          this.loading = false;

          // Cargar datos auxiliares
          if (!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos) {
            this.apiService.getAll('proyectos/list')
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
            this.apiService.getAll('documentos/list')
              .pipe(this.untilDestroyed())
              .subscribe({
                next: (documentos) => {
                  this.documentos = documentos;
                  this.documentos = this.documentos.filter(
                    (x: any) => x.id_sucursal == ventaCompleta.id_sucursal
                  );
                },
                error: (error) => {
                  this.alertService.error(error);
                }
              });
          }

          if (!this.formaPagos.length) {
            this.apiService.getAll('formas-de-pago/list')
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
            this.apiService.getAll('usuarios/list')
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
            this.apiService.getAll('canales/list')
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

          // Crear una copia profunda del objeto para evitar que los cambios se reflejen inmediatamente en el listado
          const ventaCopia = JSON.parse(JSON.stringify(ventaCompleta));
          // Abrir el modal pasando la copia como parámetro
          this.openModal(template, ventaCopia);
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      });
  }

  public openFilter(template: TemplateRef<any>) {
    if (!this.clientes.length) {
      this.apiService.getAll('clientes/list')
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
      this.apiService.getAll('documentos/list-nombre')
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
      this.apiService.getAll('formas-de-pago/list')
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
      this.apiService.getAll('usuarios/list')
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
      this.apiService.getAll('canales/list')
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
      this.apiService.getAll('proyectos/list')
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
    this.modalRefDescargar = this.modalService.show(template);
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
          this.modalRefAcumulado.hide();
          this.modalRefAcumulado = undefined;
        }
        if (this.modalRefDescargar) {
          this.modalRefDescargar.hide();
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
          detallePorSucursal: false,
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

  public setDocumento(id_documento: any) {
    let documento = this.documentos.find((x: any) => x.id == id_documento);
    if (documento) {
      this.venta.nombre_documento = documento.nombre;
      this.venta.id_documento = documento.id;
      this.venta.correlativo = documento.correlativo;
    }
  }

  public async onSubmit(item?: any, isStatusChange?: boolean): Promise<void> {
    const ventaToSave = item || this.venta;
    this.saving = true;
    try {
      const venta = await this.apiService.store('venta', ventaToSave)
        .pipe(this.untilDestroyed())
        .toPromise();
      this.venta = {};
      this.saving = false;
      if (this.modalRef) {
        this.closeModal();
      }
      this.alertService.success('Venta guardada', 'La venta fue guardada exitosamente.');
      // Actualizar el listado después de guardar
      this.filtrarVentas();
    } catch (error: any) {
      this.alertService.error(error);
      this.saving = false;
    }
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

  verificarAccesoContabilidad() {
    this.funcionalidadesService.verificarAcceso('contabilidad')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (acceso: any) => {
          this.contabilidadHabilitada = acceso === true || acceso === 1;
        },
        error: (error) => {
          console.error('Error al verificar acceso a contabilidad:', error);
          this.contabilidadHabilitada = false;
        }
      });
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
