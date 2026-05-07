import { Component, OnInit, OnDestroy, TemplateRef, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { MHService } from '@services/MH.service';
import Swal from 'sweetalert2';
import { Subject } from 'rxjs';
import { debounceTime, takeUntil } from 'rxjs/operators';

@Component({
    selector: 'app-ventas',
    templateUrl: './ventas.component.html'
})
export class VentasComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private searchSubject$ = new Subject<void>();
  public ventas: any = {};
  public venta: any = {};
  public loading: boolean = false;
  public saving: boolean = false;
  public sending: boolean = false;
  public downloadingDetalles: boolean = false;
  public downloadingVentas: boolean = false;
  public downloadingCobrosVendedor: boolean = false;
  public reporteSeleccionado: string = '';

  /** Años disponibles: desde 2023 hasta el año en curso (más reciente primero). */
  public get aniosDisponiblesExportVentas(): number[] {
    const hasta = Math.max(new Date().getFullYear(), 2023);
    const desde = 2023;
    const anios: number[] = [];
    for (let year = hasta; year >= desde; year--) {
      anios.push(year);
    }
    return anios;
  }

  // Propiedades booleanas para tipos de reporte
  public esReporteMarca: boolean = false;
  public esReporteUtilidades: boolean = false;
  public esReporteCobrosVendedor: boolean = false;

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
  
  // Campos para anulación
  public fechaAnulacion: string = '';
  public tipoAnulacion: number = 2;
  public motivoAnulacion: string = '';
  public codigoGeneracionRemplazo: string = '';
  public motivosAnulacion: any[] = [
    { valor: 1, texto: 'Error en la Información del Documento Tributario Electrónico a invalidar' },
    { valor: 2, texto: 'Rescindir de la operación realizada' },
    { valor: 3, texto: 'Otro' }
  ];
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
  public filtrosCobrosVendedor: any = {
    inicio: '',
    fin: '',
    id_sucursal: '',
    id_vendedor: '',
  };

  /**
   * Período para exportar "Detalles por producto" y "Detalles ventas totales" cuando el listado no tiene inicio/fin en la URL.
   */
  public tipoPeriodoExport: 'rango' | 'anio' | 'mes' | 'anio_mes' = 'anio_mes';
  public exportPeriodoRangoInicio = '';
  public exportPeriodoRangoFin = '';
  public exportPeriodoAnio: number = new Date().getFullYear();
  public exportPeriodoMes = new Date().getMonth() + 1;
  public readonly mesesExportPeriodo = [
    { v: 1, n: 'Enero' },
    { v: 2, n: 'Febrero' },
    { v: 3, n: 'Marzo' },
    { v: 4, n: 'Abril' },
    { v: 5, n: 'Mayo' },
    { v: 6, n: 'Junio' },
    { v: 7, n: 'Julio' },
    { v: 8, n: 'Agosto' },
    { v: 9, n: 'Septiembre' },
    { v: 10, n: 'Octubre' },
    { v: 11, n: 'Noviembre' },
    { v: 12, n: 'Diciembre' },
  ];

  public modalRefDescargar!: any; // BsModalRef
  public modalRefAcumulado!: any; // BsModalRef
  public modalRefPorMarca!: any; // BsModalRef
  public modalRefCobrosVendedor!: any; // BsModalRef
  downloadingPorMarca: boolean = false;

  public modalRef!: BsModalRef;

  @ViewChild('modalAnulacion')
  public modalAnulacionTemplate!: TemplateRef<any>;

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

  protected openModal(template: TemplateRef<any>, item?: any, config?: { class?: string }) {
    if (item) {
      this.venta = item;
    }
    this.modalRef = this.modalService.show(template, config || {});
  }

  protected closeModal() {
    if (this.modalRef) {
      this.modalRef.hide();
    }
  }


  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.verificarAccesoContabilidad();

    this.searchSubject$.pipe(
      debounceTime(400),
      takeUntil(this.destroy$)
    ).subscribe(() => this.filtrarVentas());

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

      this.filtrarVentas(false);
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

  public abrirModalFiltrosCobrosVendedor(template: TemplateRef<any>) {
    if (this.modalRefDescargar) {
      this.modalRefDescargar.hide();
      this.modalRefDescargar = undefined;
    }

    // Cargar usuarios si no están cargados
    if (!this.usuarios.length) {
      this.apiService.getAll('usuarios/list')
        .pipe(this.untilDestroyed())
        .subscribe({
          next: (usuarios) => {
            this.usuarios = usuarios;
            this.abrirModalCobrosVendedor(template);
          },
          error: (error) => {
            this.alertService.error(error);
          }
        });
    } else {
      this.abrirModalCobrosVendedor(template);
    }
  }

  private abrirModalCobrosVendedor(template: TemplateRef<any>) {
    // Inicializar filtros con valores por defecto si están vacíos
    if (!this.filtrosCobrosVendedor.inicio && !this.filtrosCobrosVendedor.fin) {
      this.filtrosCobrosVendedor.inicio = this.filtros.inicio || '';
      this.filtrosCobrosVendedor.fin = this.filtros.fin || '';
      this.filtrosCobrosVendedor.id_sucursal = this.filtros.id_sucursal || '';
      this.filtrosCobrosVendedor.id_vendedor = this.filtros.id_vendedor || '';
    }

    setTimeout(() => {
      this.modalRefCobrosVendedor = this.modalService.show(template, {
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

      this.filtrarVentas(false);
      
    }


  public onBuscadorInput() {
    this.searchSubject$.next();
  }

  /**
   * @param resetPage Si es true (por defecto), vuelve a la página 1 (búsqueda, filtros, orden, paginate).
   *                  false al paginar o al sincronizar desde la URL.
   */
  public filtrarVentas(resetPage = true): void {
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
    this.filtrarVentas(false);
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
    // this.loading = true;
    this.apiService.read('venta/', venta.id)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (ventaCompleta: any) => {
          this.loading = false;

          // Crear una copia profunda del objeto para evitar que los cambios se reflejen inmediatamente en el listado
          const ventaCopia = JSON.parse(JSON.stringify(ventaCompleta));
          if (!ventaCopia.condicion) {
            ventaCopia.condicion = ventaCopia.estado === 'Pendiente' ? 'Crédito' : 'Contado';
          }

          const abrirModalCuandoListo = () => {
            this.openModal(template, ventaCopia, { class: 'modal-xl' });
          };

          // Cargar datos auxiliares (en paralelo cuando aplique)
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

          // Métodos de pago: usar el mismo endpoint que en facturación (formas-de-pago/list) y esperar a que carguen antes de abrir el modal
          if (!this.formaPagos.length) {
            this.apiService.getAll('formas-de-pago/list')
              .pipe(this.untilDestroyed())
              .subscribe({
                next: (formaPagos) => {
                  this.formaPagos = formaPagos;
                  abrirModalCuandoListo();
                },
                error: (error) => {
                  this.alertService.error(error);
                  abrirModalCuandoListo();
                }
              });
          } else {
            abrirModalCuandoListo();
          }
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
    // Resetear todas las propiedades booleanas
    this.esReporteMarca = false;
    this.esReporteUtilidades = false;
    this.esReporteCobrosVendedor = false;
    this.resetExportPeriodo();
    this.modalRefDescargar = this.modalService.show(template);
  }

  /** True si el listado tiene rango de fechas en la URL (evita export histórico sin límite). */
  public get tieneRangoFechaEnListado(): boolean {
    const i = this.filtros?.inicio;
    const f = this.filtros?.fin;
    return !!(i && f && String(i).trim() !== '' && String(f).trim() !== '');
  }

  /** Válido para export "detalles por producto" y "ventas totales" (mismo período en el modal). */
  public get puedeDescargarConPeriodoExport(): boolean {
    return this.buildFechasExportPeriodo() !== null;
  }

  public get anioEnCursoParaMes(): number {
    return new Date().getFullYear();
  }

  private resetExportPeriodo(): void {
    this.tipoPeriodoExport = 'anio_mes';
    const d = new Date();
    this.exportPeriodoAnio = d.getFullYear();
    this.exportPeriodoMes = d.getMonth() + 1;
    this.exportPeriodoRangoInicio = '';
    this.exportPeriodoRangoFin = '';
  }

  private pad2ExportPeriodo(n: number): string {
    return String(n).padStart(2, '0');
  }

  private ultimoDiaDelMesExportPeriodo(anio: number, mes: number): number {
    return new Date(anio, mes, 0).getDate();
  }

  /** Diferencia en días enteros entre dos fechas ISO (YYYY-MM-DD). Usa mediodía local para evitar DST. */
  private diasEntreFechasIso(inicioIso: string, finIso: string): number {
    const s = new Date(inicioIso + 'T12:00:00');
    const e = new Date(finIso + 'T12:00:00');
    return Math.floor((e.getTime() - s.getTime()) / 86400000);
  }

  /** Máximo de días permitidos entre inicio y fin en modo rango (no más de un año). */
  private static readonly MAX_DIAS_RANGO_EXPORT_PERIOD = 365;

  /**
   * True si el rango elegido supera un año (solo modo rango, sin filtro en URL).
   */
  public get exportPeriodoRangoSuperaUnAnio(): boolean {
    if (this.tieneRangoFechaEnListado || this.tipoPeriodoExport !== 'rango') {
      return false;
    }
    const ini = this.exportPeriodoRangoInicio?.trim();
    const fin = this.exportPeriodoRangoFin?.trim();
    if (!ini || !fin || ini > fin) {
      return false;
    }
    return (
      this.diasEntreFechasIso(ini, fin) > VentasComponent.MAX_DIAS_RANGO_EXPORT_PERIOD
    );
  }

  /**
   * Fechas efectivas para export de detalles / ventas totales: URL del listado o período del modal.
   */
  public buildFechasExportPeriodo(): { inicio: string; fin: string } | null {
    if (this.tieneRangoFechaEnListado) {
      return {
        inicio: String(this.filtros.inicio).trim(),
        fin: String(this.filtros.fin).trim(),
      };
    }
    switch (this.tipoPeriodoExport) {
      case 'rango': {
        const ini = this.exportPeriodoRangoInicio?.trim();
        const fin = this.exportPeriodoRangoFin?.trim();
        if (!ini || !fin) return null;
        if (ini > fin) return null;
        if (
          this.diasEntreFechasIso(ini, fin) >
          VentasComponent.MAX_DIAS_RANGO_EXPORT_PERIOD
        ) {
          return null;
        }
        return { inicio: ini, fin };
      }
      case 'anio': {
        const y = +this.exportPeriodoAnio;
        if (!y || y < 2000 || y > 2100) return null;
        return { inicio: `${y}-01-01`, fin: `${y}-12-31` };
      }
      case 'mes': {
        const y = new Date().getFullYear();
        const m = +this.exportPeriodoMes;
        if (m < 1 || m > 12) return null;
        const ud = this.ultimoDiaDelMesExportPeriodo(y, m);
        return {
          inicio: `${y}-${this.pad2ExportPeriodo(m)}-01`,
          fin: `${y}-${this.pad2ExportPeriodo(m)}-${this.pad2ExportPeriodo(ud)}`,
        };
      }
      case 'anio_mes': {
        const y = +this.exportPeriodoAnio;
        const m = +this.exportPeriodoMes;
        if (!y || y < 2000 || y > 2100 || m < 1 || m > 12) return null;
        const ud = this.ultimoDiaDelMesExportPeriodo(y, m);
        return {
          inicio: `${y}-${this.pad2ExportPeriodo(m)}-01`,
          fin: `${y}-${this.pad2ExportPeriodo(m)}-${this.pad2ExportPeriodo(ud)}`,
        };
      }
      default:
        return null;
    }
  }

  private alertPeriodoExportInvalido(): void {
    if (
      !this.tieneRangoFechaEnListado &&
      this.tipoPeriodoExport === 'rango' &&
      this.exportPeriodoRangoSuperaUnAnio
    ) {
      this.alertService.error(
        'El rango de fechas no puede superar un año (365 días entre inicio y fin).'
      );
    } else {
      this.alertService.error(
        'Debe indicar un período válido para el reporte (rango, año, mes o año y mes).'
      );
    }
  }

  public descargarVentas() {
    const fechas = this.buildFechasExportPeriodo();
    if (!fechas) {
      this.alertPeriodoExportInvalido();
      return;
    }
    const base = { ...this.filtros, inicio: fechas.inicio, fin: fechas.fin };
    const filtrosExport = { ...base };
    delete (filtrosExport as any).anio;

    this.downloadingVentas = true;
    this.saving = true;
    const sufijoArchivo = `${fechas.inicio}_a_${fechas.fin}`.replace(/[^0-9a-z_-]+/gi, '_');
    this.apiService.export('ventas/exportar', filtrosExport).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ventas-${sufijoArchivo}.xlsx`;
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
    const fechas = this.buildFechasExportPeriodo();
    if (!fechas) {
      this.alertPeriodoExportInvalido();
      return;
    }
    const filtrosExport = { ...this.filtros, inicio: fechas.inicio, fin: fechas.fin };
    this.downloadingDetalles = true;
    this.saving = true;
    this.apiService.export('ventas-detalles/exportar', filtrosExport).subscribe((data: Blob) => {
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

  public onCondicionChange() {
    if (this.venta.condicion === 'Crédito') {
      // Si se cambia a Crédito, establecer estado como Pendiente si no está ya establecido
      if (this.venta.estado !== 'Pendiente' && this.venta.estado !== 'Anulada') {
        this.venta.estado = 'Pendiente';
      }
      // Si no hay fecha de pago, establecer una fecha por defecto (30 días desde hoy)
      if (!this.venta.fecha_pago) {
        const fecha = new Date();
        fecha.setDate(fecha.getDate() + 30);
        this.venta.fecha_pago = fecha.toISOString().split('T')[0];
      }
    } else if (this.venta.condicion === 'Contado') {
      // Si se cambia a Contado, establecer estado como Pagada si no está anulada
      if (this.venta.estado !== 'Anulada') {
        this.venta.estado = 'Pagada';
      }
      // Establecer fecha de pago como la fecha actual
      const fecha = new Date();
      this.venta.fecha_pago = fecha.toISOString().split('T')[0];
    }
  }

  public async onSubmit(item?: any, isStatusChange?: boolean): Promise<void> {
    const ventaToSave = item || this.venta;
    
    // Validar que el ID esté presente para editar
    if (!ventaToSave.id) {
      this.alertService.error('Error: No se puede guardar la venta sin un ID válido.');
      return;
    }
    
    // Asegurar que el ID sea un número
    ventaToSave.id = +ventaToSave.id;
    
    // Normalizar campos que pueden venir como cadena vacía a null
    if (ventaToSave.id_vendedor === '' || ventaToSave.id_vendedor === 'Todos' || ventaToSave.id_vendedor === null || ventaToSave.id_vendedor === undefined) {
      ventaToSave.id_vendedor = null;
    } else {
      // Convertir a número si tiene valor
      ventaToSave.id_vendedor = +ventaToSave.id_vendedor;
    }
    
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
      this.filtrarVentas(false);
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

  openModalAnulacion(template: TemplateRef<any>, venta: any) {
    this.venta = { ...venta }; // Crear copia para no modificar el original
    // Inicializar valores por defecto
    this.fechaAnulacion = this.apiService.date();
    this.tipoAnulacion = 2;
    this.motivoAnulacion = '';
    this.codigoGeneracionRemplazo = '';
    this.venta.errores = null; // Limpiar errores previos
    this.saving = false; // Asegurar que saving esté en false
    this.modalRef = this.modalService.show(template, {
      class: 'modal-md',
      backdrop: 'static'
    });
  }

  onTipoAnulacionChange() {
    // Limpiar el motivo cuando se cambia el tipo, excepto si es tipo 3
    if (this.tipoAnulacion != 3) {
      this.motivoAnulacion = '';
    }
    // Limpiar código de reemplazo si no es tipo 1 o 3
    if (this.tipoAnulacion != 1 && this.tipoAnulacion != 3) {
      this.codigoGeneracionRemplazo = '';
    }
  }

  anularDTE(venta: any) {
    this.venta = venta;
    if (venta.sello_mh && !venta.dte_invalidacion) {
      // Abrir modal para ingresar fecha y motivo
      this.openModalAnulacion(this.modalAnulacionTemplate, venta);
    }
    else {
      if (confirm('¿Confirma anular la venta?')) {
        this.venta.estado = 'Anulada';
        this.onSubmit();
      }
    }
  }

  confirmarAnulacion() {
    // Validar campos
    if (!this.fechaAnulacion) {
      this.alertService.error('Debe seleccionar una fecha de anulación.');
      return;
    }
    if (!this.tipoAnulacion) {
      this.alertService.error('Debe seleccionar un tipo de anulación.');
      return;
    }
    if (this.tipoAnulacion == 3 && !this.motivoAnulacion) {
      this.alertService.error('Debe ingresar el motivo de anulación.');
      return;
    }
    if ((this.tipoAnulacion == 1 || this.tipoAnulacion == 3) && !this.codigoGeneracionRemplazo) {
      this.alertService.error('Debe ingresar el código de generación de la venta que reemplaza a esta.');
      return;
    }

    // Si el tipo no es 3, usar el texto predeterminado según el tipo
    let motivoTexto = '';
    if (this.tipoAnulacion == 1) {
      motivoTexto = 'Error en la Información del Documento Tributario Electrónico a invalidar.';
    } else if (this.tipoAnulacion == 2) {
      motivoTexto = 'Se rescinde la operación.';
    } else {
      motivoTexto = this.motivoAnulacion;
    }

    // Asignar valores a la venta
    this.venta.fecha_anulacion = this.fechaAnulacion;
    this.venta.tipo_anulacion = this.tipoAnulacion;
    this.venta.motivo_anulacion = motivoTexto;
    this.venta.codigo_generacion_remplazo = (this.tipoAnulacion == 1 || this.tipoAnulacion == 3) ? this.codigoGeneracionRemplazo : null;
    this.venta.errores = null; // Limpiar errores previos

    this.saving = true;
    
    this.apiService.store('generarDTEAnulado', this.venta).subscribe(dte => {
      // this.alertService.success('DTE generado.');
      this.venta.dte_invalidacion = dte;
      this.mhService.firmarDTE(dte).subscribe(dteFirmado => {
        this.venta.dte_invalidacion.firmaElectronica = dteFirmado.body;

        if (dteFirmado.status == 'ERROR') {
          this.venta.errores = dteFirmado.body;
          this.saving = false;
          return;
        }

        this.mhService.anularDTE(this.venta, dteFirmado.body).subscribe(dte => {
          if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
            this.venta.dte_invalidacion.sello = dte.selloRecibido;
            this.venta.sello_mh = dte.selloRecibido;
            this.venta.estado = 'Anulada';
            
            // Cerrar el modal primero
            if (this.modalRef) {
              this.modalRef.hide();
            }
            
            // Limpiar el estado del modal
            this.saving = false;
            this.venta.errores = null;
            
            // Guardar la venta
            this.onSubmit();
            
            // Actualizar la venta en el listado
            const index = this.ventas.data.findIndex((v: any) => v.id === this.venta.id);
            if (index !== -1) {
              this.ventas.data[index] = { ...this.venta };
            }
            
            if (this.venta.id_cliente) {
              setTimeout(() => {
                this.enviarDTE(this.venta);
              }, 3000);
            }
            
            this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
          } else {
            this.venta.errores = dte;
            this.saving = false;
          }
        }, error => {
          this.saving = false;
          this.venta.errores = error;
        });

      }, error => { 
        this.saving = false;
        this.venta.errores = error;
      });

    }, error => { 
      this.saving = false;
      this.venta.errores = error;
    });
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

  public descargarCobrosPorVendedor() {
    this.downloadingCobrosVendedor = true;
    this.saving = true;
    this.apiService.export('cobros-por-vendedor/exportar', this.filtrosCobrosVendedor).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const fechaInicio = this.filtrosCobrosVendedor.inicio || 'sin-fecha';
        const fechaFin = this.filtrosCobrosVendedor.fin || 'sin-fecha';
        a.download = 'cobros-por-vendedor_' + fechaInicio + '_' + fechaFin + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Cerrar ambos modales
        if (this.modalRefCobrosVendedor) {
          this.modalRefCobrosVendedor.hide();
          this.modalRefCobrosVendedor = undefined;
        }
        if (this.modalRefDescargar) {
          this.modalRefDescargar.hide();
          this.modalRefDescargar = undefined;
        }

        this.downloadingCobrosVendedor = false;
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloadingCobrosVendedor = false;
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

  public getTotalConPropina(venta: any): number {
    const total = parseFloat(venta?.total || 0);
    const propina = parseFloat(venta?.propina || 0);
    return total + propina;
  }

  public getSaldo(venta: any): number {
    const total = parseFloat(venta?.total || 0);
    const abonos = parseFloat(venta?.abonos_sum_total || 0);
    const devoluciones = parseFloat(venta?.devoluciones_sum_total || 0);
    return Math.round((total - abonos - devoluciones) * 100) / 100;
  }

  public seleccionarReporte(reporte: string) {
    this.reporteSeleccionado = reporte;
    
    // Resetear todas las propiedades booleanas
    this.esReporteMarca = false;
    this.esReporteUtilidades = false;
    this.esReporteCobrosVendedor = false;
    
    // Establecer la propiedad booleana correspondiente
    if (reporte) {
      switch (reporte) {
        case 'marca':
          this.esReporteMarca = true;
          break;
        case 'utilidades':
          this.esReporteUtilidades = true;
          break;
        case 'cobros-vendedor':
          this.esReporteCobrosVendedor = true;
          break;
        case 'detalles':
        case 'ventas':
          this.resetExportPeriodo();
          break;
      }
    }
  }

}
