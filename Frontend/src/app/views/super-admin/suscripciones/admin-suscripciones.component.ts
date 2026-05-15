import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { formatDate } from '@angular/common';
import Swal from 'sweetalert2';
import { AppConstants } from '../../../../app/constants/app.constants';
import { Subject, Observable, of } from 'rxjs';
import {
  debounceTime,
  distinctUntilChanged,
  switchMap,
  catchError,
} from 'rxjs/operators';

interface Plan {
  id: number;
  nombre: string;
  monto: number;
}

interface OrdenPagoVentaResumen {
  id: number;
  correlativo: string;
  fecha: string;
  estado: string;
  total: string | number;
  forma_pago?: string;
  condicion?: string;
  num_cotizacion?: string | null;
  documento_nombre?: string | null;
}

interface OrdenPago {
  id?: number;
  id_orden: string;
  id_venta?: number | null;
  fecha_transaccion: string;
  monto: string;
  metodo_pago?: string;
  estado: string;
  codigo_autorizacion?: string;
  comprobante_url?: string;
  plan?: string;
  tipo_pago?: string;
  nombre_cliente?: string;
  email_cliente?: string;
  created_at?: string;
  venta?: OrdenPagoVentaResumen | null;
}

interface VentaBusquedaSuscripcion {
  id: number;
  correlativo: string;
  fecha: string;
  estado: string;
  total: string | number;
  forma_pago?: string;
  condicion?: string;
  num_cotizacion?: string | null;
  documento_nombre?: string | null;
  nombre_cliente?: string | null;
}

@Component({
  selector: 'app-admin-suscripciones',
  templateUrl: './admin-suscripciones.component.html',
  styles: [`
    .action-card {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      border: 1px solid rgba(0,0,0,0.05) !important;
    }
    .action-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 24px rgba(0,0,0,0.1) !important;
    }
    .action-card:hover .card-footer {
      opacity: 1;
    }
    .card-footer {
      transition: all 0.3s ease;
      opacity: 0.9;
    }
    .icon-circle {
      width: 64px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }
    .bg-primary-soft {
      background-color: rgba(13, 110, 253, 0.12);
    }
    .bg-success-soft {
      background-color: rgba(25, 135, 84, 0.12);
    }
  `]
})
export class AdminSuscripcionesComponent implements OnInit {
  public suscripciones: any = [];
  public suscripcion: any = {};
  public usuario: any = {};
  public empresa: any = {};
  public users: any[] = [];
  public filtros: any = {};
  public loading: boolean = false;
  public saving: boolean = false;
  public nuevaSuscripcion: any = {};
  public tabActivo: 'n1co' | 'transferencia' = 'n1co';
  public editando: boolean = false;
  public downloading: boolean = false;

  /** Evita doble clic en acciones rápidas desde la tabla o el modal */
  public accionPagoId: number | null = null;
  public accionAccesoId: number | null = null;
  public accionCancelarAccesoId: number | null = null;

  public historialPagos: OrdenPago[] = [];
  public loadingHistorial: boolean = false;

  // Para lazy loading de campañas
  public searchCampanias$ = new Subject<string>();
  public campaniasResults: string[] = [];
  public loadingCampanias: boolean = false;

  // Para lazy loading de clientes
  public searchClientes$ = new Subject<string>();
  public clientesResults: any[] = [];
  public loadingClientes: boolean = false;
  public asignandoCliente: boolean = false;
  /** True cuando ya hay cliente pero el usuario eligió cambiarlo por otro o crear uno nuevo. */
  public reemplazandoCliente: boolean = false;
  public editandoCliente: boolean = false;
  public clienteSeleccionado: any = null;

  // Datos para el formulario de cliente
  public paises: any = [];
  public departamentos: any = [];
  public distritos: any = [];
  public municipios: any = [];
  public actividad_economicas: any = [];
  public vendedores: any = [];
  public esNuevoCliente: boolean = false;
  public tipoAnterior: string = '';
  public diasCreditoOpciones = [3, 8, 10, 15, 30, 45, 60];

  /** Opciones DTE El Salvador (`suscripciones.tipo_factura`). */
  public readonly opcionesTipoFactura: { value: string; label: string }[] = [
    { value: '01', label: 'Consumidor final' },
    { value: '03', label: 'Crédito fiscal' },
  ];
  public guardandoTipoFactura = false;

  /** Valor inicial de frecuencia al abrir edición en modal facturación (para persistir solo si cambió). */
  private frecuenciaPagoFacturacionInicial = '';

  /** Valor inicial de tipo_factura al abrir edición en modal facturación (se persiste con «Guardar cambios»). */
  private tipoFacturaFacturacionInicial = '';

  // Para códigos promocionales
  public codigosPromocionales: any[] = [];
  public loadingCodigosPromocionales: boolean = false;

  public planes: Plan[] = [
    {
      id: AppConstants.PLANID.EMPRENDEDOR,
      nombre: AppConstants.PLANES.EMPRENDEDOR.NOMBRE,
      monto: AppConstants.PLANES.EMPRENDEDOR.PRECIO,
    },
    {
      id: AppConstants.PLANID.ESTANDAR,
      nombre: AppConstants.PLANES.ESTANDAR.NOMBRE,
      monto: AppConstants.PLANES.ESTANDAR.PRECIO,
    },
    {
      id: AppConstants.PLANID.AVANZADO,
      nombre: AppConstants.PLANES.AVANZADO.NOMBRE,
      monto: AppConstants.PLANES.AVANZADO.PRECIO,
    },
    {
      id: AppConstants.PLANID.PRO,
      nombre: AppConstants.PLANES.PRO.NOMBRE,
      monto: AppConstants.PLANES.PRO.PRECIO,
    },
  ];

  modalRef!: BsModalRef;

  /** Confirmación «Pago recibido» (modal propio, no SweetAlert). */
  modalRefPagoRecibido?: BsModalRef;
  suscripcionPagoPendiente: any = null;
  empresaNombrePagoModal = '';
  /** Fila empresa (listado) o empresa anidada en detalle — para `monto_mensual` al estimar factura. */
  empresaMontosPagoModal: any = null;
  ordenesPagoPendientesModal: OrdenPago[] = [];
  loadingOrdenesPendientesModal = false;
  ordenPagoSeleccionadoId: number | null = null;

  /** Cobertura del pago (próximo cobro = hoy + N meses en servidor). */
  pagoModalMesesCobertura = 1;
  readonly mesesCoberturaOpcionesPago: { meses: number; etiqueta: string }[] = [
    { meses: 1, etiqueta: '1 mes' },
    { meses: 2, etiqueta: '2 meses' },
    { meses: 3, etiqueta: '3 meses' },
    { meses: 6, etiqueta: '6 meses' },
    { meses: 12, etiqueta: '1 año' },
    { meses: 24, etiqueta: '2 años' },
  ];

  /** Cómo se concilia el cobro con el ERP. */
  pagoModalDocumentoOrigen: 'orden' | 'venta' | 'ninguno' = 'ninguno';

  ventasBusquedaPagoModal: VentaBusquedaSuscripcion[] = [];
  ventaBusquedaPagoTexto = '';
  ventaSeleccionadaPagoId: number | null = null;
  loadingVentasBusquedaPagoModal = false;

  /** Solo con origen «ninguno»: genera factura en ERP por plan × meses. */
  crearVentaManualPago = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) { }

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.loadAll();
    this.setupCampaniasSearch();
    this.setupClientesSearch();
    this.loadCodigosPromocionales();
    this.loadCatalogos();
  }

  public getMetodoPagoNombre(metodo: any): string {
    if (!metodo) return '-';
    if (metodo === '1' || metodo === 1 || metodo.toLowerCase() === 'n1co') return 'N1co';
    if (metodo === '2' || metodo === 2 || metodo.toLowerCase() === 'transferencia') return 'Transferencia';
    return metodo;
  }

  private loadCatalogos() {
    this.paises = JSON.parse(localStorage.getItem('paises') || '[]');
    this.departamentos = JSON.parse(localStorage.getItem('departamentos') || '[]');
    this.distritos = JSON.parse(localStorage.getItem('distritos') || '[]');
    this.municipios = JSON.parse(localStorage.getItem('municipios') || '[]');
    this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas') || '[]');

    this.apiService.getAll('usuarios/list').subscribe({
      next: (usuarios) => { this.vendedores = usuarios; },
      error: (error) => { console.error('Error cargando vendedores:', error); }
    });
  }

  private setupClientesSearch() {
    this.searchClientes$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((term) => {
          if (!term || term.length < 2) {
            return of([]);
          }
          this.loadingClientes = true;
          const u = this.apiService.auth_user();
          const idEmpresa =
            u?.id_empresa ?? u?.empresa?.id ?? u?.empresa?.id_empresa;
          if (!idEmpresa) {
            this.loadingClientes = false;
            return of([]);
          }
          return this.apiService.getAll('superadmin/clientes', {
            buscador: term,
            id_empresa: idEmpresa,
            paginate: 50,
          }).pipe(
            catchError((error) => {
              console.error('Error buscando clientes:', error);
              return of([]);
            })
          );
        })
      )
      .subscribe((results: any) => {
        const raw = results?.data || results || [];
        this.clientesResults = Array.isArray(raw)
          ? raw.map((c: any) => ({
              ...c,
              _labelBusqueda:
                (c.nombre_empresa && String(c.nombre_empresa).trim()) ||
                [c.nombre, c.apellido].filter(Boolean).join(' ').trim() ||
                'Sin nombre',
            }))
          : [];
        this.loadingClientes = false;
      });
  }

  private setupCampaniasSearch() {
    this.searchCampanias$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((term) => {
          if (!term || term.length < 1) {
            // Si no hay término, cargar las primeras campañas
            this.loadingCampanias = true;
            return this.apiService.getAll('suscripciones/campanias', {}).pipe(
              catchError((error) => {
                console.error('Error cargando campañas:', error);
                return of([]);
              })
            );
          }
          this.loadingCampanias = true;
          return this.apiService
            .getAll('suscripciones/campanias', { search: term })
            .pipe(
              catchError((error) => {
                console.error('Error buscando campañas:', error);
                return of([]);
              })
            );
        })
      )
      .subscribe((results) => {
        this.campaniasResults = results || [];
        this.loadingCampanias = false;
      });

    // Cargar campañas iniciales
    this.searchCampanias$.next('');
  }

  public loadAll() {
    this.filtros = {
      estado: '',
      buscador: '',
      orden: 'fecha_ultimo_pago',
      direccion: 'desc',
      paginate: 10,
      campania: '',
      fecha_pago_inicio: '',
      fecha_pago_fin: '',
    };

    this.filtrarSuscripciones();
  }

  public filtrarSuscripciones() {
    this.loading = true;

    this.apiService.getAll('suscripciones', this.filtros).subscribe(
      (suscripciones) => {
        this.suscripciones = suscripciones;
        this.loading = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.suscripciones.path + '?page=' + event.page, this.filtros)
      .subscribe(
        (suscripciones) => {
          this.suscripciones = suscripciones;
          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  public openDetalles(template: TemplateRef<any>, suscripcion: any) {
    this.suscripcion = suscripcion;
    this.modalRef = this.modalService.show(template);
  }

  public openEditar(template: TemplateRef<any>, suscripcion: any) {
    // Obtener empresa_id desde la suscripción
    const empresaId = suscripcion.empresa_id || suscripcion.empresa?.id;

    // Cargar la suscripción completa desde el backend con la relación empresa
    // para obtener el código promocional desde el modelo Suscripcion
    if (suscripcion.id) {
      this.apiService.getAll(`suscripcion/${suscripcion.id}`).subscribe(
        (suscripcionCompleta) => {
          // Obtener código promocional desde la relación empresa del modelo Suscripcion
          const codigoPromocional = suscripcionCompleta.empresa?.codigo_promocional || '';
          const frecuenciaPago = suscripcionCompleta.empresa?.frecuencia_pago || suscripcionCompleta.tipo_plan || '';
          const montoMensual = suscripcionCompleta.empresa?.monto_mensual || null;
          const montoAnual = suscripcionCompleta.empresa?.monto_anual || null;

          this.getUsersForSelect(empresaId)
            .then(() => {
              this.editando = true;
              this.suscripcion = {
                ...suscripcionCompleta,
                fecha_proximo_pago: this.formatearFecha(
                  suscripcionCompleta.fecha_proximo_pago
                ),
                fin_periodo_prueba: this.formatearFecha(
                  suscripcionCompleta.fin_periodo_prueba
                ),
                frecuencia_pago: frecuenciaPago,
                codigo_promocional: codigoPromocional,
                monto_mensual: montoMensual,
                monto_anual: montoAnual,
              };
              this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
            })
            .catch((error) => {
              this.alertService.error('No se pudo obtener usuarios');
            });
        },
        (error) => {
          // Si falla cargar la suscripción completa, usar los datos que ya tenemos
          this.getUsersForSelect(empresaId)
            .then(() => {
              this.editando = true;
              const codigoPromocional = suscripcion.empresa?.codigo_promocional || '';
              const frecuenciaPago = suscripcion.empresa?.frecuencia_pago || suscripcion.tipo_plan || '';
              const montoMensual = suscripcion.empresa?.monto_mensual || null;
              const montoAnual = suscripcion.empresa?.monto_anual || null;

              this.suscripcion = {
                ...suscripcion,
                fecha_proximo_pago: this.formatearFecha(
                  suscripcion.fecha_proximo_pago
                ),
                fin_periodo_prueba: this.formatearFecha(
                  suscripcion.fin_periodo_prueba
                ),
                frecuencia_pago: frecuenciaPago,
                codigo_promocional: codigoPromocional,
                monto_mensual: montoMensual,
                monto_anual: montoAnual,
              };
              this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
            })
            .catch((error) => {
              this.alertService.error('No se pudo obtener usuarios');
            });
        }
      );
    } else {
      // Si no hay ID, usar los datos que ya tenemos
      this.getUsersForSelect(empresaId)
        .then(() => {
          this.editando = true;
          const codigoPromocional = suscripcion.empresa?.codigo_promocional || '';
          const frecuenciaPago = suscripcion.empresa?.frecuencia_pago || suscripcion.tipo_plan || '';
          const montoMensual = suscripcion.empresa?.monto_mensual || null;
          const montoAnual = suscripcion.empresa?.monto_anual || null;

          this.suscripcion = {
            ...suscripcion,
            fecha_proximo_pago: this.formatearFecha(
              suscripcion.fecha_proximo_pago
            ),
            fin_periodo_prueba: this.formatearFecha(
              suscripcion.fin_periodo_prueba
            ),
            frecuencia_pago: frecuenciaPago,
            codigo_promocional: codigoPromocional,
            monto_mensual: montoMensual,
            monto_anual: montoAnual,
          };
          this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
        })
        .catch((error) => {
          this.alertService.error('No se pudo obtener usuarios');
        });
    }
  }

  private formatearFecha(fecha: string): string {
    if (!fecha) return '';
    return formatDate(new Date(fecha), 'yyyy-MM-dd', 'en');
  }

  public openCancelar(template: TemplateRef<any>, suscripcion: any) {
    this.suscripcion = suscripcion;
    this.modalRef = this.modalService.show(template);
  }

  public cancelarSuscripcion() {
    if (!this.suscripcion?.id || !this.suscripcion?.motivo_cancelacion) {
      return;
    }

    this.saving = true;
    this.apiService
      .store('suscripcion/cancel', {
        id: this.suscripcion.id,
        motivo_cancelacion: this.suscripcion.motivo_cancelacion,
      })
      .subscribe(
        (response) => {
          this.saving = false;
          this.alertService.success(
            'Éxito',
            'Suscripción cancelada exitosamente'
          );
          this.modalRef.hide();
          this.filtrarSuscripciones();
        },
        (error) => {
          this.alertService.error(error);
          this.saving = false;
        }
      );
  }

  public onSubmitEditSuscription() {
    if (!this.suscripcion.id) {
      this.alertService.error('ID de suscripción no válido');
      return;
    }

    this.saving = true;

    // Sincronizar tipo_plan con frecuencia_pago si no están sincronizados
    if (this.suscripcion.frecuencia_pago && !this.suscripcion.tipo_plan) {
      this.suscripcion.tipo_plan = this.suscripcion.frecuencia_pago;
    } else if (this.suscripcion.tipo_plan && !this.suscripcion.frecuencia_pago) {
      this.suscripcion.frecuencia_pago = this.suscripcion.tipo_plan;
    }

    const datosSuscripcion: any = {
      ...this.suscripcion,
      usuario_id: this.suscripcion.usuario_id,
      fin_periodo_prueba: new Date(this.suscripcion.fin_periodo_prueba),
      frecuencia_pago: this.suscripcion.frecuencia_pago || this.suscripcion.tipo_plan,
      codigo_promocional: this.suscripcion.codigo_promocional || null,
      monto_mensual: this.suscripcion.monto_mensual || null,
      monto_anual: this.suscripcion.monto_anual || null,
    };
    delete datosSuscripcion.fecha_proximo_pago;

    this.apiService.store('suscripcion/edit', datosSuscripcion).subscribe(
      (response) => {
        this.alertService.success(
          'Éxito',
          'Suscripción actualizada correctamente'
        );
        this.modalRef.hide();
        this.filtrarSuscripciones();
        this.saving = false;
      },
      (error) => {
        this.saving = false;
        this.alertService.error(
          error.message || 'Error actualizando suscripción'
        );
      }
    );
  }

  public pagoRecibido(
    template: TemplateRef<any>,
    suscripcion: any,
    nombreEmpresa?: string,
    empresaMontos?: any
  ): void {
    if (!suscripcion?.id) {
      return;
    }
    this.suscripcionPagoPendiente = suscripcion;
    this.empresaMontosPagoModal =
      empresaMontos ?? suscripcion?.empresa ?? null;
    this.empresaNombrePagoModal =
      nombreEmpresa ||
      suscripcion?.empresa?.nombre ||
      empresaMontos?.nombre ||
      '';
    this.ordenesPagoPendientesModal = [];
    this.ordenPagoSeleccionadoId = null;
    this.pagoModalMesesCobertura = 1;
    this.pagoModalDocumentoOrigen = 'ninguno';
    this.ventasBusquedaPagoModal = [];
    this.ventaBusquedaPagoTexto = '';
    this.ventaSeleccionadaPagoId = null;
    this.crearVentaManualPago = false;
    this.cargarOrdenesPagoPendientesModal(suscripcion.id);

    this.modalRefPagoRecibido = this.modalService.show(template, {
      class: 'modal-dialog-centered modal-lg',
      ignoreBackdropClick: true,
    });
  }

  private cargarOrdenesPagoPendientesModal(suscripcionId: number): void {
    this.loadingOrdenesPendientesModal = true;
    this.apiService
      .getAll(`suscripciones/${suscripcionId}/ordenes-pago-pendientes`)
      .subscribe({
        next: (rows: OrdenPago[]) => {
          this.ordenesPagoPendientesModal = Array.isArray(rows) ? rows : [];
          if (this.ordenesPagoPendientesModal.length > 0) {
            this.pagoModalDocumentoOrigen = 'orden';
            if (this.ordenesPagoPendientesModal.length === 1) {
              const only = this.ordenesPagoPendientesModal[0];
              if (only?.id != null) {
                this.ordenPagoSeleccionadoId = only.id;
              }
            }
          } else {
            this.pagoModalDocumentoOrigen = 'ninguno';
          }
          this.loadingOrdenesPendientesModal = false;
        },
        error: () => {
          this.ordenesPagoPendientesModal = [];
          this.loadingOrdenesPendientesModal = false;
          this.alertService.error(
            'No se pudieron cargar las órdenes pendientes. Puedes registrar el pago igualmente si no aplica ninguna.'
          );
        },
      });
  }

  public cerrarModalPagoRecibido(): void {
    this.modalRefPagoRecibido?.hide();
    this.modalRefPagoRecibido = undefined;
    this.suscripcionPagoPendiente = null;
    this.empresaNombrePagoModal = '';
    this.empresaMontosPagoModal = null;
    this.ordenesPagoPendientesModal = [];
    this.ordenPagoSeleccionadoId = null;
    this.loadingOrdenesPendientesModal = false;
    this.pagoModalMesesCobertura = 1;
    this.pagoModalDocumentoOrigen = 'ninguno';
    this.ventasBusquedaPagoModal = [];
    this.ventaBusquedaPagoTexto = '';
    this.ventaSeleccionadaPagoId = null;
    this.crearVentaManualPago = false;
    this.loadingVentasBusquedaPagoModal = false;
  }

  public setDocumentoOrigenPago(
    modo: 'orden' | 'venta' | 'ninguno'
  ): void {
    this.pagoModalDocumentoOrigen = modo;
    if (modo !== 'orden') {
      this.ordenPagoSeleccionadoId = null;
    }
    if (modo !== 'venta') {
      this.ventaSeleccionadaPagoId = null;
    }
    if (modo !== 'ninguno') {
      this.crearVentaManualPago = false;
    }
    if (modo === 'orden' && this.ordenesPagoPendientesModal.length === 1) {
      const only = this.ordenesPagoPendientesModal[0];
      if (only?.id != null) {
        this.ordenPagoSeleccionadoId = only.id;
      }
    }
    if (modo === 'venta') {
      this.buscarVentasParaPagoModal();
    }
  }

  public fechaProximoPagoPreview(): Date {
    const d = new Date();
    d.setMonth(d.getMonth() + (this.pagoModalMesesCobertura || 1));
    return d;
  }

  public montoEstimadoCrearVentaModal(): number | null {
    const s = this.suscripcionPagoPendiente;
    if (!s) {
      return null;
    }
    const emp = this.empresaMontosPagoModal;
    const mensual = Number(
      emp?.monto_mensual ??
      s?.empresa?.monto_mensual ??
      s?.plan?.precio ??
      s?.monto ??
      0
    );
    if (!mensual || mensual <= 0) {
      return null;
    }
    return Math.round(mensual * (this.pagoModalMesesCobertura || 1) * 100) / 100;
  }

  public buscarVentasParaPagoModal(): void {
    const suscripcion = this.suscripcionPagoPendiente;
    if (!suscripcion?.id) {
      return;
    }
    this.loadingVentasBusquedaPagoModal = true;
    this.ventaSeleccionadaPagoId = null;
    const q = (this.ventaBusquedaPagoTexto || '').trim();
    this.apiService
      .getAll(`suscripciones/${suscripcion.id}/ventas-buscar`, {
        buscar: q,
      })
      .subscribe({
        next: (rows: VentaBusquedaSuscripcion[]) => {
          this.ventasBusquedaPagoModal = Array.isArray(rows) ? rows : [];
          this.loadingVentasBusquedaPagoModal = false;
        },
        error: () => {
          this.ventasBusquedaPagoModal = [];
          this.loadingVentasBusquedaPagoModal = false;
          this.alertService.error(
            'No se pudieron buscar ventas. Verifica que la empresa tenga cliente ERP vinculado.'
          );
        },
      });
  }

  public ejecutarPagoRecibido(): void {
    const suscripcion = this.suscripcionPagoPendiente;
    if (!suscripcion?.id) {
      this.cerrarModalPagoRecibido();
      return;
    }

    if (this.pagoModalDocumentoOrigen === 'orden') {
      if (
        this.ordenesPagoPendientesModal.length > 0 &&
        this.ordenPagoSeleccionadoId == null
      ) {
        this.alertService.error(
          'Selecciona la orden pendiente que quedó pagada con este abono.'
        );
        return;
      }
      if (this.ordenesPagoPendientesModal.length === 0) {
        this.alertService.error(
          'No hay órdenes pendientes; elige «Buscar venta» o «Solo suscripción».'
        );
        return;
      }
    }

    if (
      this.pagoModalDocumentoOrigen === 'venta' &&
      this.ventaSeleccionadaPagoId == null
    ) {
      this.alertService.error(
        'Busca y selecciona la venta del ERP que quedó pagada (p. ej. transferencia).'
      );
      return;
    }

    if (
      this.crearVentaManualPago &&
      this.pagoModalDocumentoOrigen === 'ninguno' &&
      this.montoEstimadoCrearVentaModal() == null
    ) {
      this.alertService.error(
        'No hay monto mensual/plan para generar la factura. Completa montos en la suscripción o desmarca «Generar factura».'
      );
      return;
    }

    this.accionPagoId = suscripcion.id;
    const payload: {
      id: number;
      meses_cobertura: number;
      documento_origen: 'orden' | 'venta' | 'ninguno';
      orden_pago_id?: number;
      venta_id?: number;
      crear_venta: boolean;
    } = {
      id: suscripcion.id,
      meses_cobertura: this.pagoModalMesesCobertura || 1,
      documento_origen: this.pagoModalDocumentoOrigen,
      crear_venta:
        this.crearVentaManualPago &&
        this.pagoModalDocumentoOrigen === 'ninguno',
    };
    if (
      this.pagoModalDocumentoOrigen === 'orden' &&
      this.ordenPagoSeleccionadoId != null
    ) {
      payload.orden_pago_id = this.ordenPagoSeleccionadoId;
    }
    if (
      this.pagoModalDocumentoOrigen === 'venta' &&
      this.ventaSeleccionadaPagoId != null
    ) {
      payload.venta_id = this.ventaSeleccionadaPagoId;
    }

    this.apiService.store('suscripcion/pago-recibido', payload).subscribe({
      next: () => {
        this.accionPagoId = null;
        this.cerrarModalPagoRecibido();
        this.alertService.success(
          'Listo',
          'Pago registrado y próxima fecha de pago actualizada.'
        );
        this.filtrarSuscripciones();
        if (this.modalRef && this.suscripcion?.id === suscripcion.id) {
          this.apiService
            .getAll(`suscripcion/${suscripcion.id}`)
            .subscribe((s) => {
              this.suscripcion = s;
            });
        }
      },
      error: (err) => {
        this.accionPagoId = null;
        this.alertService.error(err);
      },
    });
  }

  /** True si existe fecha de acceso temporal aún no vencida (para alertas al extender). */
  public tieneAccesoTemporalVigente(suscripcion: any): boolean {
    if (!suscripcion?.acceso_temporal_hasta) {
      return false;
    }
    return new Date(suscripcion.acceso_temporal_hasta).getTime() > Date.now();
  }

  public concederAccesoTemporal(suscripcion: any): void {
    if (!suscripcion?.id) {
      return;
    }
    const dias = AppConstants.DIAS_ACCESO_TEMPORAL_ADMIN;
    const yaVigente = this.tieneAccesoTemporalVigente(suscripcion);
    const finActual = suscripcion.acceso_temporal_hasta
      ? new Date(suscripcion.acceso_temporal_hasta).toLocaleString('es-SV', {
        dateStyle: 'short',
        timeStyle: 'short',
      })
      : '';

    const htmlBase = yaVigente
      ? `<p class="mb-2"><strong>Esta suscripción ya tiene acceso temporal vigente</strong> hasta el <strong>${finActual}</strong>.</p>
         <p class="mb-0">Si continúas, se sumarán <strong>${dias} días adicionales</strong> a partir de esa fecha (no desde hoy), <strong>sin cambiar</strong> la fecha de próximo pago.</p>`
      : `<p class="mb-0">Se amplía el acceso a la plataforma <strong>${dias} días</strong> (desde hoy o desde el fin del acceso temporal vigente) <strong>sin cambiar</strong> la fecha de próximo pago.</p>`;

    Swal.fire({
      title: yaVigente ? '¿Extender acceso temporal?' : '¿Conceder acceso temporal?',
      html: htmlBase,
      icon: yaVigente ? 'warning' : 'question',
      showCancelButton: true,
      confirmButtonText: yaVigente ? 'Sí, extender' : 'Sí, conceder',
      cancelButtonText: 'Cancelar',
    }).then((r) => {
      if (!r.isConfirmed) {
        return;
      }
      this.accionAccesoId = suscripcion.id;
      this.apiService.store('suscripcion/acceso-temporal', { id: suscripcion.id }).subscribe({
        next: () => {
          this.accionAccesoId = null;
          this.alertService.success(
            'Listo',
            yaVigente
              ? 'Acceso temporal extendido.'
              : 'Acceso temporal concedido.'
          );
          this.filtrarSuscripciones();
          if (this.modalRef && this.suscripcion?.id === suscripcion.id) {
            this.apiService
              .getAll(`suscripcion/${suscripcion.id}`)
              .subscribe((s) => {
                this.suscripcion = s;
              });
          }
        },
        error: (err) => {
          this.accionAccesoId = null;
          this.alertService.error(err);
        },
      });
    });
  }

  public cancelarAccesoTemporal(suscripcion: any): void {
    if (!suscripcion?.id) {
      return;
    }
    if (!suscripcion.acceso_temporal_hasta) {
      this.alertService.error('No hay acceso temporal configurado para quitar.');
      return;
    }
    Swal.fire({
      title: '¿Cancelar acceso temporal?',
      html: 'El cliente volverá a depender solo de la fecha de próximo pago y la mora habitual (puede aplicarse el paywall si corresponde).',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cancelar acceso temporal',
      cancelButtonText: 'No',
      confirmButtonColor: '#d33',
    }).then((r) => {
      if (!r.isConfirmed) {
        return;
      }
      this.accionCancelarAccesoId = suscripcion.id;
      this.apiService
        .store('suscripcion/cancelar-acceso-temporal', { id: suscripcion.id })
        .subscribe({
          next: () => {
            this.accionCancelarAccesoId = null;
            this.alertService.success('Listo', 'Acceso temporal cancelado.');
            this.filtrarSuscripciones();
            if (this.modalRef && this.suscripcion?.id === suscripcion.id) {
              this.apiService
                .getAll(`suscripcion/${suscripcion.id}`)
                .subscribe((s) => {
                  this.suscripcion = s;
                });
            }
          },
          error: (err) => {
            this.accionCancelarAccesoId = null;
            this.alertService.error(err);
          },
        });
    });
  }

  private getUsersForSelect(id_empresa: number) {
    return new Promise<any[]>((resolve, reject) => {
      const params = {
        id_empresa: id_empresa,
      };

      this.apiService
        .store('suscripcion/getUsersSelect', { params })
        .subscribe({
          next: (response: any) => {
            this.users = response;
            resolve(response);
          },
          error: (error) => {
            this.alertService.error('Error al cargar usuarios: ' + error);
            reject(error);
          },
        });
    });
  }

  public openCrearSuscripcion(template: TemplateRef<any>, empresa: any) {
    if (!empresa) {
      this.alertService.error('No se proporcionaron datos de la empresa');
      return;
    }

    this.getUsersForSelect(empresa.id)
      .then(() => {
        (this.editando = false),
          (this.nuevaSuscripcion = {
            empresa_id: empresa.id,
            usuario_id: '',
            plan_id: '',
            nombre_factura: empresa.nombre,
            direccion_factura: empresa.direccion,
            nit: empresa.nit,
            tipo_plan: empresa.tipo_plan || empresa.frecuencia_pago || '',
            frecuencia_pago: empresa.frecuencia_pago || empresa.tipo_plan || '',
            codigo_promocional: empresa.codigo_promocional || '',
            monto_mensual: empresa.monto_mensual || null,
            monto_anual: empresa.monto_anual || null,
            estado: 'En prueba',
            monto: 0,
            fecha_proximo_pago: this.formatearFecha(new Date().toISOString()),
            fin_periodo_prueba: this.formatearFecha(
              new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString()
            ),
            requiere_factura: Boolean(empresa.nit),
          });

        this.modalRef = this.modalService.show(template);
      })
      .catch((error) => {
        this.alertService.error('No se pudo obtener usuarios');
      });
  }

  public selectPlan(planId: number) {
    const planSeleccionado = this.planes.find(
      (plan) => plan.id === Number(planId)
    );
    if (planSeleccionado) {
      this.nuevaSuscripcion.plan_id = planSeleccionado.id;
      this.nuevaSuscripcion.monto = planSeleccionado.monto;
      // Calcular montos si hay frecuencia de pago establecida
      if (this.nuevaSuscripcion.frecuencia_pago || this.nuevaSuscripcion.tipo_plan) {
        const frecuencia = this.nuevaSuscripcion.frecuencia_pago || this.nuevaSuscripcion.tipo_plan;
        this.calcularMontos(planSeleccionado.monto, frecuencia, false);
      }
    }
  }

  public selectPlanEdit(planId: number) {
    const planSeleccionado = this.planes.find(
      (plan) => plan.id === Number(planId)
    );
    if (planSeleccionado) {
      if (!this.suscripcion.plan) {
        this.suscripcion.plan = {};
      }
      this.suscripcion.plan.id = planSeleccionado.id;
      this.suscripcion.monto = planSeleccionado.monto;
      // Calcular montos si hay frecuencia de pago establecida
      if (this.suscripcion.frecuencia_pago || this.suscripcion.tipo_plan) {
        const frecuencia = this.suscripcion.frecuencia_pago || this.suscripcion.tipo_plan;
        this.calcularMontos(planSeleccionado.monto, frecuencia, true);
      }
    }
  }

  public onFrecuenciaPagoChange(frecuencia: string, isEdit: boolean = false) {
    if (isEdit) {
      // Sincronizar tipo_plan con frecuencia_pago en edición
      if (frecuencia) {
        this.suscripcion.tipo_plan = frecuencia;
      }
      // Recalcular montos si hay un monto establecido
      if (this.suscripcion.monto) {
        this.calcularMontos(this.suscripcion.monto, frecuencia, true);
      }
    } else {
      // Sincronizar tipo_plan con frecuencia_pago en creación
      if (frecuencia) {
        this.nuevaSuscripcion.tipo_plan = frecuencia;
      }
      // Recalcular montos si hay un monto establecido
      if (this.nuevaSuscripcion.monto) {
        this.calcularMontos(this.nuevaSuscripcion.monto, frecuencia, false);
      }
    }
  }

  /** Frecuencia en modal facturación (empresa), alineada con suscripción al guardar cliente. */
  public onFrecuenciaPagoFacturacionModalChange(frecuencia: string): void {
    if (!this.empresa || typeof this.empresa !== 'object') {
      this.empresa = {};
    }
    this.empresa.frecuencia_pago = frecuencia;
    if (frecuencia) {
      this.suscripcion.tipo_plan = frecuencia;
      this.empresa.tipo_plan = frecuencia;
    }
    if (this.suscripcion?.monto && frecuencia) {
      this.calcularMontos(this.suscripcion.monto, frecuencia, true);
    }
  }

  public onMontoChange(monto: number, isEdit: boolean = false) {
    if (!monto || monto <= 0) {
      return;
    }

    const frecuencia = isEdit
      ? this.suscripcion.frecuencia_pago || this.suscripcion.tipo_plan
      : this.nuevaSuscripcion.frecuencia_pago || this.nuevaSuscripcion.tipo_plan;

    if (frecuencia) {
      this.calcularMontos(monto, frecuencia, isEdit);
    }
  }

  private calcularMontos(monto: number, frecuenciaPago: string, isEdit: boolean) {
    if (!monto || monto <= 0 || !frecuenciaPago) {
      return;
    }

    let montoMensual: number = 0;
    let montoAnual: number = 0;

    switch (frecuenciaPago.toLowerCase()) {
      case 'mensual':
        // Si el monto es mensual: monto_mensual = monto, monto_anual = monto * 12
        montoMensual = monto;
        montoAnual = monto * 12;
        break;

      case 'anual':
        // Si el monto es anual (con descuento del 20%): 
        // monto_anual = monto, monto_mensual = (monto / 12) / 0.80
        montoAnual = monto;
        montoMensual = (monto / 12) / 0.80;
        break;

      case 'trimestral':
        // Si el monto es trimestral: monto_mensual = monto / 3, monto_anual = monto_mensual * 12
        montoMensual = monto / 3;
        montoAnual = montoMensual * 12;
        break;

      default:
        return;
    }

    // Redondear a 2 decimales
    montoMensual = Math.round(montoMensual * 100) / 100;
    montoAnual = Math.round(montoAnual * 100) / 100;

    // Asignar los valores calculados
    if (isEdit) {
      this.suscripcion.monto_mensual = montoMensual;
      this.suscripcion.monto_anual = montoAnual;
    } else {
      this.nuevaSuscripcion.monto_mensual = montoMensual;
      this.nuevaSuscripcion.monto_anual = montoAnual;
    }
  }

  public loadCodigosPromocionales() {
    this.loadingCodigosPromocionales = true;
    this.apiService.getAll('promocionales', {}).subscribe(
      (codigos) => {
        this.codigosPromocionales = codigos || [];
        this.loadingCodigosPromocionales = false;
      },
      (error) => {
        console.error('Error cargando códigos promocionales:', error);
        this.codigosPromocionales = [];
        this.loadingCodigosPromocionales = false;
      }
    );
  }

  public getCodigoPromocionalDisplay(codigo: any): string {
    if (!codigo) return '';
    let display = codigo.codigo || '';
    if (codigo.tipo === 'porcentaje' && codigo.descuento) {
      display += ` (${codigo.descuento}%)`;
    } else if (codigo.tipo === 'monto_fijo' && codigo.descuento) {
      display += ` ($${codigo.descuento})`;
    }
    return display;
  }

  public getDescuentoCodigoPromocional(codigoSeleccionado: string, isEdit: boolean = false): string {
    if (!codigoSeleccionado) return '';

    const codigo = this.codigosPromocionales.find(
      (c) => c.codigo === codigoSeleccionado
    );

    if (!codigo) return '';

    if (codigo.tipo === 'porcentaje') {
      return `${codigo.descuento}% de descuento`;
    } else if (codigo.tipo === 'monto_fijo') {
      return `$${codigo.descuento} de descuento`;
    }

    return '';
  }

  public onSubmitCreateSuscription() {
    if (!this.nuevaSuscripcion.empresa_id) {
      this.alertService.error('ID de empresa no válido');
      return;
    }

    this.saving = true;

    // Sincronizar tipo_plan con frecuencia_pago si no están sincronizados
    if (this.nuevaSuscripcion.frecuencia_pago && !this.nuevaSuscripcion.tipo_plan) {
      this.nuevaSuscripcion.tipo_plan = this.nuevaSuscripcion.frecuencia_pago;
    } else if (this.nuevaSuscripcion.tipo_plan && !this.nuevaSuscripcion.frecuencia_pago) {
      this.nuevaSuscripcion.frecuencia_pago = this.nuevaSuscripcion.tipo_plan;
    }

    const datosSuscripcion = {
      ...this.nuevaSuscripcion,
      fecha_proximo_pago: new Date(this.nuevaSuscripcion.fecha_proximo_pago),
      fin_periodo_prueba: new Date(this.nuevaSuscripcion.fin_periodo_prueba),
      frecuencia_pago: this.nuevaSuscripcion.frecuencia_pago || this.nuevaSuscripcion.tipo_plan,
      codigo_promocional: this.nuevaSuscripcion.codigo_promocional || null,
      monto_mensual: this.nuevaSuscripcion.monto_mensual || null,
      monto_anual: this.nuevaSuscripcion.monto_anual || null,
      nit: this.nuevaSuscripcion.nit || null,
      nombre_factura: this.nuevaSuscripcion.nombre_factura || null,
      direccion_factura: this.nuevaSuscripcion.direccion_factura || null,
      requiere_factura: this.nuevaSuscripcion.nit ? 1 : 0,
      giro: this.nuevaSuscripcion.giro || null,
      telefono: this.nuevaSuscripcion.telefono || null,
      correo: this.nuevaSuscripcion.correo || null,
    };

    this.apiService.store('suscripcion/create', datosSuscripcion).subscribe({
      next: (response) => {
        this.saving = false;
        this.alertService.success('Éxito', 'Suscripción creada correctamente');
        this.modalRef.hide();
        this.filtrarSuscripciones();
      },
      error: (error) => {
        this.saving = false;
        this.alertService.error(error.message || 'Error creando suscripción');
      },
    });
  }

  public openFilter(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template);
  }

  public suspenderSistema(empresa: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Desea suspender el acceso al sistema ?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, suspender',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.store('suscripcion/suspender', { empresa }).subscribe({
          next: () => {
            Swal.fire(
              '¡Suspendido!',
              'El acceso al sistema ha sido suspendido',
              'success'
            );
            this.filtrarSuscripciones();
          },
          error: (error) => {
            Swal.fire('No se pudo suspender el sistema.', 'error');
            this.filtrarSuscripciones();
          },
        });
      }
    });
  }

  public activarSistema(empresa: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Desea activar el acceso al sistema?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, activar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.store('suscripcion/activar', { empresa }).subscribe({
          next: () => {
            Swal.fire(
              '¡Activado!',
              'El acceso al sistema ha sido activado',
              'success'
            );
            this.filtrarSuscripciones();
          },
          error: (error) => {
            Swal.fire('No se pudo activar el sistema.', 'error');
            this.filtrarSuscripciones();
          },
        });
      }
    });
  }

  public openHistorialPagos(
    template: TemplateRef<any>,
    suscripcion: any,
    empresa: any
  ) {
    this.loadingHistorial = true;
    this.suscripcion = suscripcion;
    this.suscripcion.empresa = empresa;
    this.tabActivo = 'n1co'; // Establecer tab por defecto

    this.apiService.getAll(`suscripciones/${suscripcion.id}/pagos`).subscribe({
      next: (response) => {
        console.log('Historial de pagos:', response); // Para debug
        this.historialPagos = response;
        this.loadingHistorial = false;
      },
      error: (error) => {
        console.error('Error cargando historial:', error); // Para debug
        this.alertService.error('Error al cargar el historial de pagos');
        this.loadingHistorial = false;
      },
    });

    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  public openFacturacion(template: TemplateRef<any>, suscripcion: any, empresa: any) {
    this.suscripcion = { ...suscripcion };
    this.empresa = empresa || {};
    this.frecuenciaPagoFacturacionInicial = '';
    this.reemplazandoCliente = false;
    this.asignandoCliente = false;
    this.editandoCliente = false;
    this.clienteSeleccionado = null;

    if (suscripcion?.id) {
      this.apiService.getAll(`suscripcion/${suscripcion.id}`).subscribe({
        next: (s) => {
          this.suscripcion = { ...this.suscripcion, ...s };
          if (s.empresa) {
            this.empresa = { ...(this.empresa || {}), ...s.empresa };
            if (!this.empresa.frecuencia_pago && this.suscripcion.tipo_plan) {
              this.empresa.frecuencia_pago = this.suscripcion.tipo_plan;
            }
          }
        },
        error: () => {},
      });
    }

    if (this.suscripcion.id_cliente) {
      this.loading = true;
      this.apiService.read('superadmin/cliente/', this.suscripcion.id_cliente).subscribe({
        next: (cliente) => {
          this.clienteSeleccionado = cliente;
          this.tipoAnterior = cliente.tipo;
          this.loading = false;
        },
        error: (err) => {
          this.alertService.error(err);
          this.loading = false;
        }
      });
    }

    this.modalRef = this.modalService.show(template, { class: 'modal-xl' });
  }

  public etiquetaTipoFactura(val: string | null | undefined): string {
    if (val == null || val === '') {
      return 'No definido';
    }
    const o = this.opcionesTipoFactura.find((x) => x.value === val);
    return o ? o.label : String(val);
  }

  public guardarTipoFactura() {
    if (!this.suscripcion?.id) {
      return;
    }
    this.guardandoTipoFactura = true;
    this.apiService
      .store('suscripcion/tipo-factura', {
        suscripcion_id: this.suscripcion.id,
        tipo_factura: this.suscripcion.tipo_factura ?? null,
      })
      .subscribe({
        next: (res: any) => {
          this.guardandoTipoFactura = false;
          if (res?.data) {
            this.suscripcion = { ...this.suscripcion, ...res.data };
          }
          this.alertService.success('Éxito', 'Tipo de factura guardado');
          this.filtrarSuscripciones();
        },
        error: (err) => {
          this.guardandoTipoFactura = false;
          this.alertService.error(err);
        },
      });
  }

  public crearNuevoCliente(suscripcion: any) {
    this.esNuevoCliente = true;
    this.editandoCliente = true;
    const empresa = this.empresa || suscripcion.empresa;
    this.clienteSeleccionado = {
      nombre: empresa?.nombre || '',
      nombre_empresa: empresa?.nombre || '',
      tipo: 'Empresa',
      id_empresa: empresa?.id || suscripcion.empresa_id,
      id_usuario: this.usuario.id,
      enable: true,
      cod_pais: 'SLV',
      pais: 'El Salvador',
      habilita_credito: false,
      correo: empresa?.correo || '',
      telefono: empresa?.telefono || '',
      empresa_telefono: empresa?.telefono || '',
      nit: empresa?.nit || '',
      direccion: empresa?.direccion || '',
      empresa_direccion: empresa?.direccion || '',
      cod_departamento: '',
      cod_municipio: '',
      cod_distrito: '',
      id_vendedor: null,
      clasificacion: 'B'
    };
    this.ensureEmpresaFacturacionModal();
    this.frecuenciaPagoFacturacionInicial =
      this.empresa.frecuencia_pago == null || this.empresa.frecuencia_pago === ''
        ? ''
        : String(this.empresa.frecuencia_pago);
    this.tipoFacturaFacturacionInicial = this.normalizarTipoFacturaModal(
      this.suscripcion?.tipo_factura
    );
  }

  public asignarClienteExistente(suscripcion: any) {
    this.asignandoCliente = true;
    this.clienteSeleccionado = null;
    this.clientesResults = [];
    this.searchClientes$.next('');
  }

  public confirmarAsignacionCliente() {
    if (!this.clienteSeleccionado || !this.suscripcion?.id) {
      this.alertService.error('Debe seleccionar un cliente');
      return;
    }

    this.saving = true;
    this.apiService.store('suscripcion/asignar-cliente', {
      suscripcion_id: this.suscripcion.id,
      cliente_id: this.clienteSeleccionado.id,
      tipo_factura: this.suscripcion.tipo_factura ?? null,
    }).subscribe({
      next: () => {
        this.saving = false;
        this.asignandoCliente = false;
        this.reemplazandoCliente = false;
        this.alertService.success('Éxito', 'Cliente asignado correctamente');
        // Recargar datos para mostrar información de facturación
        this.apiService.read('superadmin/cliente/', this.clienteSeleccionado.id).subscribe(c => {
          this.clienteSeleccionado = c;
          this.tipoAnterior = c.tipo;
        });

        this.apiService.getAll(`suscripcion/${this.suscripcion.id}`).subscribe(s => {
          this.suscripcion = s;
        });
        this.filtrarSuscripciones();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      }
    });
  }

  // Métodos para edición de cliente (Copiados de ClienteInformacionComponent)
  public iniciarEdicionCliente() {
    this.ensureEmpresaFacturacionModal();
    this.frecuenciaPagoFacturacionInicial =
      this.empresa.frecuencia_pago == null || this.empresa.frecuencia_pago === ''
        ? ''
        : String(this.empresa.frecuencia_pago);
    this.tipoFacturaFacturacionInicial = this.normalizarTipoFacturaModal(
      this.suscripcion?.tipo_factura
    );
    this.editandoCliente = true;
    this.esNuevoCliente = false;
  }

  public cancelarEdicionCliente() {
    this.editandoCliente = false;
    if (this.esNuevoCliente) {
      this.esNuevoCliente = false;
    }
    if (this.suscripcion?.id) {
      this.apiService.getAll(`suscripcion/${this.suscripcion.id}`).subscribe({
        next: (s) => {
          this.suscripcion = { ...this.suscripcion, ...s };
          if (s.empresa) {
            this.empresa = { ...(this.empresa || {}), ...s.empresa };
            if (!this.empresa.frecuencia_pago && this.suscripcion.tipo_plan) {
              this.empresa.frecuencia_pago = this.suscripcion.tipo_plan;
            }
          }
        },
        error: () => {},
      });
    }
  }

  public iniciarCambioCliente() {
    this.reemplazandoCliente = true;
    this.asignandoCliente = false;
    this.editandoCliente = false;
    this.esNuevoCliente = false;
    this.clienteSeleccionado = null;
  }

  public cancelarCambioCliente() {
    this.reemplazandoCliente = false;
    this.asignandoCliente = false;
    if (this.suscripcion?.id_cliente) {
      this.apiService
        .read('superadmin/cliente/', this.suscripcion.id_cliente)
        .subscribe({
          next: (c) => {
            this.clienteSeleccionado = c;
            this.tipoAnterior = c.tipo;
          },
          error: () => {},
        });
    }
  }

  public puedeEditarCreditoCliente(): boolean {
    const tipo = this.apiService.auth_user()?.tipo || '';
    return ['Administrador', 'Supervisor', 'Supervisor Limitado'].includes(tipo);
  }

  public onHabilitaCreditoChange() {
    if (this.clienteSeleccionado.habilita_credito && !this.clienteSeleccionado.dias_credito) {
      const clasificacion = this.clienteSeleccionado.clasificacion?.toUpperCase();
      if (clasificacion === 'A' || clasificacion === 'B') {
        this.clienteSeleccionado.dias_credito = 30;
      } else if (clasificacion === 'C') {
        this.clienteSeleccionado.dias_credito = 15;
      } else {
        this.clienteSeleccionado.dias_credito = 30;
      }
    }
    if (!this.clienteSeleccionado.habilita_credito) {
      this.clienteSeleccionado.dias_credito = null;
      this.clienteSeleccionado.limite_credito = null;
    }
  }

  public onTipoChange() {
    // Implementación simplificada para el modal
    this.tipoAnterior = this.clienteSeleccionado.tipo;
  }

  public setPais() {
    this.clienteSeleccionado.pais = this.paises.find((item: any) => item.cod == this.clienteSeleccionado.cod_pais)?.nombre;
  }

  public setGiro() {
    this.clienteSeleccionado.giro = this.actividad_economicas.find((item: any) => item.cod == this.clienteSeleccionado.cod_giro)?.nombre;
  }

  public setDistrito() {
    let distrito = this.distritos.find((item: any) => item.cod == this.clienteSeleccionado.cod_distrito && item.cod_departamento == this.clienteSeleccionado.cod_departamento);
    if (distrito) {
      this.clienteSeleccionado.cod_municipio = distrito.cod_municipio;
      this.setMunicipio();
      this.clienteSeleccionado.distrito = distrito.nombre;
      this.clienteSeleccionado.cod_distrito = distrito.cod;
    }
  }

  public setMunicipio() {
    let municipio = this.municipios.find((item: any) => item.cod == this.clienteSeleccionado.cod_municipio && item.cod_departamento == this.clienteSeleccionado.cod_departamento);
    if (municipio) {
      this.clienteSeleccionado.municipio = municipio.nombre;
      this.clienteSeleccionado.cod_municipio = municipio.cod;
      this.clienteSeleccionado.distrito = '';
      this.clienteSeleccionado.cod_distrito = '';
    }
  }

  public setDepartamento() {
    let departamento = this.departamentos.find((item: any) => item.cod == this.clienteSeleccionado.cod_departamento);
    if (departamento) {
      this.clienteSeleccionado.departamento = departamento.nombre;
      this.clienteSeleccionado.cod_departamento = departamento.cod;
    }
    this.clienteSeleccionado.municipio = '';
    this.clienteSeleccionado.cod_municipio = '';
    this.clienteSeleccionado.distrito = '';
    this.clienteSeleccionado.cod_distrito = '';
  }

  public guardarCambiosCliente() {
    this.saving = true;
    const endpoint = this.esNuevoCliente
      ? 'superadmin/cliente'
      : 'superadmin/cliente/update';

    this.apiService.store(endpoint, this.clienteSeleccionado).subscribe({
      next: (cliente) => {
        if (this.esNuevoCliente) {
          this.vincularClienteASuscripcion(cliente.id);
        } else {
          this.clienteSeleccionado = cliente;
          this.persistirFacturacionModalTrasCliente(() => {
            this.editandoCliente = false;
            this.saving = false;
            this.alertService.success(
              'Éxito',
              'Información del cliente actualizada correctamente'
            );
          });
        }
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      },
    });
  }

  private vincularClienteASuscripcion(clienteId: number) {
    this.apiService
      .store('suscripcion/asignar-cliente', {
        suscripcion_id: this.suscripcion.id,
        cliente_id: clienteId,
        tipo_factura: this.suscripcion.tipo_factura ?? null,
      })
      .subscribe({
        next: () => {
          this.persistirFacturacionModalTrasCliente(() => {
            this.saving = false;
            this.editandoCliente = false;
            this.esNuevoCliente = false;
            this.reemplazandoCliente = false;
            this.alertService.success(
              'Éxito',
              'Cliente creado y vinculado correctamente'
            );

            this.apiService.read('superadmin/cliente/', clienteId).subscribe((c) => {
              this.clienteSeleccionado = c;
              this.tipoAnterior = c.tipo;
            });

            this.apiService
              .getAll(`suscripcion/${this.suscripcion.id}`)
              .subscribe((s) => {
                this.suscripcion = s;
                if (s.empresa) {
                  this.empresa = { ...(this.empresa || {}), ...s.empresa };
                }
              });
            this.filtrarSuscripciones();
          });
        },
        error: (err) => {
          this.saving = false;
          this.alertService.error(
            'Cliente creado pero no se pudo vincular: ' + err
          );
        },
      });
  }

  private ensureEmpresaFacturacionModal(): void {
    if (!this.empresa || typeof this.empresa !== 'object') {
      this.empresa = { ...(this.suscripcion?.empresa || {}) };
    }
    const empId =
      this.suscripcion?.empresa_id ?? this.suscripcion?.empresa?.id;
    if (empId && !this.empresa.id) {
      this.empresa = { ...this.empresa, id: empId };
    }
    if (!this.empresa.frecuencia_pago && this.suscripcion?.tipo_plan) {
      this.empresa.frecuencia_pago = this.suscripcion.tipo_plan;
    }
  }

  private normalizarTipoFacturaModal(
    v: string | null | undefined
  ): string {
    if (v == null || v === '') {
      return '';
    }
    return String(v);
  }

  private persistirFacturacionModalTrasCliente(onOk: () => void): void {
    this.persistirTipoFacturaFacturacionSiCambio(() =>
      this.persistirFrecuenciaPagoFacturacionSiCambio(onOk)
    );
  }

  private persistirTipoFacturaFacturacionSiCambio(onOk: () => void): void {
    const actual = this.normalizarTipoFacturaModal(
      this.suscripcion?.tipo_factura
    );
    const inicial = this.tipoFacturaFacturacionInicial ?? '';
    if (!this.suscripcion?.id || actual === inicial) {
      onOk();
      return;
    }

    this.apiService
      .store('suscripcion/tipo-factura', {
        suscripcion_id: this.suscripcion.id,
        tipo_factura: this.suscripcion.tipo_factura ?? null,
      })
      .subscribe({
        next: (res: any) => {
          if (res?.data) {
            this.suscripcion = { ...this.suscripcion, ...res.data };
          }
          this.tipoFacturaFacturacionInicial =
            this.normalizarTipoFacturaModal(this.suscripcion?.tipo_factura);
          this.filtrarSuscripciones();
          onOk();
        },
        error: (err) => {
          this.saving = false;
          this.alertService.error(err);
        },
      });
  }

  private persistirFrecuenciaPagoFacturacionSiCambio(onOk: () => void): void {
    const actual =
      this.empresa?.frecuencia_pago == null || this.empresa.frecuencia_pago === ''
        ? ''
        : String(this.empresa.frecuencia_pago);
    const inicial = this.frecuenciaPagoFacturacionInicial ?? '';
    if (!this.suscripcion?.id || actual === inicial) {
      onOk();
      return;
    }

    const payload: Record<string, unknown> = {
      suscripcion_id: this.suscripcion.id,
      frecuencia_pago: actual || null,
    };
    const mm = this.suscripcion.monto_mensual;
    const ma = this.suscripcion.monto_anual;
    if (typeof mm === 'number' && !Number.isNaN(mm)) {
      payload['monto_mensual'] = mm;
    }
    if (typeof ma === 'number' && !Number.isNaN(ma)) {
      payload['monto_anual'] = ma;
    }

    this.apiService.store('suscripcion/frecuencia-pago', payload).subscribe({
      next: (res: any) => {
        if (res?.data?.empresa) {
          this.empresa = { ...this.empresa, ...res.data.empresa };
        }
        if (res?.data?.suscripcion) {
          this.suscripcion = { ...this.suscripcion, ...res.data.suscripcion };
        }
        this.frecuenciaPagoFacturacionInicial =
          this.empresa?.frecuencia_pago == null || this.empresa.frecuencia_pago === ''
            ? ''
            : String(this.empresa.frecuencia_pago);
        this.filtrarSuscripciones();
        onOk();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      },
    });
  }

  public cancelarAsignacion() {
    this.asignandoCliente = false;
    this.clienteSeleccionado = null;
  }

  public getPagosFiltrados(tipo: 'n1co' | 'transferencia'): OrdenPago[] {
    if (!this.historialPagos || !Array.isArray(this.historialPagos)) {
      return [];
    }

    return this.historialPagos.filter((pago) => {
      if (tipo === 'n1co') {
        return (
          pago.metodo_pago === 'n1co' ||
          pago.metodo_pago === 'tarjeta' ||
          !pago.metodo_pago
        );
      } else {
        return pago.metodo_pago === 'transferencia';
      }
    });
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.filtrarSuscripciones();
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('suscripciones/exportar', this.filtros).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'suscripciones.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloading = false;
      }
    );
  }
}
