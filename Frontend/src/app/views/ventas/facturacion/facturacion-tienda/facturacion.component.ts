import { Component, OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import { FE_PAIS_CR, FE_PAIS_HN, FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import {
  formatoCorrelativoHn,
  nombresDocumentosVentaNormales as nombresVentaPorPais,
} from '@views/ventas/documentos/documento-nombre-options';
import { migrarExoneracionCrLegacyADetalles as migrarExoneracionLegacyUtil } from '@shared/modals/fe-cr-exoneracion-detalle/fe-cr-exoneracion-detalle.util';
import { xmlComprobanteDesdeRechazoFeCr } from '@services/facturacion-electronica/fe-cr-http-error.util';
import { abrirVentanaTextoFeCr } from '@services/facturacion-electronica/fe-cr-abrir-xml.util';
import { FuncionalidadesService } from '@services/functionalities.service';
import { RestauranteService } from '@services/restaurante.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { SharedDataService } from '@services/shared-data.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { VentaDetallesComponent } from './detalles/venta-detalles.component';
import { MetodosDePagoComponent } from './metodos-de-pago/metodos-de-pago.component';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { BuscadorClientesComponent } from '@shared/parts/buscador-clientes/buscador-clientes.component';
import { CrearProyectoComponent } from '@shared/modals/crear-proyecto/crear-proyecto.component';
import { NgSelectModule } from '@ng-select/ng-select';
import { FilterPipe } from '@pipes/filter.pipe';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { FidelizacionService, PuntosDisponiblesInfo, ConfiguracionCliente } from '@services/fidelizacion.service';
import { GiftCardsService, GiftCardLookup } from '@services/gift-cards.service';
import { esFormaPagoGiftCard, montoPagoGiftCardVenta, ventaUsaGiftCard } from '@utils/gift-card.util';
import { pedirPinDescuentoSiAplica } from '../venta-descuento-autorizacion.util';
import Swal from 'sweetalert2';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { CountryI18nService } from '@services/country-i18n.service';

import * as moment from 'moment';
import {
  acumularImpuestosVentaConCierreResidual,
  calcularMontosLineaDetalle,
  hidratarImpuestosProductosEnDetalles,
  prepararDetallesParaFacturarDesdeCotizacion,
  redondearMoneda,
  sincronizarTipoGravadoPorCobroIva,
  sumarSubTotalEncabezadoVenta,
  sumarTotalEncabezadoVenta,
  copiarImpuestosProductoAlDetalle,
} from '@utils/impuestos-venta.util';
import { esVentaPorConsigna, sincronizarFlagConsignaVenta, aplicarEstadoConsignaEnVenta } from '@utils/venta-consigna.util';
import { debeDispararAtajoTcla } from '@utils/atajos-teclado.util';
import { calcularCambioEfectivo } from '@utils/cambio-efectivo.util';
import { FACTURA_REMISION, esVentaConsignaRemision } from '../../../../constants/documento.constants';
import { SharedModule } from '@shared/shared.module';
import {
  debeEmitirDteAlFacturar,
  debeImprimirTrasFacturar,
  isImpresionEnFacturacionActiva,
} from '@helpers/empresa.helper';
import { aplicarPrefillCredito, prepararVentaParaFacturarCuota } from '@views/ventas/creditos/creditos-facturar';
import {
  aplicarPlanAVenta,
  generarPreviewCuotas,
  planCuadra,
  restoreSnapshotVenta,
  snapshotVentaMontos,
  sumaMontosCuotas,
  SnapshotMontosVenta,
  PreviewCuota,
} from '@views/ventas/creditos/creditos-cuotas';

@Component({
    selector: 'app-facturacion',
    templateUrl: './facturacion.component.html',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        NgSelectModule,
        CurrencyPipe,
        FilterPipe,
        VentaDetallesComponent,
        MetodosDePagoComponent,
        CrearClienteComponent,
        BuscadorClientesComponent,
        CrearProyectoComponent,
        TranslatePipe,
        SharedModule,
    ],
    providers: [SumPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class FacturacionComponent extends BaseModalComponent implements OnInit {
  public venta: any = {};
  public evento: any = {};
  public detalle: any = {};
  // public clientes: any = [];
  public proyectos: any = [];
  public usuarios: any = [];
  public documentos: any = [];
  private documentosSucursal: any[] = [];
  private documentosLoadSeq = 0;
  public formaPagos: any = [];
  public sucursales: any = [];
  public bodegas: any = [];
  public impuestos: any = [];
  /** Evita advertencia de “configure impuestos” antes de que responda GET /impuestos */
  private impuestosVentaCatalogoCargado = false;
  public recintos: any = [];
  public regimenes: any = [];
  public incoterms: any = [];
  public bancos: any = [];
  public editar = false;
  public canales: any = [];
  public supervisor: any = {};
  public override loading = false;
  public override saving = false;
  public sending = false;
  public emiting = false;
  public duplicarventa = false;
  public documentoCreditoBloqueado = false;
  public creditosClientesActivo = false;
  public creditoSnapshot: SnapshotMontosVenta | null = null;
  public planCuotasForm: any = { tipo: 'bien', n_cuotas: 2, fecha_inicio: '', concepto: '' };
  public planCuotasPreview: PreviewCuota[] = [];
  public facturarCotizacion = false;
  public documentoFiscalListo = true;
  public api: boolean = false;
  public opAvanzadas = false;
  public opAvanzadasFacturacion = false;
  public customFields: any = [];
  public selectedCustomFields: number[] = [];
  public activeCustomFields: any = [];
  public filtros: any = {
    bandera: true,
  };
  public customField: boolean = false;
  public tieneAccesoPropina: boolean = false;
  public tieneMultimoneda: boolean = false;
  /** Config pais_configuracion módulo moneda (cargada al activar multimoneda). */
  public monedaConfig: {
    moneda_funcional: string;
    monedas_documento: string[];
    fuente: string;
    permitir_editar: boolean;
  } | null = null;
  public tieneFidelizacionHabilitada: boolean = false;
  public mensajeValidacionFecha: string = '';
  public mensajeErrorBanco: string = '';
  public debeImprimir: boolean = false;
  public giftCardsActivo = false;
  public giftCardInfo: GiftCardLookup | null = null;
  public giftCardLookupError = '';
  public giftCardLookupLoading = false;
  public contabilidadHabilitada: boolean = false;

  /** Preview de tipo de cambio para el selector de moneda. */
  public tcPreview: { rate: number | null; date: string | null; loading: boolean; error: string | null } = {
    rate: null,
    date: null,
    loading: false,
    error: null,
  };
  public exchangeRateDraft: number | null = null;
  private modalTipoCambioRef?: BsModalRef;

  /** Si está activo, se muestra el monto; el importe es siempre la suma de `cuenta_a_terceros` en las líneas (no se edita en cabecera). */
  public habilitarCuentaTerceros = false;

  /**
   * Si el usuario movió el switch de retención IVA 1%, no aplicar la regla automática (gran contribuyente + monto)
   * hasta cambiar de cliente o iniciar un documento nuevo desde carga inicial.
   */
  private retencionIvaGcUsuarioDecidio = false;

  /** Pre-cuenta restaurante: al facturar desde cuenta-mesa */
  preCuentaId: number | null = null;
  sesionId: number | null = null;

  /** Pedido canal (Spoties / manual): al facturar desde listado de pedidos */
  pedidoCanalId: number | null = null;

  // Información de puntos canjeados
  public puntosCanjeados: number = 0;
  public descuentoPuntos: number = 0;

  // Propiedades para el botón de puntos
  public puntosCliente: number = 0;
  public loadingPuntos: boolean = false;

  // Propiedades para el modal de puntos
  public loadingModalPuntos: boolean = false;
  public puntosInfoModal: PuntosDisponiblesInfo | null = null;
  public configuracionModal: ConfiguracionCliente | null = null;
  public puntosProximosAExpirarModal: any[] = [];
  public usarPuntosModal: boolean = false;
  public puntosACanjearModal: number = 0;

  override modalRef!: BsModalRef;
  modalCredito!: BsModalRef;
  modalPuntosRef!: BsModalRef;
  modalGiftCardsRef?: BsModalRef;
  public giftCardsEmitidas: GiftCardLookup[] = [];
  private ventaPostFacturaPendiente: any = null;

  @ViewChild('msupervisor')
  public supervisorTemplate!: TemplateRef<any>;

  @ViewChild('modalPuntos')
  public modalPuntosTemplate!: TemplateRef<any>;

  @ViewChild('mcredito')
  public creditoTemplate!: TemplateRef<any>;

  @ViewChild('mgiftCardsEmitidas')
  public giftCardsEmitidasTemplate!: TemplateRef<any>;

  @ViewChild(VentaDetallesComponent)
  private ventaDetalles?: VentaDetallesComponent;

  private cdr = inject(ChangeDetectorRef);
  private countryI18n = inject(CountryI18nService);

  constructor(
    public apiService: ApiService,
    private facturacionElectronica: FacturacionElectronicaService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private modalService: BsModalService,
    private sumPipe: SumPipe,
    private route: ActivatedRoute,
    private router: Router,
    private sharedDataService: SharedDataService,
    private fidelizacionService: FidelizacionService,
    private funcionalidadesService: FuncionalidadesService,
    private restauranteService: RestauranteService,
    private giftCardsService: GiftCardsService,
  ) {
    super(modalManager, alertService);
    this.router.routeReuseStrategy.shouldReuseRoute = function () {
      return false;
    };
  }

  // Integración Boxful
  public paqueteData: any = { peso: 1, alto: 10, ancho: 10, largo: 10, es_fragil: false, id: null };
  private lastSyncedPaqueteId: number | null = null;
  public tieneBoxful = false;
  public mostrarModalBoxful = false;
  public boxfulVentaId: number | null = null;
  public boxfulClienteId: number | null = null;
  public boxfulSugerirCod = false;
  public boxfulMontoCod: number | null = null;
  public boxfulPaqueteData: any = {
    peso: 1, alto: 11, ancho: 43, largo: 47.5, es_fragil: false, id: null, parcels: []
  };

  esCanalBoxful(): boolean {
    if (!this.venta.id_canal || !this.canales) return false;
    const canal = this.canales.find((c: any) => c.id == this.venta.id_canal);
    return !!(canal && canal.nombre === 'Boxful');
  }

  mostrarAlertaEnvioBoxful(): boolean {
    return this.tieneBoxful && this.esCanalBoxful() && this.venta.cotizacion != 1;
  }

  private debePreguntarEnvioBoxful(): boolean {
    return this.mostrarAlertaEnvioBoxful() && !!this.venta?.id;
  }

  private preguntarGenerarEnvioBoxful(venta: any): void {
    Swal.fire({
      title: 'Envío BoxFul',
      text: 'La venta se guardó correctamente. ¿Desea generar el envío BoxFul ahora o hacerlo después desde el listado de ventas?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Generar ahora',
      cancelButtonText: 'Después',
      reverseButtons: true,
      allowOutsideClick: false,
    }).then((result) => {
      if (result.isConfirmed) {
        this.abrirWizardBoxfulDesdeVenta(venta);
      } else {
        this.router.navigate(['/ventas']);
        this.alertService.success(
          'Venta creada',
          'Puede generar el envío BoxFul después desde el listado de ventas.'
        );
      }
    });
  }

  private resolverPaqueteStubBoxful(venta: any): any | null {
    const paquetes = venta?.paquetes || this.venta?.paquetes || [];
    const stub = (paquetes as any[]).find((p) =>
      (p.transportista === 'Boxful' || p.transportista === 'boxful')
      && (!p.num_guia || String(p.num_guia).trim() === '' || String(p.num_guia).startsWith('PENDING-'))
    );
    if (stub?.id) {
      return stub;
    }
    if (venta?.boxful_paquete_stub_id || this.venta?.boxful_paquete_stub_id) {
      return { id: venta?.boxful_paquete_stub_id || this.venta?.boxful_paquete_stub_id, peso: 1 };
    }
    if (this.paqueteData?.id) {
      return { id: this.paqueteData.id, peso: this.paqueteData.peso || 1 };
    }
    return null;
  }

  private abrirWizardBoxfulDesdeVenta(venta: any): void {
    if (!venta?.id_cliente) {
      this.alertService.warning(
        'Atención',
        'La venta no tiene cliente asignado. Genere el envío después desde el listado de ventas.'
      );
      this.router.navigate(['/ventas']);
      return;
    }

    const stub = this.resolverPaqueteStubBoxful(venta);
    const peso = parseFloat(stub?.peso || 1) || 1;

    this.boxfulVentaId = venta.id;
    this.boxfulClienteId = venta.id_cliente;
    this.boxfulSugerirCod = !!(venta.credito || venta.condicion === 'Crédito' || this.venta?.credito || this.venta?.condicion === 'Crédito');
    this.boxfulMontoCod = parseFloat(venta.total || this.venta?.total || 0) || null;
    this.boxfulPaqueteData = {
      id: stub?.id || null,
      peso,
      alto: 11,
      ancho: 43,
      largo: 47.5,
      es_fragil: false,
      parcels: [{
        peso,
        alto: 11,
        ancho: 43,
        largo: 47.5,
        es_fragil: false,
        contenido: '',
        valor: parseFloat(venta.total || 50)
      }]
    };
    this.mostrarModalBoxful = true;
    this.alertService.success('Venta creada', 'Complete los datos del envío BoxFul.');
  }

  onBoxfulGuiaGenerada(guia: any): void {
    const numGuia = guia?.shipmentNumber || guia?.data?.shipmentNumber || '';
    if (numGuia) {
      this.alertService.success('Logística Boxful', `Guía #${numGuia} generada correctamente.`);
    }
    if (Array.isArray(guia?.warnings) && guia.warnings.length) {
      this.alertService.warning('Atención', guia.warnings.join(' '));
    }
  }

  cerrarModalBoxful(): void {
    this.mostrarModalBoxful = false;
    this.boxfulVentaId = null;
    this.boxfulClienteId = null;
    this.boxfulSugerirCod = false;
    this.boxfulMontoCod = null;
    this.boxfulPaqueteData = {
      peso: 1, alto: 11, ancho: 43, largo: 47.5, es_fragil: false, id: null, parcels: []
    };
    this.router.navigate(['/ventas']);
  }

  ngOnInit() {
    this.cargarDatosIniciales();
    this.verificarAccesoContabilidad();
    this.loadData();
    this.verificarAccesoPropina();
    this.verificarAccesoMultimoneda();
    this.verificarFidelizacionHabilitada();
    this.verificarGiftCardsActivo();
    this.verificarAccesoCreditosClientes();
  }

  verificarAccesoContabilidad() {
    this.funcionalidadesService.verificarAcceso('contabilidad')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (acceso) => {
          this.contabilidadHabilitada = acceso;
          this.cargarBancos(); // Cargar bancos después de verificar contabilidad
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al verificar acceso a contabilidad:', error);
          this.contabilidadHabilitada = false;
          this.cargarBancos(); // Cargar bancos incluso si hay error
          this.cdr.markForCheck();
        }
      });
  }

  cargarBancos() {
    // Si tiene contabilidad habilitada, usar el endpoint de cuentas bancarias
    // Si no tiene contabilidad, usar el endpoint simple de bancos (index)
    const endpoint = this.contabilidadHabilitada ? 'banco/cuentas/list' : 'bancos';

    this.apiService.getAll(endpoint).pipe(this.untilDestroyed()).subscribe(
      (bancos) => {
        // Si no tiene contabilidad, los bancos vienen como array de objetos {nombre, activo}
        // del endpoint bancos (index)
        // Necesitamos transformarlos para que tengan la estructura esperada {id, nombre_banco}
        if (!this.contabilidadHabilitada) {
          // Los bancos del endpoint bancos tienen estructura {nombre, activo}
          // Transformar a formato {id, nombre_banco} para que coincida con lo esperado
          // Usar el nombre como id temporal ya que no hay id en este endpoint
          this.bancos = bancos
            .filter((banco: any) => banco.activo === true || banco.activo === 1)
            .map((banco: any) => ({
              id: banco.nombre, // Usar nombre como id temporal
              nombre_banco: banco.nombre
            }));
        } else {
          // Con contabilidad, los bancos ya vienen en el formato correcto {id, nombre_banco, ...}
          this.bancos = bancos;
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );
  }

  public loadData() {
    this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(
      (sucursales) => {
        this.sucursales = sucursales;

        if (this.apiService.validateRole('super_admin', false)
          || this.apiService.validateRole('admin', false)) {
          this.sucursales = this.sucursales.filter(
            (item: any) => item.id == this.apiService.auth_user().id_sucursal);
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );

    //solo si es una cotizacion if (this.route.snapshot.queryParamMap.get('cotizacion')) {
    if (this.route.snapshot.queryParamMap.get('cotizacion')) {
      this.apiService.getAll('custom-fields', this.filtros).pipe(this.untilDestroyed()).subscribe(
        (customFields) => {
          // console.log('customFields', customFields);
          this.customFields = customFields;
          //verificar si hay campos personalizados
          if (this.customFields.data.length > 0) {
            // console.log('hay campos personalizados');
            this.customField = true;
          }else{
            // console.log('no hay campos personalizados');
            this.customField = false;
          }
          this.cdr.markForCheck();

        },
        (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
    }

    // Cargar bodegas usando SharedDataService
    this.sharedDataService.getBodegas()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (bodegas) => {
          this.bodegas = bodegas;
          // Alinear sucursal con la bodega y recargar documentos (el filtro es por sucursal).
          this.sincronizarSucursalDesdeBodega();
          this.cargarDocumentos();
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });

    // Cargar todos los usuarios usando SharedDataService (como en compras)
    this.sharedDataService.getUsuarios().pipe(this.untilDestroyed()).subscribe({
      next: (usuarios) => {
        this.usuarios = usuarios;
        const auth = this.apiService.auth_user();
        const listaCompletaVendedores =
          auth.tipo === 'Administrador' ||
          auth.tipo === 'Supervisor' ||
          auth.tipo === 'Supervisor Limitado' ||
          ((auth.tipo === 'Ventas' || auth.tipo === 'Ventas Limitado') &&
            this.apiService.isVentasPuedeCambiarVendedorFacturacion());
        if (!listaCompletaVendedores) {
          this.usuarios = this.usuarios.filter(
            (item: any) => item.id == auth.id
          );
        }
      },
      error: (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    });

    // Los bancos se cargan en cargarBancos() después de verificar contabilidad

    this.apiService.getAll('formas-de-pago/list').pipe(this.untilDestroyed()).subscribe(
      (formaPagos) => {
        this.formaPagos = formaPagos;
        // Si ya hay un método de pago seleccionado y no es Efectivo, asignar el banco por defecto
        if (this.venta.forma_pago && this.venta.forma_pago !== 'Efectivo' && this.venta.forma_pago !== 'Multiple' && this.venta.forma_pago !== 'Wompi') {
          const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.venta.forma_pago);
          if (formaPagoSeleccionada && formaPagoSeleccionada.banco && formaPagoSeleccionada.banco.nombre_banco) {
            // Solo asignar si no hay banco ya seleccionado
            if (!this.venta.detalle_banco) {
              this.venta.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            }
          }
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );

    this.apiService.getAll('canales/list').pipe(this.untilDestroyed()).subscribe(
      (canales) => {
        this.canales = canales;
        this.venta.id_canal = this.canales[0].id;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );

    this.apiService.getAll('boxful/status').pipe(this.untilDestroyed()).subscribe({
      next: (res: any) => {
        this.tieneBoxful = !!(res && res.connected);
        this.cdr.markForCheck();
      },
      error: () => {
        this.tieneBoxful = false;
        this.cdr.markForCheck();
      }
    });

    this.apiService.getAll('impuestos').pipe(this.untilDestroyed()).subscribe(
      (impuestos) => {
        // Filtrar solo los impuestos que aplican a ventas
        this.impuestos = impuestos.filter((impuesto: any) => impuesto.aplica_ventas !== false && impuesto.aplica_ventas !== 0);
        this.impuestosVentaCatalogoCargado = true;
        // Al editar cotización/venta no sobrescribir impuestos para no volver a agregarlos
        const esEdicion = !!this.route.snapshot.paramMap.get('id');
        const sinImpuestosEnVenta =
          !this.venta.impuestos ||
          !Array.isArray(this.venta.impuestos) ||
          this.venta.impuestos.length === 0;
        if (!esEdicion && sinImpuestosEnVenta && this.impuestos.length > 0) {
          this.venta.impuestos = [...this.impuestos];
          this.sumTotal();
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.impuestosVentaCatalogoCargado = true;
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );

    this.apiService.getAll('proyectos/list').pipe(this.untilDestroyed()).subscribe(
      (proyectos) => {
        this.proyectos = proyectos;
        this.loading = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );
  }

  public cargarDocumentos() {
    const seq = ++this.documentosLoadSeq;
    this.apiService.getAll('documentos/list').pipe(this.untilDestroyed()).subscribe(
      (documentos) => {
        if (seq !== this.documentosLoadSeq) {
          return;
        }
        const idSucursal = this.obtenerIdSucursalDocumentos();
        this.documentosSucursal = (documentos || []).filter(
          (doc: any) =>
            idSucursal != null && Number(doc.id_sucursal) === Number(idSucursal)
        );
        this.aplicarFiltroDocumentosVenta();
        this.cdr.markForCheck();
      },
      (error) => {
        if (seq !== this.documentosLoadSeq) {
          return;
        }
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );
  }

  /** Prioriza la sucursal de la bodega seleccionada; si no hay, usa la de la venta. */
  private obtenerIdSucursalDocumentos(): number | null {
    const bodega = this.bodegas?.find(
      (b: any) => Number(b.id) === Number(this.venta?.id_bodega)
    );
    if (bodega?.id_sucursal != null && bodega.id_sucursal !== '') {
      return Number(bodega.id_sucursal);
    }
    if (this.venta?.id_sucursal != null && this.venta.id_sucursal !== '') {
      return Number(this.venta.id_sucursal);
    }
    return null;
  }

  /** Asegura bodega+sucursal usables antes de filtrar documentos. */
  private sincronizarSucursalDesdeBodega(): void {
    if (!this.bodegas?.length) {
      return;
    }
    let bodega = this.bodegas.find(
      (b: any) => Number(b.id) === Number(this.venta?.id_bodega)
    );
    // Bodega inválida/ausente: tomar una de la sucursal; si no hay, la primera disponible.
    if (!bodega && this.venta?.id_sucursal != null && this.venta.id_sucursal !== '') {
      bodega = this.bodegas.find(
        (b: any) => Number(b.id_sucursal) === Number(this.venta.id_sucursal)
      );
    }
    if (!bodega) {
      bodega = this.bodegas[0];
    }
    if (bodega?.id_sucursal != null && bodega.id_sucursal !== '') {
      this.venta.id_sucursal = bodega.id_sucursal;
      this.venta.id_bodega = Number(bodega.id);
    }
  }

  private aplicarFiltroDocumentosVenta(): void {
    if (this.venta.cotizacion == 1) {
      this.documentos = this.documentosSucursal.filter(
        (x: any) => x.nombre == 'Cotización'
      );
      if (this.documentos.length === 0) {
        this.alertService.error('Debe crear un documento de cotización');
      }
      const documento = this.documentos.find((x: any) => x.nombre == 'Cotización');
      if (documento) {
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;
        this.venta.nombre_documento = documento.nombre;
      }
      return;
    }

    if (esVentaConsignaRemision(this.venta)) {
      this.documentos = this.documentosSucursal.filter(
        (doc: any) => doc.nombre === FACTURA_REMISION
      );
      this.seleccionarDocumentoRemisionConsigna();
      return;
    }

    const nombresVenta = nombresVentaPorPais(this.apiService.auth_user()?.empresa);
    const porWhitelist = this.documentosSucursal.filter((doc: any) =>
      nombresVenta.includes(String(doc.nombre || '').trim())
    );
    // Si no hay match exacto de nombres, no dejar el select vacío: excluir solo cotización/OC.
    this.documentos = porWhitelist.length
      ? porWhitelist
      : this.documentosSucursal.filter(
          (doc: any) =>
            doc.nombre !== 'Cotización' && doc.nombre !== 'Orden de compra'
        );

    const docActual = this.documentos.find(
      (x: any) => x.id == this.venta.id_documento
    );

    if (!docActual) {
      const pred = this.documentos.find((x: any) => x.predeterminado == 1);
      if (pred) {
        this.setDocumento(pred.id);
      } else if (this.documentos.length > 0) {
        this.setDocumento(this.documentos[0].id);
      } else {
        this.venta.id_documento = null;
        this.venta.correlativo = null;
        this.venta.nombre_documento = undefined;
        if (this.facturarCotizacion) {
          this.documentoFiscalListo = false;
          this.alertService.error(
            'Debe configurar un documento fiscal (Factura o Crédito fiscal) en la sucursal antes de facturar la cotización.'
          );
        }
      }
    } else {
      this.venta.nombre_documento = docActual.nombre;
      if (
        !this.venta.id ||
        this.venta.correlativo == null ||
        this.venta.correlativo === ''
      ) {
        this.venta.correlativo = docActual.correlativo;
      }
      if (this.facturarCotizacion) {
        this.documentoFiscalListo = true;
      }
    }
  }

  private seleccionarDocumentoRemisionConsigna(): void {
    const documento = this.documentos.find((x: any) => x.nombre === FACTURA_REMISION);
    if (!documento) {
      this.alertService.warning(
        'Consigna',
        'No hay un documento "Factura de remisión" configurado para esta sucursal.'
      );
      return;
    }

    this.venta.id_documento = documento.id;
    this.venta.correlativo = documento.correlativo;
    this.venta.nombre_documento = documento.nombre;
    this.venta.cobrar_impuestos = false;
    this.venta.percepcion = 0;
    this.venta.iva_percibido = 0;
    this.sumTotal();
  }

  private reiniciarDocumentoTrasCargarVentaBase(): void {
    this.venta.id_documento = null;
    this.venta.correlativo = null;
    this.cargarDocumentos();
  }

  public cargarDatosIniciales() {
    this.venta = {};
    this.habilitarCuentaTerceros = false;
    this.retencionIvaGcUsuarioDecidio = false;
    this.debeImprimir = isImpresionEnFacturacionActiva(this.apiService.auth_user()?.empresa);
    this.venta.fecha = this.apiService.date();
    this.venta.fecha_pago = this.apiService.date();
    this.venta.forma_pago = 'Efectivo';
    this.venta.tipo = 'Interna';
    this.venta.estado = 'Pagada';
    this.venta.condicion = 'Contado';

    // Asegurar que usuarios "Ventas Limitado" siempre tengan ventas al contado
    if (this.apiService.auth_user().tipo === 'Ventas Limitado') {
      this.venta.credito = false;
      this.venta.consigna = false;
    }

    this.venta.tipo_operacion = 'Gravada';
    this.venta.tipo_renta = null;
    this.venta.detalle_banco = '';
    this.venta.id_cliente = '';
    this.venta.detalles = [];
    this.venta.cliente = {};
    this.venta.descuento = 0;
    this.venta.sub_total = 0;
    this.venta.iva_percibido = 0;
    this.venta.iva_retenido = 0;
    this.venta.cotizacion = 0;
    if(this.canales.length > 0){
      this.venta.id_canal = this.canales[0].id;
    }
    this.venta.iva = 0;
    this.venta.total_costo = 0;
    this.venta.total = 0;
    this.venta.propina = 0;
    this.venta.cobrar_propina = false;
    if(this.impuestos.length > 0){
      this.venta.impuestos = this.impuestos;
    }else{
      this.venta.impuestos = [];
    }
    this.detalle = {};
    this.venta.cobrar_impuestos =
      this.apiService.auth_user().empresa.cobra_iva == 'Si' ? true : false;
    this.venta.id_bodega = this.apiService.auth_user().id_bodega;
    this.venta.id_usuario = this.apiService.auth_user().id;
    this.venta.id_vendedor = this.apiService.auth_user().id;
    this.venta.id_sucursal = this.apiService.auth_user().id_sucursal;
    this.venta.id_empresa = this.apiService.auth_user().id_empresa;
    this.inicializarMonedaVenta();
    let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
    if (corte) {
      this.venta.fecha = JSON.parse(sessionStorage.getItem('SP_corte')!).fecha;
      this.venta.caja_id = JSON.parse(
        sessionStorage.getItem('SP_corte')!
      ).id_caja;
      this.venta.corte_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
    }

    // Para proyectos
    if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
      this.venta.id_proyecto =
        +this.route.snapshot.queryParamMap.get('id_proyecto')!;
    }

    this.cargarPrefillCreditoCuota();
    this.cargarVentaParaFacturar();

    // Para cotizaciones Pre-venta
    if (this.route.snapshot.queryParamMap.get('cotizacion')) {
      this.venta.cotizacion = 1;
      this.venta.estado = 'pendiente';
      this.venta.tipo = 'cotizacion'; // Identificador para cotización
      this.syncVentaCreditoConsignaFlagsFromEstado();
    }

    // Pre-cuenta restaurante: state o queryParams (respaldo por si state se pierde)
    const navState = history.state as any;
    const qp = this.route.snapshot.queryParamMap;
    const preCuentaIdFromState = navState?.preCuentaId;
    const preCuentaIdFromQuery = qp.get('pre_cuenta');
    const preCuentaIdVal = preCuentaIdFromState ?? (preCuentaIdFromQuery ? +preCuentaIdFromQuery : null);
    if (preCuentaIdVal) {
      this.preCuentaId = preCuentaIdVal;
      this.sesionId = navState?.sesionId ?? (qp.get('sesion') ? +qp.get('sesion')! : null);
      const detalles = navState?.preCuentaData?.detalles ?? [];
      if (detalles.length) {
        const iva = this.apiService.auth_user()?.empresa?.iva ?? 0;
        this.venta.observaciones = ((this.venta.observaciones || '') + ' Mesa ' + (navState.preCuentaData.mesa_numero || '')).trim();
        this.venta.detalles = detalles.map((d: any) => {
          const sub = (d.cantidad || 0) * (parseFloat(d.precio) || 0);
          return {
            id_producto: d.id_producto,
            id_presentacion: d.id_presentacion ?? null,
            cantidad: d.cantidad,
            precio: parseFloat(d.precio).toFixed(4),
            descripcion: d.descripcion || '',
            costo: 0,
            descuento: 0,
            descuento_porcentaje: 0,
            sub_total: sub.toFixed(4),
            total: sub.toFixed(4),
            tipo_gravado: 'gravada',
            porcentaje_impuesto: iva,
            gravada: 0,
            exenta: 0,
            no_sujeta: 0,
            iva: 0,
          };
        });
        this.normalizarDetallesTipoGravado(this.venta);
        this.sumTotal();
        const pctPropinaEmpresa = parseFloat(String(this.apiService.auth_user()?.empresa?.propina_porcentaje ?? '')) || 0;
        if (pctPropinaEmpresa > 0) {
          this.venta.cobrar_propina = true;
          this.sumTotal();
        }
      }
    } else {
      const pedidoCanalFromState = navState?.pedidoCanalId;
      const pedidoCanalFromQuery = qp.get('pedido_canal');
      const pedidoCanalIdVal =
        pedidoCanalFromState ?? (pedidoCanalFromQuery ? +pedidoCanalFromQuery : null);
      if (pedidoCanalIdVal) {
        this.pedidoCanalId = pedidoCanalIdVal;
        const pdata = navState?.pedidoCanalData;
        if (pdata?.detalles?.length) {
          this.aplicarPedidoCanalAFactura({
            pedido_id: pedidoCanalIdVal,
            cliente_id: pdata.cliente_id,
            id_sucursal: pdata.id_sucursal,
            id_bodega: pdata.id_bodega,
            fecha: pdata.fecha,
            canal: pdata.canal,
            referencia_externa: pdata.referencia_externa,
            observaciones: pdata.observaciones,
            detalles: pdata.detalles
          });
        } else {
          this.restauranteService.prepararFacturaPedidoCanal(pedidoCanalIdVal).subscribe({
            next: (data) => this.aplicarPedidoCanalAFactura(data),
            error: (e) => this.alertService.error(e)
          });
        }
      }
    }

    // Para editar cotizaciones Pre-venta / ventas
    if (this.route.snapshot.paramMap.get('id')) {
      this.editar = true;
      const isCotizacion = this.venta.cotizacion == 1;
      const endpoint = isCotizacion ? 'cotizacionVentas/' : 'venta/';
      this.apiService
        .read(endpoint, +this.route.snapshot.paramMap.get('id')!)
        .subscribe(
          (venta) => {
            this.venta = this.adaptarCotizacionVentaSiAplica(venta, isCotizacion);
            sincronizarFlagConsignaVenta(this.venta);
            this.retencionIvaGcUsuarioDecidio = true;
            this.normalizarDetallesTipoGravado(this.venta);
            hidratarImpuestosProductosEnDetalles(
              this.venta.detalles,
              this.apiService.auth_user()?.empresa?.iva
            );
            this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
            this.syncVentaCreditoConsignaFlagsFromEstado();
            this.sincronizarPreviewMonedaDesdeVenta();
            this.sumTotal();

            // Obtener todos los custom_field_ids únicos de los detalles
            const usedCustomFieldIds = new Set();
            this.venta.detalles.forEach((detalle: any) => {
              if (detalle.custom_fields && detalle.custom_fields.length > 0) {
                detalle.custom_fields.forEach((cf: any) => {
                  if (cf.custom_field) {
                    usedCustomFieldIds.add(cf.custom_field.id);
                  }
                });
              }
            });
            // Pre-seleccionar los campos personalizados
            this.selectedCustomFields = Array.from(
              usedCustomFieldIds
            ) as number[];
            this.updateCustomFields();
            this.cdr.markForCheck();
          },
          (error) => {
            this.alertService.error(error);
            this.cdr.markForCheck();
          }
        );
    }

    // Facturar venta recurrente
    // Duplicar venta

    if (
      this.route.snapshot.queryParamMap.get('recurrente')! &&
      this.route.snapshot.queryParamMap.get('id_venta')!
    ) {
      this.duplicarventa = true;
      this.apiService
        .read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!)
        .pipe(this.untilDestroyed())
        .subscribe(
          (venta) => {
            this.venta = venta;
            this.retencionIvaGcUsuarioDecidio = true;
            this.normalizarDetallesTipoGravado(this.venta);
            hidratarImpuestosProductosEnDetalles(
              this.venta.detalles,
              this.apiService.auth_user()?.empresa?.iva
            );
            if (!this.venta.cliente) {
              this.venta.cliente = {};
            } else {
              this.venta.cliente.nombre = this.venta.cliente.tipo == 'Empresa' ? this.venta.cliente.nombre_empresa : this.venta.cliente.nombre_completo;
            }
            this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
            this.syncVentaCreditoConsignaFlagsFromEstado();
            this.venta.fecha = this.apiService.date();
            this.venta.fecha_pago = this.apiService.date();
            this.venta.id_documento = null;
            this.venta.correlativo = null;
            this.venta.tipo_dte = null;
            this.venta.numero_control = null;
            this.venta.codigo_generacion = null;
            this.venta.impuestos = this.impuestos;
            this.venta.sello_mh = null;
            this.venta.dte = null;
            this.venta.dte_invalidacion = null;
            this.venta.id = null;
            this.venta.detalles.forEach((detalle: any) => {
              detalle.id = null;
            });
            this.refrescarMonedaTrasResetFecha();
            this.sumTotal();
            this.cdr.markForCheck();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
            this.cdr.markForCheck();
          }
        );
    }

    // Facturar cotizacion
    if (
      this.route.snapshot.queryParamMap.get('facturar_cotizacion') &&
      this.route.snapshot.queryParamMap.get('id_venta')
    ) {
      if (this.apiService.restriccionesCotizacionesVendedoresActivas()) {
        this.alertService.error('No tiene permiso para facturar cotizaciones.');
        this.router.navigate(['/cotizaciones']);
      } else {
        this.facturarCotizacion = true;
        this.documentoFiscalListo = false;
        this.apiService
          .read('cotizacionVentas/', +this.route.snapshot.queryParamMap.get('id_venta')!)
          .subscribe(
            (venta) => {
              this.venta = this.adaptarCotizacionVentaSiAplica(venta, false);
              this.retencionIvaGcUsuarioDecidio = true;
              this.normalizarDetallesTipoGravado(this.venta);
              hidratarImpuestosProductosEnDetalles(
                this.venta.detalles,
                this.apiService.auth_user()?.empresa?.iva
              );
              if (!this.venta.cliente) {
                this.venta.cliente = {};
              } else {
                this.venta.cliente.nombre = this.venta.cliente.tipo == 'Empresa' ? this.venta.cliente.nombre_empresa : this.venta.cliente.nombre_completo;
              }
              this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
              this.venta.fecha = this.apiService.date();
              this.venta.fecha_pago = this.apiService.date();
              this.venta.id_documento = null;
              this.venta.correlativo = null;
              this.venta.nombre_documento = null;
              this.venta.estado = 'Pagada';
              this.venta.condicion = 'Contado';
              this.venta.impuestos = this.impuestos;
              this.venta.observaciones = '';
              this.venta.cotizacion = 0;
              this.venta.num_cotizacion = venta.id;
              this.venta.id = null;
              this.syncVentaCreditoConsignaFlagsFromEstado();
              prepararDetallesParaFacturarDesdeCotizacion(
                this.venta.detalles,
                !!this.venta.cobrar_impuestos,
                Number(this.apiService.auth_user()?.empresa?.iva ?? 0),
                { paisEmpresa: this.apiService.auth_user()?.empresa?.pais }
              );
              this.reiniciarDocumentoTrasCargarVentaBase();
              this.refrescarMonedaTrasResetFecha();
              this.sumTotal();

              // Para proyectos
              if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
                this.venta.detalles = [];
              }
            },
            (error) => {
              this.alertService.error(error);
              this.loading = false;
            }
          );
      }
    }

    // Facturar orden de compra
    if (this.route.snapshot.queryParamMap.get('facturar_orden_compra')!) {
      this.apiService.read('orden-de-compra/solicitud/', +this.route.snapshot.queryParamMap.get('id_orden_compra')!).pipe(this.untilDestroyed()).subscribe((ordenCompra) => {
        this.venta.num_orden = ordenCompra.id;
        this.cdr.markForCheck();

        this.apiService.getAll('clientes/buscar/' + (ordenCompra.empresa.dui ?? ordenCompra.empresa.nit)).subscribe((empresa) => {
          if(empresa.length > 0){
            this.setCliente(empresa[0]);
            console.log(empresa);

            // Solo procesar productos si el cliente existe
            this.procesarProductosOrdenCompra(ordenCompra.detalles);
            this.cdr.markForCheck();
          }else{
            const labelDoc = this.countryI18n.t('country.identity.withTaxIdOrFiscal');
            Swal.fire({
              title: 'Cliente no encontrado',
              html: `
                <div class="text-left">
                  <p><strong>No se encontró el cliente para poder facturar.</strong></p>
                  <p>Debe crear el cliente con los siguientes datos:</p>
                  <ul class="list-unstyled mt-3">
                    <li><strong>Nombre:</strong> ${ordenCompra.empresa.nombre || 'No disponible'}</li>
                    <li><strong>${labelDoc}:</strong> ${ordenCompra.empresa.dui || ordenCompra.empresa.nit || 'No disponible'}</li>
                  </ul>
                </div>
              `,
              icon: 'warning',
              confirmButtonText: 'Entendido',
              confirmButtonColor: '#3085d6'
            }).then(() => {
              window.history.back();
            });
            // No procesar productos si el cliente no existe
            this.cdr.markForCheck();
            return;
          }
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
      }, (error) => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); }
    );
    console.log(this.venta);
    }
    // Solo si ya hay bodegas (p. ej. tras emitir y quedarse); en el 1er ingreso las carga loadData.
    if (this.bodegas?.length) {
      this.sincronizarSucursalDesdeBodega();
      this.cargarDocumentos();
    }
  }
    // Método para procesar productos de orden de compra
  public procesarProductosOrdenCompra(detalles: any[]) {
    detalles.forEach((detalleCompra: any) => {
      this.apiService.getAll('producto/buscar-by-code/'+ detalleCompra.codigo).pipe(this.untilDestroyed()).subscribe((producto) => {
        if (producto) {
          let detalle: any = {};
          detalle.cantidad = detalleCompra.cantidad;
          detalle.descripcion = producto.nombre;
          detalle.id_producto = producto.id;
          detalle.precio = parseFloat(producto.precio);
          detalle.precios = Array.isArray(producto.precios) ? [...producto.precios] : [];
          detalle.precios.unshift({ precio: detalle.precio });
          this.aplicarCoincidenciaListaPreciosOrdenV1(detalle, detalleCompra, producto);
          if (
            this.apiService.auth_user().empresa.valor_inventario == 'promedio' &&
            producto.costo_promedio > 0
          ) {
            detalle.costo = parseFloat(producto.costo_promedio);
          } else {
            detalle.costo = parseFloat(producto.costo);
          }
          detalle.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
          copiarImpuestosProductoAlDetalle(
            detalle,
            producto,
            this.apiService.auth_user()?.empresa?.iva ?? 0
          );
          detalle.descuento = 0;
          detalle.id_vendedor = this.venta.id_vendedor;
          detalle.exenta = 0;
          detalle.no_sujeta = 0;
          detalle.cuenta_a_terceros = 0;
          detalle.total = detalle.precio * detalle.cantidad;
          // Base gravada para IVA: debe asignarse después de total (antes quedaba NaN y sumTotal dejaba IVA en 0)
          detalle.gravada = detalle.total;
          this.venta.detalles.push(detalle);
          this.sumTotal();
          this.cdr.markForCheck();
        } else {
           Swal.fire({
             title: 'Producto no encontrado',
             html: `
               <div class="text-left">
                 <p><strong>No se encontró el producto para poder facturar.</strong></p>
                 <p>Debe verificar o crear el producto con el siguiente código:</p>
                 <ul class="list-unstyled mt-3">
                   <li><strong>Código del producto:</strong> ${detalleCompra.codigo || 'Sin código'}</li>
                   <li><strong>Cantidad solicitada:</strong> ${detalleCompra.cantidad || 'No disponible'}</li>
                 </ul>
               </div>
             `,
             icon: 'warning',
             confirmButtonText: 'Entendido',
             confirmButtonColor: '#3085d6'
           }).then(() => {
             window.history.back();
           });
          this.cdr.markForCheck();
        }
      }, (error) => {
        Swal.fire({
          title: 'Error al buscar producto',
          html: `
            <div class="text-left">
              <p><strong>Error al buscar el producto.</strong></p>
              <p>No se pudo encontrar el producto con el siguiente código:</p>
              <ul class="list-unstyled mt-3">
                <li><strong>Código del producto:</strong> ${detalleCompra.codigo || 'Sin código'}</li>
                <li><strong>Cantidad solicitada:</strong> ${detalleCompra.cantidad || 'No disponible'}</li>
              </ul>
            </div>
          `,
          icon: 'error',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#3085d6'
        }).then(() => {
          window.history.back();
        });
        this.cdr.markForCheck();
      });
    });

    // Cita a venta
    if (this.route.snapshot.queryParamMap.get('id_cita')!) {
      this.loading = true;
      this.apiService
        .read('evento/', +this.route.snapshot.queryParamMap.get('id_cita')!)
        .pipe(this.untilDestroyed())
        .subscribe(
          (evento) => {
            this.evento = evento;
            this.venta.id_cliente = evento.id_cliente;
            this.venta.id_evento = evento.id;

            this.evento.productos.forEach((detalleProducto: any) => {
              this.apiService
                .read('producto/', detalleProducto.id_producto)
                .pipe(this.untilDestroyed())
                .subscribe(
                  (producto) => {
                    let detalle: any = {};
                    detalle.id_producto = producto.id;
                    detalle.descripcion = producto.nombre;
                    detalle.img = producto.img;
                    detalle.precio = parseFloat(producto.precio);
                    detalle.costo = parseFloat(producto.costo);
                    detalle.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
          copiarImpuestosProductoAlDetalle(
            detalle,
            producto,
            this.apiService.auth_user()?.empresa?.iva ?? 0
          );
                    if (producto.inventarios.length > 0) {
                      producto.inventarios = producto.inventarios.filter(
                        (item: any) =>
                          item.id_sucursal == this.venta.id_sucursal
                      );
                      detalle.stock = parseFloat(
                        this.sumPipe.transform(producto.inventarios, 'stock')
                      );
                    } else {
                      detalle.stock = null;
                    }
                    detalle.cantidad = detalleProducto.cantidad;
                    detalle.descuento = 0;
                    detalle.descuento_porcentaje = 0;
                    detalle.total_costo = detalle.costo;
                    detalle.total = detalle.precio;

                    if (!detalle.exenta) {
                      detalle.exenta = 0;
                    }
                    if (!detalle.no_sujeta) {
                      detalle.no_sujeta = 0;
                    }
                    if (!detalle.cuenta_a_terceros) {
                      detalle.cuenta_a_terceros = 0;
                    }

                    detalle.total = (
                      parseFloat(detalle.cantidad) *
                      parseFloat(detalle.precio) -
                      parseFloat(detalle.descuento)
                    ).toFixed(4);

                    this.venta.detalles.push(detalle);
                    this.sumTotal();
                    this.cdr.markForCheck();

                    if (!this.venta.propina) {
                      this.venta.propina = 0;
                    }

                    if (!detalle.gravada) {
                      detalle.gravada = detalle.total;
                    }

                    this.venta.detalles.push(detalle);
                    this.sumTotal();
                    this.loading = false;
                    this.cdr.markForCheck();
                  },
                  (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                  }
                );
            });
            this.cdr.markForCheck();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
            this.cdr.markForCheck();
          }
        );
    }

    this.cargarDocumentos();
    this.loadData();
  }

  totalPorMetodoDePago() {
    // Agregar los metodos que tengan asignado un monto
    this.venta.metodos_de_pago = this.formaPagos.filter(
      (item: any) => item.total && item.total > 0
    );
    this.formaPagos.push({ nombre: 'Multiple' });
    this.venta.forma_pago = 'Multiple';
    this.venta.efectivo = this.formaPagos.find(
      (item: any) => item.nombre == 'Efectivo'
    )?.total;
    this.syncGiftCardFieldsAfterPagoChange();
    this.actualizarCambioEfectivo();
    console.log(this.venta);
  }

  /** En la orden, `costo` es precio unitario **con IVA**. */
  private precioConIvaReferenciaDesdeOrden(det: any): number | null {
    const desdeCosto = Number(det?.costo);
    return Number.isFinite(desdeCosto) && desdeCosto > 0 ? desdeCosto : null;
  }

  private igualdadPrecioMercadoV1(a: number, b: number): boolean {
    if (!Number.isFinite(a) || !Number.isFinite(b)) return false;
    const diff = Math.abs(a - b);
    return diff <= 0.015 || diff <= 1e-6 * Math.max(Math.abs(a), Math.abs(b), 1);
  }

  /** Lista guarda sin IVA: compara `costo` (con IVA) contra cada precio × (1+IVA). */
  private aplicarCoincidenciaListaPreciosOrdenV1(
    detalle: any,
    detalleCompra: any,
    producto: any,
  ): void {
    const refConIva = this.precioConIvaReferenciaDesdeOrden(detalleCompra);
    const lista = detalle?.precios;
    if (refConIva == null || !Array.isArray(lista) || lista.length === 0) return;

    const pct =
      producto?.porcentaje_impuesto != null && producto?.porcentaje_impuesto !== ''
        ? Number(producto.porcentaje_impuesto)
        : this.apiService.auth_user()?.empresa?.iva ?? 0;

    let mejor: any = null;
    let mejorErr = Infinity;
    for (const p of lista) {
      const sinLista = Number(p?.precio);
      if (!Number.isFinite(sinLista)) continue;
      const precioListaConIva = pct > 0 ? sinLista * (1 + Number(pct) / 100) : sinLista;
      if (!this.igualdadPrecioMercadoV1(refConIva, precioListaConIva)) continue;
      const err = Math.abs(refConIva - precioListaConIva);
      if (err < mejorErr - 1e-9) {
        mejorErr = err;
        mejor = p;
      }
    }
    if (mejor == null || mejorErr > 0.015) return;
    detalle.precio = Number(mejor.precio);
    detalle.precios[0] = { precio: detalle.precio };
  }

  private syncPaqueteData(): void {
    if (!this.venta || !this.venta.detalles || !Array.isArray(this.venta.detalles)) {
      this.lastSyncedPaqueteId = null;
      this.paqueteData.id = null;
      return;
    }
    const pkgDetail = this.venta.detalles.find((d: any) => d.id_paquete);
    if (pkgDetail) {
      const pkgId = pkgDetail.id_paquete;
      if (pkgId !== this.lastSyncedPaqueteId) {
        this.lastSyncedPaqueteId = pkgId;
        this.paqueteData.id = pkgId;
        this.paqueteData.peso = parseFloat(pkgDetail.peso || pkgDetail.cantidad || 1);
        this.paqueteData.alto = parseFloat(pkgDetail.alto || 10);
        this.paqueteData.ancho = parseFloat(pkgDetail.ancho || 10);
        this.paqueteData.largo = parseFloat(pkgDetail.largo || 10);
        this.paqueteData.es_fragil = !!pkgDetail.es_fragil;
        this.paqueteData.valor = parseFloat(pkgDetail.total || 50);
      }
    } else {
      this.lastSyncedPaqueteId = null;
      this.paqueteData.id = null;
    }
  }

  public sumTotal(opts?: { preservePrecioIva?: boolean }) {
    this.syncPaqueteData();
    if (this.venta.cobrar_impuestos) {
      const sinImpuestosEnVenta =
        !this.venta.impuestos ||
        !Array.isArray(this.venta.impuestos) ||
        this.venta.impuestos.length === 0;
      if (sinImpuestosEnVenta && this.impuestos?.length > 0) {
        this.venta.impuestos = [...this.impuestos];
      }
      const aunSinImpuestos =
        !this.venta.impuestos ||
        !Array.isArray(this.venta.impuestos) ||
        this.venta.impuestos.length === 0;
      if (aunSinImpuestos) {
        if (!this.impuestosVentaCatalogoCargado) {
          // Aún no respondió GET /impuestos: no mostrar advertencia ni apagar IVA
        } else if (this.impuestos.length === 0) {
          this.alertService.warning(
            'Configuración requerida',
            this.countryI18n.tax('configureTaxBeforeIncludeVentas')
          );
          this.venta.cobrar_impuestos = false;
          return;
        } else {
          this.venta.impuestos = [...this.impuestos];
        }
      }
    }

    // Asegurar que detalles existe y es un array
    if (!this.venta.detalles || !Array.isArray(this.venta.detalles)) {
      this.venta.detalles = [];
    }

    // Asegurar que impuestos existe y es un array
    if (!this.venta.impuestos || !Array.isArray(this.venta.impuestos)) {
      this.venta.impuestos = [];
    }

    const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
    const paisEmpresa = this.apiService.auth_user()?.empresa?.pais;
    this.venta.detalles.forEach((d: any) => {
      if (String(d?.tipo_gravado || '').toLowerCase() === 'exonerada') {
        return;
      }
      calcularMontosLineaDetalle(d, !!this.venta.cobrar_impuestos, empresaIva, {
        paisEmpresa,
        preservePrecioIva: !!opts?.preservePrecioIva,
      });
    });

    this.venta.sub_total = Number(sumarSubTotalEncabezadoVenta(this.venta.detalles)).toFixed(4);

    this.sincronizarRetencionGranContribuyente();

    const rawExenta = parseFloat(this.sumPipe.transform(this.venta.detalles, 'exenta'));
    this.venta.exenta = redondearMoneda(rawExenta).toFixed(4);
    const rawNoSujeta = parseFloat(this.sumPipe.transform(this.venta.detalles, 'no_sujeta'));
    this.venta.no_sujeta = redondearMoneda(rawNoSujeta).toFixed(4);
    const rawGravada = parseFloat(this.sumPipe.transform(this.venta.detalles, 'gravada'));
    this.venta.gravada = redondearMoneda(rawGravada).toFixed(4);
    const rawCuentaTerceros = parseFloat(this.sumPipe.transform(this.venta.detalles, 'cuenta_a_terceros'));
    this.venta.cuenta_a_terceros = Number(rawCuentaTerceros).toFixed(4);

    const subTotalNum = parseFloat(this.venta.sub_total);
    this.venta.iva_percibido = this.venta.percepcion
      ? Math.round(subTotalNum * 0.01 * 100) / 100
      : 0;
    this.venta.iva_retenido = this.venta.retencion
      ? Math.round(subTotalNum * 0.01 * 100) / 100
      : 0;
    this.venta.renta_retenida = this.venta.renta
      ? Math.round(subTotalNum * 0.10 * 100) / 100
      : 0;

    // Calcular propina basada en el porcentaje de la empresa y el subtotal
    const propinaPorcentaje = parseFloat(this.apiService.auth_user().empresa.propina_porcentaje) || 0;
    this.venta.propina = this.venta.cobrar_propina
      ? Math.round(subTotalNum * (propinaPorcentaje / 100) * 100) / 100
      : 0;

    // IVA por tasa: acumula por impuesto (multi-impuesto) y cierra la diferencia residual
    // de IVA sin apagar tributos especiales (turismo, etc.) cuando cobrar_impuestos es false.
    const descuentoPuntos = parseFloat(this.venta.descuento_puntos || 0) || 0;
    const ivaEncabezado = acumularImpuestosVentaConCierreResidual(
      this.venta.impuestos,
      this.venta.detalles,
      !!this.venta.cobrar_impuestos,
      empresaIva,
      paisEmpresa,
      descuentoPuntos
    );
    this.venta.iva = ivaEncabezado.toFixed(4);

    const rawDescuento = parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'));
    this.venta.descuento = Number(rawDescuento).toFixed(4);
    const rawTotalCosto = parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'));
    this.venta.total_costo = Number(rawTotalCosto).toFixed(4);

    const totalNum = sumarTotalEncabezadoVenta(
      this.venta.detalles,
      this.venta.impuestos,
      {
        empresaIva,
        cuentaTerceros: parseFloat(this.venta.cuenta_a_terceros),
        ivaPercibido: parseFloat(String(this.venta.iva_percibido)),
        ivaRetenido: parseFloat(String(this.venta.iva_retenido)),
        rentaRetenida: parseFloat(String(this.venta.renta_retenida)),
        descuentoPuntos,
      }
    );
    this.venta.total = totalNum.toFixed(2);

    // Asignar tipoOperacion según los detalles
    if (this.venta.cobrar_impuestos) {
      this.venta.tipo_operacion = 'Gravada'; // Aplica IVA
    } else {
      this.venta.tipo_operacion = 'No Gravada'; // No aplica IVA
    }

    // Asignar tipo renta
    if (this.venta.detalles && this.venta.detalles.length > 0) {
        if (this.venta.detalles[0].tipo == 'Servicio'){
            this.venta.tipo_renta = this.apiService.auth_user().empresa.tipo_renta_servicios;
        }else{
            this.venta.tipo_renta = this.apiService.auth_user().empresa.tipo_renta_productos;
        }
    }

    this.actualizarCambioEfectivo();
  }

  /** Vuelto: efectivo recibido menos lo debido en efectivo (total + propina, o parte en pago mixto). */
  public actualizarCambioEfectivo(): void {
    this.venta.cambio = calcularCambioEfectivo({
      montoPago: this.venta.monto_pago,
      total: this.venta.total,
      propina: this.venta.propina,
      formaPago: this.venta.forma_pago,
      efectivo: this.venta.efectivo,
    });
  }

  /** Tras cargar una venta: mostrar el bloque de cuenta a terceros si hay monto en líneas o en cabecera. */
  private aplicarEstadoCuentaTercerosDesdeVentaCargada(): void {
    const cRaw = parseFloat(this.venta.cuenta_a_terceros) || 0;
    const sumDet = parseFloat(this.sumPipe.transform(this.venta.detalles || [], 'cuenta_a_terceros')) || 0;
    this.habilitarCuentaTerceros = sumDet > 0.0001 || cRaw > 0.0001;
  }

  onCuentaTercerosSwitchChange(): void {
    this.sumTotal();
  }

  /** Paquetes: al detectar en listado (o al marcar/agregar uno) cuenta a terceros &gt; 0. */
  onAlMenosUnPaqueteCuentaTerceros(): void {
    this.habilitarCuentaTerceros = true;
    this.sumTotal();
  }

    /** Umbral de subtotal (USD u otra moneda de la empresa): retención IVA 1% automática en GC si el subtotal alcanza este monto (por defecto 100). */
    private montoMinimoRetencionIvaGc(): number {
        const v = this.apiService.auth_user()?.empresa?.monto_minimo_retencion_iva_gc;
        const n = parseFloat(v);
        return !isNaN(n) && n >= 0 ? n : 100;
    }

    /** Activa o desactiva la retención según subtotal y tipo de contribuyente del cliente (si el usuario no la ajustó a mano). */
    private sincronizarRetencionGranContribuyente(): void {
        // ponytail: CR no usa retención IVA/renta SV; UI ocultos
        if (this.esFeCostaRicaFacturacion()) {
            this.venta.retencion = false;
            this.venta.renta = false;
            return;
        }
        const c = this.venta?.cliente;
        if (!c || c.tipo_contribuyente !== 'Grande') {
            return;
        }
        if (this.retencionIvaGcUsuarioDecidio) {
            return;
        }
        const sub = parseFloat(this.venta.sub_total) || 0;
        const min = this.montoMinimoRetencionIvaGc();
        this.venta.retencion = sub >= min;
    }

    /** El usuario movió el switch de retención IVA: no volver a imponer la regla automática en cada recálculo. */
    public onRetencionIvaManualChange(): void {
        this.retencionIvaGcUsuarioDecidio = true;
        this.sumTotal();
    }

    public onCobrarImpuestosChange(): void {
        this.ventaDetalles?.sincronizarIvasDetalles();
        this.sumTotal();
    }

    // Cliente
    private cargarPrefillCreditoCuota(): void {
        const idCuota = this.route.snapshot.queryParamMap.get('credito_cuota');
        if (!idCuota) {
            return;
        }
        this.apiService.get('creditos-clientes/cuotas/' + idCuota + '/prefill')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (prefill) => {
                    const fecha = prefill.fecha;
                    aplicarPrefillCredito(this.venta, prefill);
                    if (prefill.cliente?.id) {
                        this.setCliente(prefill.cliente);
                    }
                    this.venta.fecha = fecha;
                    this.venta.fecha_pago = fecha;
                    this.documentoCreditoBloqueado = !!prefill.documento_bloqueado;
                    this.syncVentaCreditoConsignaFlagsFromEstado();
                    this.sumTotal();
                    const applyDoc = (attempt = 0) => {
                        if (prefill.id_documento && this.documentos?.length) {
                            this.setDocumento(prefill.id_documento);
                            this.cdr.markForCheck();
                            return;
                        }
                        if (attempt < 40) {
                            setTimeout(() => applyDoc(attempt + 1), 100);
                        }
                    };
                    applyDoc();
                    this.cdr.markForCheck();
                },
                error: (err) => {
                    this.alertService.error(err);
                    this.cdr.markForCheck();
                },
            });
    }

    private cargarVentaParaFacturar(): void {
        if (
            !this.route.snapshot.queryParamMap.get('facturar') ||
            !this.route.snapshot.queryParamMap.get('id_venta')
        ) {
            return;
        }
        const idVenta = +this.route.snapshot.queryParamMap.get('id_venta')!;
        this.apiService.read('venta/', idVenta)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (venta) => {
                    this.venta = venta;
                    this.retencionIvaGcUsuarioDecidio = true;
                    this.normalizarDetallesTipoGravado(this.venta);
                    hidratarImpuestosProductosEnDetalles(
                        this.venta.detalles,
                        this.apiService.auth_user()?.empresa?.iva
                    );
                    if (!this.venta.cliente) {
                        this.venta.cliente = {};
                    } else {
                        this.venta.cliente.nombre = this.venta.cliente.tipo == 'Empresa'
                            ? this.venta.cliente.nombre_empresa
                            : this.venta.cliente.nombre_completo;
                    }
                    this.venta.cobrar_impuestos = this.venta.iva > 0;
                    prepararVentaParaFacturarCuota(this.venta);
                    this.documentoCreditoBloqueado = !!this.venta.id_documento;
                    this.sumTotal();
                    this.cdr.markForCheck();
                },
                error: (err) => {
                    this.alertService.error(err);
                    this.loading = false;
                    this.cdr.markForCheck();
                },
            });
    }

    public setCliente(cliente: any) {
        if (cliente.id) {
            this.retencionIvaGcUsuarioDecidio = false;
            cliente.nombre = cliente.tipo == 'Empresa' ? cliente.nombre_empresa : cliente.nombre_completo;
            this.venta.id_cliente = cliente.id;
            this.venta.cliente = cliente;
            if (cliente.tipo_fiscal === 'Exento') {
                this.venta.cobrar_impuestos = false;
                sincronizarTipoGravadoPorCobroIva(this.venta.detalles, false);
            }
            this.sumTotal();
            // Resetear puntos cuando cambia el cliente
            this.resetearPuntos();
            // Cargar puntos del cliente (solo si la empresa tiene fidelización habilitada)
            if (this.tieneFidelizacionHabilitada) {
                this.cargarPuntosCliente();
            }

            // Asignar vendedor si el cliente tiene uno asignado
            if(cliente.id_vendedor) {
                this.venta.id_vendedor = cliente.id_vendedor;
            }

            // Si el cliente tiene crédito habilitado, aplicar venta al crédito automáticamente
            if (cliente.habilita_credito && cliente.dias_credito) {
                this.venta.credito = true;
                this.venta.estado = 'Pendiente';
                this.venta.condicion = 'Crédito';
                const fechaVenta = this.venta.fecha || this.apiService.date();
                this.venta.fecha_pago = moment(fechaVenta).add(cliente.dias_credito, 'days').format('YYYY-MM-DD');
            }

            // Obtener saldo pendiente: siempre si pref "estado de cuenta en facturación" activa, o solo si tiene límite de crédito
            const cargarSaldo = this.apiService.isEstadoCuentaEnFacturacionHabilitado() || cliente.limite_credito;
            if (cargarSaldo) {
                this.venta.cliente = { ...this.venta.cliente, saldo_pendiente: 0 };
                this.apiService.getAll('cliente/' + cliente.id + '/saldo-pendiente').subscribe(
                    (res: any) => {
                        this.venta.cliente = { ...this.venta.cliente, saldo_pendiente: res.saldo_pendiente ?? 0 };
                    },
                    () => { this.venta.cliente = { ...this.venta.cliente, saldo_pendiente: 0 }; }
                );
            } else {
                this.venta.cliente = { ...this.venta.cliente, saldo_pendiente: null };
            }

            // Limpiar mensaje de validación al cambiar cliente
            this.mensajeValidacionFecha = '';
        } else {
            this.venta.cliente = { ...this.venta.cliente, saldo_pendiente: null };
            // Si no hay cliente, resetear puntos
            this.puntosCliente = 0;
            this.resetearPuntos();
        }
    }

    // Proyecto
    public setProyecto(proyecto: any) {
        if (!this.venta.id_proyecto) {
            this.proyectos.push(proyecto);
        }
        this.venta.id_proyecto = proyecto.id;
    }

    public setCredito() {
        // Prevenir que usuarios "Ventas Limitado" activen ventas al crédito
        if (this.apiService.auth_user().tipo === 'Ventas Limitado' && this.venta.credito) {
            this.venta.credito = false;
            this.alertService.error('Los usuarios de tipo "Ventas Limitado" no pueden crear ventas al crédito.');
            return;
        }
        if (this.venta.credito) {
            this.venta.estado = 'Pendiente';
            this.venta.condicion = 'Crédito';
            // Si el cliente tiene tiempo_pago configurado, usarlo; si no, usar 1 mes por defecto
            if (this.venta.cliente?.tiempo_pago) {
                const fechaBase = this.venta.fecha ? moment(this.venta.fecha) : moment();
                this.venta.fecha_pago = fechaBase.add(this.venta.cliente.tiempo_pago, 'days').format('YYYY-MM-DD');
            } else {
                this.venta.fecha_pago = moment().add(1, 'month').format('YYYY-MM-DD');
            }
        } else {
            if (this.creditoSnapshot) {
                restoreSnapshotVenta(this.venta, this.creditoSnapshot);
                this.creditoSnapshot = null;
                this.sumTotal();
            }
            this.venta.estado = 'Pagada';
            this.venta.condicion = 'Contado';
            this.venta.fecha_pago = moment().format('YYYY-MM-DD');
            // Limpiar mensaje de validación al cambiar a contado
            this.mensajeValidacionFecha = '';
        }
    }

    private verificarAccesoCreditosClientes(): void {
        this.funcionalidadesService.verificarAcceso('creditos-clientes')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (acceso) => {
                    this.creditosClientesActivo = acceso;
                    this.cdr.markForCheck();
                },
                error: () => {
                    this.creditosClientesActivo = false;
                    this.cdr.markForCheck();
                },
            });
    }

    montoContratoCredito(): number {
        return this.creditoSnapshot?.total || Number(this.venta.total) || 0;
    }

    get planCuotasCuadra(): boolean {
        return planCuadra(this.montoContratoCredito(), this.planCuotasPreview);
    }

    get sumaPlanCuotas(): number {
        return sumaMontosCuotas(this.planCuotasPreview);
    }

    get diferenciaPlanCuotas(): number {
        return Math.round((this.montoContratoCredito() - this.sumaPlanCuotas) * 100) / 100;
    }

    regenerarPlanCuotas(usarGuardadas = false): void {
        this.planCuotasPreview = generarPreviewCuotas(
            this.montoContratoCredito(),
            Number(this.planCuotasForm.n_cuotas) || 0,
            this.planCuotasForm.fecha_inicio,
        );
        if (usarGuardadas) {
            const guardadas = this.venta.credito_contrato?.cuotas;
            if (Array.isArray(guardadas) && guardadas.length === this.planCuotasPreview.length) {
                this.planCuotasPreview = this.planCuotasPreview.map((c, i) => ({
                    ...c,
                    monto: Number(guardadas[i]?.monto) > 0 ? Number(guardadas[i].monto) : c.monto,
                }));
            }
        }
        this.cdr.markForCheck();
    }

    actualizarFechasPlan(): void {
        const base = generarPreviewCuotas(
            this.montoContratoCredito(),
            Number(this.planCuotasForm.n_cuotas) || 0,
            this.planCuotasForm.fecha_inicio,
        );
        if (base.length === this.planCuotasPreview.length) {
            this.planCuotasPreview.forEach((c, i) => {
                c.fechaVencimiento = base[i].fechaVencimiento;
            });
        } else {
            this.regenerarPlanCuotas();
        }
        this.cdr.markForCheck();
    }

    onMontoPlanChange(): void {
        this.cdr.markForCheck();
    }

    public abrirPlanCuotas(template: TemplateRef<any>): void {
        if (!this.venta.id_cliente) {
            this.alertService.error('Seleccione un cliente antes de armar el plan de cuotas.');
            return;
        }
        if (!this.venta.detalles?.length || (!(Number(this.venta.total) > 0) && !this.creditoSnapshot)) {
            this.alertService.error('Agregue productos y un total mayor a 0.');
            return;
        }
        if (!this.creditoSnapshot) {
            this.creditoSnapshot = snapshotVentaMontos(this.venta);
        }
        const fecha = (this.venta.fecha || moment().format('YYYY-MM-DD')).toString().slice(0, 10);
        this.planCuotasForm = {
            tipo: this.venta.credito_contrato?.tipo || 'bien',
            n_cuotas: this.venta.credito_contrato?.n_cuotas || 2,
            fecha_inicio: this.venta.credito_contrato?.fecha_inicio || fecha,
            concepto: this.venta.credito_contrato?.concepto || '',
        };
        this.regenerarPlanCuotas(true);
        this.openModal(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public confirmarPlanCuotas(): void {
        if (!this.creditoSnapshot || !this.planCuotasCuadra) {
            this.alertService.error('La suma de las cuotas debe coincidir con el monto del contrato.');
            return;
        }
        aplicarPlanAVenta(this.venta, { ...this.planCuotasForm, cuotas: this.planCuotasPreview }, this.creditoSnapshot);
        this.sumTotal({ preservePrecioIva: true });
        this.closeModal();
        this.cdr.markForCheck();
    }

    /**
     * Valida si la fecha de pago está dentro del rango permitido según la clasificación del cliente
     * A: máximo 90 días, B: máximo 60 días, C: máximo 30 días
     */
    validarFechaPagoPorClasificacion(fechaPago: string): boolean {
        if (!this.venta.cliente?.clasificacion || !fechaPago) {
            return true; // Si no hay cliente o fecha, no validar
        }

        const hoy = moment();
        const fechaSeleccionada = moment(fechaPago);
        const diasDiferencia = fechaSeleccionada.diff(hoy, 'days');

        let diasMaximos = 30; // Por defecto 30 días (clasificación C)

        switch (this.venta.cliente.clasificacion.toUpperCase()) {
            case 'A':
                diasMaximos = 90;
                break;
            case 'B':
                diasMaximos = 60;
                break;
            case 'C':
                diasMaximos = 30;
                break;
            default:
                diasMaximos = 30;
                break;
        }

        return diasDiferencia <= diasMaximos;
    }

    /**
     * Obtiene el mensaje de validación para la fecha de pago según la clasificación
     */
    obtenerMensajeValidacionFecha(): string {
        if (!this.venta.cliente?.clasificacion) {
            return '';
        }

        let diasMaximos = 30;
        let clasificacion = 'C';

        switch (this.venta.cliente.clasificacion.toUpperCase()) {
            case 'A':
                diasMaximos = 90;
                clasificacion = 'A';
                break;
            case 'B':
                diasMaximos = 60;
                clasificacion = 'B';
                break;
            case 'C':
                diasMaximos = 30;
                clasificacion = 'C';
                break;
        }

        return `Clientes de clasificación ${clasificacion} no puede exceder ${diasMaximos} días.`;
    }

    /**
     * Valida la fecha de pago cuando cambia y muestra mensaje si está fuera del rango
     */
    public validarFechaPago() {
        this.mensajeValidacionFecha = ''; // Limpiar mensaje anterior

        if (this.venta.credito && this.venta.fecha_pago) {
            if (!this.validarFechaPagoPorClasificacion(this.venta.fecha_pago)) {
                this.mensajeValidacionFecha = this.obtenerMensajeValidacionFecha();

                // Revertir a la fecha anterior o establecer una fecha válida
                const hoy = moment();
                let diasMaximos = 30;

                if (this.venta.cliente?.clasificacion) {
                    switch (this.venta.cliente.clasificacion.toUpperCase()) {
                        case 'A':
                            diasMaximos = 90;
                            break;
                        case 'B':
                            diasMaximos = 60;
                            break;
                        case 'C':
                            diasMaximos = 30;
                            break;
                    }
                }

                // Establecer la fecha máxima permitida
                this.venta.fecha_pago = hoy.add(diasMaximos, 'days').format('YYYY-MM-DD');
            }
        }
    }

    public setConsigna() {
        if (this.venta.consigna) {
            if (!this.venta.id_cliente) {
                this.alertService.warning('Consigna', 'Seleccione un cliente para ventas por consigna.');
                this.venta.consigna = false;
                return;
            }
            if (this.venta.detalles?.length) {
                this.venta.detalles = [];
                this.alertService.warning(
                    'Consigna',
                    'Se vació el detalle. Toda la factura quedará en consigna; no puede mezclar líneas de venta normal y consigna.'
                );
                this.sumTotal();
            }
            this.venta.estado = 'Consigna';
            this.venta.credito = true;
            this.venta.condicion = 'Crédito';
            this.aplicarFiltroDocumentosVenta();
        } else {
            if (this.venta.detalles?.length) {
                this.venta.detalles = [];
                this.sumTotal();
            }
            this.setCredito();
            this.aplicarFiltroDocumentosVenta();
        }
    }

    /** Alinea switches UI con `estado` al cargar (credito/consigna no vienen del API). */
    private syncVentaCreditoConsignaFlagsFromEstado(): void {
        if (!this.venta) return;
        const e = this.venta.estado;
        this.venta.consigna = e === 'Consigna';
        this.venta.credito = e === 'Pendiente' || e === 'Consigna';
    }

    public updateVenta(venta: any) {
        this.venta = venta;
        sincronizarFlagConsignaVenta(this.venta);
        this.syncVentaCreditoConsignaFlagsFromEstado();
        this.sumTotal();
    }

    public cambioMetodoDePago() {
        if (this.venta.forma_pago != 'Multiple') {
            this.venta.metodos_de_pago = [];
            this.venta.efectivo = this.venta.total;
            this.formaPagos.forEach((item: any) => {
                item.total = null;
            });
        }
        this.syncGiftCardFieldsAfterPagoChange();

        // Si el método de pago requiere banco, asignar el banco por defecto del método de pago
        if (this.requiereBanco()) {
            const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.venta.forma_pago);

            if (formaPagoSeleccionada && formaPagoSeleccionada.banco && formaPagoSeleccionada.banco.nombre_banco) {
                this.venta.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else {
                this.venta.detalle_banco = '';
            }
            this.mensajeErrorBanco = '';
        } else if (!this.requiereBanco()) {
            this.venta.detalle_banco = '';
            this.mensajeErrorBanco = '';
        }
      this.actualizarCambioEfectivo();
        this.cdr.markForCheck();
    }

    /** Catálogo MH (incoterm, recinto, régimen) y DTE 11: solo El Salvador. */
    esFacturacionElSalvador(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_SV;
    }

    esFeCostaRicaFacturacion(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
    }

  // ==================== MULTIMONEDA (por país) ====================

  get monedaFuncional(): string {
    return (this.monedaConfig?.moneda_funcional
      || this.apiService.auth_user()?.empresa?.moneda
      || 'USD').toUpperCase();
  }

  get monedasDocumento(): string[] {
    const list = this.monedaConfig?.monedas_documento;
    if (Array.isArray(list) && list.length) {
      return list.map((m) => String(m).toUpperCase());
    }
    return [this.monedaFuncional, 'USD'].filter((v, i, a) => a.indexOf(v) === i);
  }

  get simboloMonedaFuncional(): string {
    const map: Record<string, string> = { CRC: '₡', HNL: 'L', USD: '$', GTQ: 'Q', NIO: 'C$' };
    return map[this.monedaFuncional] || this.monedaFuncional;
  }

  /** Spec: default = moneda funcional del país / empresa. Requiere funcionalidad `multimoneda`. */
  private inicializarMonedaVenta(): void {
    if (!this.tieneMultimoneda) {
      return;
    }
    const cargar = () => {
      const monedaEmpresa = (this.apiService.auth_user()?.empresa?.moneda || '').toUpperCase();
      const funcional = this.monedaFuncional;
      this.venta.currency_code = this.monedasDocumento.includes(monedaEmpresa)
        ? monedaEmpresa
        : funcional;
      if (this.venta.currency_code === 'USD' && this.venta.currency_code !== funcional) {
        this.cargarTipoCambioPreview();
      } else {
        this.venta.exchange_rate = 1;
        this.tcPreview = { rate: 1, date: this.venta.fecha, loading: false, error: null };
      }
    };

    if (this.monedaConfig) {
      cargar();
      return;
    }

    this.apiService.getAll('moneda/config').pipe(this.untilDestroyed()).subscribe({
      next: (cfg: any) => {
        this.monedaConfig = {
          moneda_funcional: String(cfg?.moneda_funcional || 'USD').toUpperCase(),
          monedas_documento: Array.isArray(cfg?.monedas_documento) ? cfg.monedas_documento : [],
          fuente: cfg?.fuente || 'manual',
          permitir_editar: !!cfg?.permitir_editar,
        };
        cargar();
        this.cdr.markForCheck();
      },
      error: () => {
        this.monedaConfig = {
          moneda_funcional: this.monedaFuncional,
          monedas_documento: this.monedasDocumento,
          fuente: 'manual',
          permitir_editar: true,
        };
        cargar();
        this.cdr.markForCheck();
      },
    });
  }

  private refrescarMonedaTrasResetFecha(): void {
    if (!this.tieneMultimoneda) {
      return;
    }
    if (this.venta.currency_code === 'USD' && this.venta.currency_code !== this.monedaFuncional) {
      this.cargarTipoCambioPreview();
    } else {
      this.sincronizarPreviewMonedaDesdeVenta();
    }
  }

  private sincronizarPreviewMonedaDesdeVenta(): void {
    if (!this.tieneMultimoneda) {
      return;
    }
    if (!this.venta.currency_code) {
      this.inicializarMonedaVenta();
      return;
    }
    if (this.venta.currency_code === this.monedaFuncional) {
      this.tcPreview = { rate: 1, date: this.venta.exchange_rate_date || this.venta.fecha, loading: false, error: null };
      return;
    }
    const rate = parseFloat(this.venta.exchange_rate);
    this.tcPreview = {
      rate: Number.isFinite(rate) ? rate : null,
      date: this.venta.exchange_rate_date || this.venta.fecha,
      loading: false,
      error: null,
    };
  }

  get monedaVenta(): string {
    return this.venta?.currency_code || this.monedaFuncional;
  }

  get etiquetaOpcionUsd(): string {
    const rate = parseFloat(this.venta?.exchange_rate);
    if (Number.isFinite(rate) && rate > 0 && rate !== 1) {
      return `USD (${this.simboloMonedaFuncional}${rate.toFixed(2)})`;
    }
    return 'USD';
  }

  get ventaYaEmitida(): boolean {
    return !!this.venta?.dte;
  }

  get permitirEditarTipoCambioVentas(): boolean {
    return !!this.apiService.auth_user()?.empresa?.custom_empresa?.facturacion_fe?.permitir_editar_tipo_cambio
      || !!this.monedaConfig?.permitir_editar;
  }

  get puedeEditarTipoCambio(): boolean {
    return this.tieneMultimoneda
      && this.monedaVenta === 'USD'
      && this.monedaVenta !== this.monedaFuncional
      && this.permitirEditarTipoCambioVentas
      && !this.ventaYaEmitida;
  }

  public abrirModalTipoCambio(template: TemplateRef<any>): void {
    if (!this.puedeEditarTipoCambio) {
      return;
    }
    const actual = parseFloat(this.venta?.exchange_rate);
    this.exchangeRateDraft = (Number.isFinite(actual) && actual > 0 && actual !== 1)
      ? actual
      : (this.tcPreview.rate ?? null);
    this.modalTipoCambioRef = this.modalService.show(template, {
      class: 'modal-sm',
      backdrop: 'static',
    });
  }

  public cerrarModalTipoCambio(): void {
    this.modalTipoCambioRef?.hide();
    this.modalTipoCambioRef = undefined;
  }

  public aplicarTipoCambioManual(): void {
    const rate = parseFloat(String(this.exchangeRateDraft ?? ''));
    if (!Number.isFinite(rate) || rate <= 0) {
      this.alertService.error('Ingrese un tipo de cambio válido mayor a 0.');
      return;
    }
    this.venta.exchange_rate = rate;
    this.sumTotal();
    this.cerrarModalTipoCambio();
    this.cdr.markForCheck();
  }

  public onCurrencyCodeChange(): void {
    if (this.venta.currency_code === this.monedaFuncional) {
      this.venta.exchange_rate = 1;
      this.tcPreview = { rate: 1, date: this.venta.fecha, loading: false, error: null };
      return;
    }
    this.venta.exchange_rate = null;
    this.cargarTipoCambioPreview();
  }

  public onFechaVentaChange(): void {
    if (this.tieneMultimoneda && this.monedaVenta === 'USD' && this.monedaVenta !== this.monedaFuncional && !this.ventaYaEmitida) {
      this.cargarTipoCambioPreview();
    }
  }

  public cargarTipoCambioPreview(): void {
    if (this.ventaYaEmitida) {
      return;
    }
    const fecha = this.venta.fecha || this.apiService.date();
    this.tcPreview = { rate: null, date: fecha, loading: true, error: null };
    this.apiService.getAll('moneda/tipo-cambio', { fecha }).pipe(this.untilDestroyed()).subscribe({
      next: (res: any) => {
        if (res?.moneda_funcional && !this.monedaConfig) {
          this.monedaConfig = {
            moneda_funcional: String(res.moneda_funcional).toUpperCase(),
            monedas_documento: Array.isArray(res.monedas_documento) ? res.monedas_documento : this.monedasDocumento,
            fuente: res.fuente || 'manual',
            permitir_editar: !!res.permitir_editar,
          };
        }
        const rate = res?.rate != null ? parseFloat(res.rate) : null;
        this.tcPreview = { rate, date: res?.date ?? fecha, loading: false, error: null };
        if (!this.permitirEditarTipoCambioVentas) {
          this.venta.exchange_rate = rate;
        } else if (this.venta.exchange_rate == null || this.venta.exchange_rate === '' || parseFloat(this.venta.exchange_rate) === 1) {
          this.venta.exchange_rate = rate;
        }
        this.cdr.markForCheck();
      },
      error: (err: any) => {
        const msg = err?.error?.error || err?.error?.message || 'No hay tipo de cambio disponible para esta fecha.';
        this.tcPreview = { rate: null, date: fecha, loading: false, error: msg };
        if (!this.permitirEditarTipoCambioVentas) {
          this.venta.exchange_rate = null;
        } else if (parseFloat(this.venta.exchange_rate) === 1) {
          this.venta.exchange_rate = null;
        }
        this.cdr.markForCheck();
      },
    });
  }

  /** Total en moneda empresa ÷ TC → dólares a cobrar al cliente. */
  get usdEquivalentTotal(): number | null {
    if (!this.tieneMultimoneda || this.monedaVenta !== 'USD' || this.monedaVenta === this.monedaFuncional) {
      return null;
    }
    const total = parseFloat(this.venta?.total);
    const rate = parseFloat(this.venta?.exchange_rate);
    if (!Number.isFinite(total) || !Number.isFinite(rate) || rate <= 0 || rate === 1) {
      return null;
    }
    return total / rate;
  }

  get bloquearPorMonedaSinTc(): boolean {
    if (!this.tieneMultimoneda || this.ventaYaEmitida || this.monedaVenta !== 'USD' || this.monedaVenta === this.monedaFuncional) {
      return false;
    }
    const rate = parseFloat(this.venta?.exchange_rate);
    return !Number.isFinite(rate) || rate <= 0 || rate === 1;
  }

  get etiquetaReferenciaTc(): string {
    const fuente = this.monedaConfig?.fuente || '';
    if (fuente === 'api') {
      return this.esHondurasFacturacion ? 'Referencia BCH' : 'Referencia API';
    }
    return 'Referencia configurada';
  }

    get esHondurasFacturacion(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_HN;
    }

    private documentoVentaSeleccionado(): any {
        return this.documentos.find((x: any) => x.id == this.venta.id_documento);
    }

    get mostrarCorrelativoHnFormato(): boolean {
        const doc = this.documentoVentaSeleccionado();
        return this.esHondurasFacturacion && !!String(doc?.numero_emision ?? '').trim();
    }

    get correlativoDisplay(): string {
        const doc = this.documentoVentaSeleccionado();
        if (this.mostrarCorrelativoHnFormato) {
            return formatoCorrelativoHn(doc.numero_emision, this.venta.correlativo);
        }
        return String(this.venta.correlativo ?? '');
    }

    public setDocumento(id_documento: any) {
        if (
          id_documento === undefined ||
          id_documento === null ||
          id_documento === ''
        ) {
          return;
        }
        const documento = this.documentos.find(
          (x: any) => x.id == id_documento
        );
        if (!documento) {
          return;
        }
        if (
          this.facturarCotizacion &&
          (documento.nombre === 'Cotización' ||
            documento.nombre === 'Orden de compra')
        ) {
          this.alertService.error(
            'Debe seleccionar un documento fiscal válido para facturar la cotización.'
          );
          return;
        }
        this.venta.nombre_documento = documento.nombre;
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;
        if (this.facturarCotizacion) {
          this.documentoFiscalListo = true;
        }

        if (this.venta.nombre_documento == 'Factura de exportación' && this.esFacturacionElSalvador()) {
            this.apiService.getAll('recintos').pipe(this.untilDestroyed()).subscribe(
                (recintos) => {
                    this.recintos = recintos;
                    this.cdr.markForCheck();
                },
                (error) => {
                    this.alertService.error(error);
                }
            );
            this.apiService.getAll('regimenes').pipe(this.untilDestroyed()).subscribe(
                (regimenes) => {
                    this.regimenes = regimenes;
                    this.cdr.markForCheck();
                },
                (error) => {
                    this.alertService.error(error);
                    this.cdr.markForCheck();
                }
            );
            this.apiService.getAll('incoterms').pipe(this.untilDestroyed()).subscribe(
                (incoterms) => {
                    this.incoterms = incoterms;
                    this.cdr.markForCheck();
                },
                (error) => {
                    this.alertService.error(error);
                    this.cdr.markForCheck();
                }
            );
        }
        if (this.venta.nombre_documento == 'Factura comercial') {
            this.venta.cobrar_impuestos = false;
            this.sumTotal();
        }else{
            this.venta.cobrar_impuestos = true;
            this.sumTotal();
        }
    }

    setIncoterm() {
        this.venta.incoterm = this.incoterms.find(
            (item: any) => item.cod == this.venta.cod_incoterm
        ).nombre;
    }

    // Facturar
    public openModalFacturar(template: TemplateRef<any>) {
        this.openModal(template, {
            class: 'modal-md',
            backdrop: 'static',
        });
    }

  private navegarPostFacturaPreCuenta(ventaId: number) {
    if (!this.preCuentaId) {
      this.alertService.warning('No se pudo vincular la pre-cuenta', 'ID de pre-cuenta no disponible.');
      this.router.navigate(['/restaurante']);
      return;
    }
    this.restauranteService.marcarPreCuentaFacturada(this.preCuentaId, ventaId).subscribe({
      next: (res: any) => {
        const dest = res?.sesion_cerrada ? ['/restaurante'] : (this.sesionId ? ['/restaurante/cuenta', this.sesionId] : ['/restaurante']);
        this.router.navigate(dest);
        this.alertService.success('Factura creada', res?.sesion_cerrada ? 'Pre-cuenta facturada. Mesa liberada.' : 'Pre-cuenta marcada como facturada.');
      },
      error: (err) => {
        const msg = err?.error?.error || err?.error?.message || err?.message || err;
        this.alertService.error(msg ?? 'Error al marcar pre-cuenta como facturada');
        this.router.navigate(['/restaurante']);
      }
    });
  }

  private aplicarPedidoCanalAFactura(data: {
    pedido_id: number;
    cliente_id?: number | null;
    id_sucursal?: number | null;
    id_bodega?: number | null;
    fecha?: string | null;
    canal?: string | null;
    referencia_externa?: string | null;
    observaciones?: string | null;
    detalles: any[];
  }): void {
    this.pedidoCanalId = data.pedido_id;
    if (data.id_sucursal) {
      this.venta.id_sucursal = data.id_sucursal;
    }
    if (data.id_bodega) {
      this.venta.id_bodega = data.id_bodega;
    }
    if (data.fecha) {
      this.venta.fecha = data.fecha;
      this.venta.fecha_pago = data.fecha;
    }
    const partes: string[] = [`Pedido canal #${data.pedido_id}`];
    if (data.canal) {
      partes.push(`Canal: ${data.canal}`);
    }
    if (data.referencia_externa) {
      partes.push(`Ref: ${data.referencia_externa}`);
    }
    if (data.observaciones) {
      partes.push(String(data.observaciones));
    }
    this.venta.observaciones = partes.join('. ');

    const detalles = data.detalles || [];
    if (detalles.length) {
      const iva = this.apiService.auth_user()?.empresa?.iva ?? 0;
      this.venta.detalles = detalles.map((d: any) => {
        const precio = parseFloat(String(d.precio)) || 0;
        const cant = parseFloat(String(d.cantidad)) || 0;
        const descLine = parseFloat(String(d.descuento ?? 0)) || 0;
        const sub = Math.max(0, cant * precio - descLine);
        return {
          id_producto: d.id_producto,
          cantidad: cant,
          precio: precio.toFixed(4),
          descripcion: d.descripcion || '',
          costo: 0,
          descuento: descLine.toFixed(4),
          descuento_porcentaje: 0,
          sub_total: sub.toFixed(4),
          total: sub.toFixed(4),
          tipo_gravado: 'gravada',
          porcentaje_impuesto: iva,
          gravada: 0,
          exenta: 0,
          no_sujeta: 0,
          iva: 0,
        };
      });
      this.normalizarDetallesTipoGravado(this.venta);
      this.sumTotal();
    }

    if (data.cliente_id) {
      this.apiService.read('cliente/', data.cliente_id as number).subscribe({
        next: (c) => this.setCliente(c),
        error: () => {},
      });
    }

    const syncBodegaPedido = (attempt = 0) => {
      if (data.id_bodega) {
        this.venta.id_bodega = data.id_bodega;
      }
      if (this.bodegas?.length && this.venta.id_bodega) {
        this.setBodega();
      } else if (attempt < 40) {
        setTimeout(() => syncBodegaPedido(attempt + 1), 100);
      }
    };
    syncBodegaPedido();
  }

  private navegarPostFacturaPedidoCanal(ventaId: number) {
    if (!this.pedidoCanalId) {
      this.alertService.warning('No se pudo vincular el pedido', 'ID de pedido no disponible.');
      this.router.navigate(['/pedidos']);
      return;
    }
    this.restauranteService.marcarPedidoCanalFacturado(this.pedidoCanalId, ventaId).subscribe({
      next: () => {
        this.router.navigate(['/pedidos']);
        this.alertService.success('Factura creada', 'El pedido quedó marcado como facturado.');
      },
      error: (err) => {
        const msg = err?.error?.error || err?.error?.message || err?.message || err;
        this.alertService.error(msg ?? 'Error al vincular la venta con el pedido');
        this.router.navigate(['/pedidos']);
      },
    });
  }

  public onFacturar() {
    if (this.saving || this.emiting) {
      return;
    }
    if (this.bloquearPorMonedaSinTc) {
      this.alertService.error(
        'No hay tipo de cambio disponible para USD en la fecha indicada. Configure el TC del país o intente más tarde.'
      );
      return;
    }
    if (
      this.facturarCotizacion &&
      (!this.documentoFiscalListo ||
        !this.venta.id_documento ||
        this.venta.nombre_documento === 'Cotización' ||
        this.venta.nombre_documento === 'Orden de compra')
    ) {
      this.alertService.error(
        'Espere a que se asigne el documento fiscal o seleccione un documento válido (Factura o Crédito fiscal).'
      );
      return;
    }

    // Validar que si el método de pago requiere banco, este esté seleccionado
    this.mensajeErrorBanco = '';

    if (this.venta.cotizacion != 1 && this.requiereBanco() && !this.venta.detalle_banco) {
      this.mensajeErrorBanco = 'Debe seleccionar un banco para este método de pago.';
      this.alertService.error('Debe seleccionar un banco para este método de pago.');
      return;
    }

    if (this.tieneDetallesInvalidosParaFacturar()) {
      return;
    }

    if (!this.validarGiftCardAntesFacturar()) {
      return;
    }

    if (this.venta.cobrar_impuestos) {
      const sinImpuestosEnVenta =
        !this.venta.impuestos ||
        !Array.isArray(this.venta.impuestos) ||
        this.venta.impuestos.length === 0;
      if (sinImpuestosEnVenta && this.impuestos?.length > 0) {
        this.venta.impuestos = [...this.impuestos];
      }
      const aunSinImpuestos =
        !this.venta.impuestos ||
        !Array.isArray(this.venta.impuestos) ||
        this.venta.impuestos.length === 0;
      if (aunSinImpuestos) {
        if (!this.impuestosVentaCatalogoCargado) {
          this.alertService.warning(
            'Cargando datos',
            'Espere a que termine de cargar el catálogo de impuestos e intente de nuevo.'
          );
          return;
        }
        if (this.impuestos.length === 0) {
          this.alertService.error(
            this.countryI18n.tax('configureTaxBeforeIncludeVentas')
          );
          return;
        }
        this.venta.impuestos = [...this.impuestos];
      }
    }
    Swal.fire({
      title:
        '¿Confirma procesar la ' +
        (this.venta.cotizacion == 1 ? 'cotización' : 'venta') +
        '?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, procesar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        if (!this.venta.recibido) {
          this.venta.recibido = this.venta.total;
        }

        if (this.venta.forma_pago == 'Wompi' && !this.venta.consigna) {
          this.venta.estado = 'Pendiente';
        }
        aplicarEstadoConsignaEnVenta(this.venta);
        this.onSubmit();
      }
    });
  }

  private tieneDetallesInvalidosParaFacturar(): boolean {
    if (this.venta.id_documento == null || this.venta.id_documento === '') {
      Swal.fire({
        icon: 'warning',
        title: 'Documento requerido',
        text: 'Debe seleccionar un documento para procesar la venta.',
        confirmButtonText: 'Entendido',
      });
      return true;
    }

    const detalles = this.venta.detalles || [];

    const sinCantidad = detalles.find((detalle: any) => {
      const c = Number(detalle?.cantidad);
      return !Number.isFinite(c) || c <= 0;
    });
    if (sinCantidad) {
      const nombre =
        sinCantidad.descripcion || sinCantidad.nombre || 'el producto';
      this.alertService.error(
        `La cantidad de "${nombre}" debe ser mayor que 0 para procesar la venta.`
      );
      return true;
    }

    const totalInvalido = detalles.find((detalle: any) => {
      const t = Number(detalle?.total);
      return !Number.isFinite(t) || t < 0;
    });
    if (totalInvalido) {
      const nombre =
        totalInvalido.descripcion || totalInvalido.nombre || 'el producto';
      this.alertService.error(
        `El total de la línea "${nombre}" debe ser mayor o igual a 0.`
      );
      return true;
    }

    if (esVentaPorConsigna(this.venta)) {
      if (!this.venta.id_cliente) {
        this.alertService.error('Debe seleccionar un cliente para ventas por consigna.');
        return true;
      }
    }

    return false;
  }

  /**
   * Verifica si el método de pago requiere selección de banco
   */
  public requiereBanco(): boolean {
    return this.venta.forma_pago &&
           this.venta.forma_pago !== 'Efectivo' &&
           this.venta.forma_pago !== 'Wompi' &&
           this.venta.forma_pago !== 'Multiple' &&
           !esFormaPagoGiftCard(this.venta.forma_pago);
  }

  public esFormaPagoGiftCard(nombre: string | null | undefined): boolean {
    return esFormaPagoGiftCard(nombre);
  }

  public requiereCodigoGiftCard(): boolean {
    return this.giftCardsActivo && ventaUsaGiftCard(this.venta, this.formaPagos);
  }

  public consultarGiftCard(): void {
    const codigo = (this.venta.codigo_gift_card || '').trim();
    this.giftCardLookupError = '';
    this.giftCardInfo = null;

    if (!codigo) {
      return;
    }

    this.giftCardLookupLoading = true;
    this.giftCardsService.getByCodigo(codigo).subscribe({
      next: (response) => {
        this.giftCardInfo = response.data;
        this.giftCardLookupLoading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.giftCardInfo = null;
        this.giftCardLookupError = error?.error?.message || 'Gift card no encontrada';
        this.giftCardLookupLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  private validarGiftCardAntesFacturar(): boolean {
    if (!this.requiereCodigoGiftCard()) {
      return true;
    }

    const codigo = (this.venta.codigo_gift_card || '').trim();
    if (!codigo) {
      this.alertService.error('Ingrese el código de la gift card.');
      return false;
    }

    if (!this.giftCardInfo || this.giftCardInfo.codigo !== codigo) {
      this.alertService.warning('Gift card', 'Consulte el saldo del código ingresado antes de facturar.');
      return false;
    }

    const montoGift = montoPagoGiftCardVenta(this.venta, this.formaPagos);
    if (this.giftCardInfo.estado !== 'activa') {
      this.alertService.error(`La gift card está ${this.giftCardInfo.estado}.`);
      return false;
    }

    if (this.giftCardInfo.saldo < montoGift) {
      this.alertService.error('Saldo insuficiente en la gift card.');
      return false;
    }

    this.venta.codigo_gift_card = codigo;
    return true;
  }

  private syncGiftCardFieldsAfterPagoChange(): void {
    if (!this.requiereCodigoGiftCard()) {
      this.venta.codigo_gift_card = '';
      this.giftCardInfo = null;
      this.giftCardLookupError = '';
    }
  }

  private verificarGiftCardsActivo(): void {
    this.funcionalidadesService.verificarAcceso('gift-cards').subscribe({
      next: (acceso) => {
        this.giftCardsActivo = acceso;
        this.cdr.markForCheck();
      },
      error: () => {
        this.giftCardsActivo = false;
      },
    });
  }

  // Guardar venta
  public async onSubmit() {
    if (this.saving || this.emiting) {
      return;
    }
    this.saving = true;

    // Si se esta duplicando una venta, esta ya no se marca como recurrente para
    // que no aparezca en las ventas recurrentes
    if (this.duplicarventa) {
      this.venta.recurrente = false;
    }

    if (!this.venta.monto_pago) {
      this.venta.monto_pago = this.venta.efectivo
        ? this.venta.efectivo
        : this.venta.total;
      this.venta.cambio = 0;
    }

    if (this.pedidoCanalId) {
      (this.venta as any).id_pedido_canal = this.pedidoCanalId;
    }

    aplicarEstadoConsignaEnVenta(this.venta);

    // Asegurar que usuarios "Ventas Limitado" siempre tengan ventas al contado
    if (this.apiService.auth_user().tipo === 'Ventas Limitado') {
      this.venta.credito = false;
      this.venta.consigna = false;
    }

    if (this.venta.detalles) {
      this.venta.detalles.forEach((detalle: any) => {
        if (detalle.custom_fields) {
          detalle.custom_fields = detalle.custom_fields.filter((cf: any) =>
            this.selectedCustomFields.includes(cf.custom_field?.id)
          );
        }
      });
    }

    if (!this.venta.detalles || !Array.isArray(this.venta.detalles) || this.venta.detalles.length === 0) {
      this.alertService.warning(
        'Faltan productos',
        'Agregue al menos un producto o servicio en el detalle antes de procesar la venta.'
      );
      this.saving = false;
      this.cdr.markForCheck();
      return;
    }

    const endpointSave = this.venta.cotizacion == 1 ? 'cotizacionVentas' : 'facturacion';
    const pin = await pedirPinDescuentoSiAplica(this.apiService, this.venta);
    if (pin === false) {
      this.saving = false;
      this.cdr.markForCheck();
      return;
    }
    if (pin) {
      this.venta.descuento_autorizacion = pin;
    } else {
      delete this.venta.descuento_autorizacion;
    }
    this.apiService.store(endpointSave, this.venta).subscribe(
      (venta) => {
        // Actualizar siempre la venta local con la respuesta del backend (id, correlativo, etc.)
        // para que en un siguiente guardado se envíe el mismo correlativo.
        const detallesAntes = this.venta.detalles;
        const cotizacionFlag = this.venta.cotizacion;
        Object.assign(this.venta, venta);
        this.venta.cotizacion = cotizacionFlag;
        if (
          (!this.venta.detalles || !Array.isArray(this.venta.detalles) || this.venta.detalles.length === 0) &&
          Array.isArray(detallesAntes) &&
          detallesAntes.length > 0
        ) {
          this.venta.detalles = detallesAntes;
        }

        if (this.venta.cotizacion != 1) {
          this.generarPartidaVentaSiAutomatico(venta);
        }

        if (this.modalRef) {
          this.closeModal();
        }
        this.saving = false;
        this.cdr.markForCheck();

        const giftCards = venta?.gift_cards_emitidas ?? [];
        if (Array.isArray(giftCards) && giftCards.length > 0) {
          this.giftCardsEmitidas = giftCards;
          this.ventaPostFacturaPendiente = venta;
          this.modalGiftCardsRef = this.modalService.show(this.giftCardsEmitidasTemplate, {
            class: 'modal-md',
            backdrop: 'static',
          });
          return;
        }

        this.continuarTrasFacturar(venta);
      },
      (error) => {
        this.alertService.error(error);
        if (this.esErrorRedAmbiguoAlFacturar(error)) {
          const habilitar = confirm(
            'No se confirmó la respuesta del servidor. La venta pudo haberse guardado.\n\n' +
              'Revise el listado de ventas antes de reintentar.\n\n' +
              '¿Desea habilitar de nuevo el botón de facturar?'
          );
          if (habilitar) {
            this.saving = false;
          }
        } else {
          this.saving = false;
        }
        this.cdr.markForCheck();
      }
    );
  }

  public copiarCodigoGiftCard(codigo: string): void {
    if (!codigo || !navigator?.clipboard) {
      return;
    }
    navigator.clipboard.writeText(codigo).then(
      () => this.alertService.success('Copiado', 'Código copiado al portapapeles.'),
      () => this.alertService.warning('Atención', 'No se pudo copiar el código.')
    );
  }

  public continuarTrasGiftCardsEmitidas(): void {
    const venta = this.ventaPostFacturaPendiente;
    this.modalGiftCardsRef?.hide();
    this.modalGiftCardsRef = undefined;
    this.giftCardsEmitidas = [];
    this.ventaPostFacturaPendiente = null;
    if (venta) {
      this.continuarTrasFacturar(venta);
    }
  }

  private continuarTrasFacturar(venta: any): void {
        if (this.venta.cotizacion == 1) {
          this.router.navigate(['/cotizaciones']);
          this.alertService.success(
            'Cotización creada',
            'La cotizacion fue añadida exitosamente.'
          );
        } else if (debeEmitirDteAlFacturar(this.apiService.auth_user()?.empresa)) {
          this.emitirDTE();
        } else {
          if (debeImprimirTrasFacturar(this.apiService.auth_user()?.empresa, this.debeImprimir)) {
            this.imprimir(venta);
          }
          if (this.preCuentaId && this.venta.id) {
            this.navegarPostFacturaPreCuenta(this.venta.id);
          } else if (this.pedidoCanalId && this.venta.id) {
            this.navegarPostFacturaPedidoCanal(this.venta.id);
          } else if (this.debePreguntarEnvioBoxful()) {
            this.preguntarGenerarEnvioBoxful(venta);
          } else {
            this.router.navigate(['/ventas']);
            this.alertService.success(
              'Venta creada',
              'La venta fue añadida exitosamente.'
            );
          }
        }
  }

  private esErrorRedAmbiguoAlFacturar(error: any): boolean {
    const status = error?.status;
    if (status === 0 || status === 502 || status === 503 || status === 504) {
      return true;
    }
    const texto = String(
      error?.message || error?.statusText || error?.name || ''
    ).toLowerCase();
    return (
      texto.includes('timeout') ||
      texto.includes('gateway timeout') ||
      texto.includes('unknown error')
    );
  }

  private generarPartidaVentaSiAutomatico(ventaGuardada: any): void {
    if (this.apiService.auth_user().empresa.generar_partidas !== 'Auto') {
      return;
    }
    this.apiService.store('contabilidad/partida/venta', ventaGuardada).pipe(this.untilDestroyed()).subscribe({
      next: () => { this.cdr.markForCheck(); },
      error: (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  //Limpiar

  public limpiar() {
    if (!debeDispararAtajoTcla('Delete', document.activeElement)) {
      return;
    }
    this.openModal(this.supervisorTemplate, {
      class: 'modal-xs',
    });
  }

  public supervisorCheck() {
    this.loading = true;
    this.apiService.store('usuario-validar', this.supervisor).pipe(this.untilDestroyed()).subscribe(
      (supervisor) => {
        if (this.modalRef) {
          this.closeModal();
        }
        this.cargarDatosIniciales();
        this.loading = false;
        this.supervisor = {};
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );
  }

  // DTE

  public imprimir(venta: any) {
    this.apiService.imprimirFactura(venta.id, 'Impresión', 'width=400');
  }

  emitirDTE() {
    this.emiting = true;
    const ventaPreDte = { ...this.venta };
    this.facturacionElectronica
      .emitirDTE({ ...ventaPreDte })
      .then((venta) => {
        this.venta = { ...ventaPreDte, ...venta };
        if (ventaPreDte.paquetes && !this.venta.paquetes) {
          this.venta.paquetes = ventaPreDte.paquetes;
        }
        if (ventaPreDte.boxful_paquete_stub_id && !this.venta.boxful_paquete_stub_id) {
          this.venta.boxful_paquete_stub_id = ventaPreDte.boxful_paquete_stub_id;
        }
        if (ventaPreDte.id_canal && !this.venta.id_canal) {
          this.venta.id_canal = ventaPreDte.id_canal;
        }
        this.syncVentaCreditoConsignaFlagsFromEstado();
        this.alertService.success(
          this.countryI18n.fe('emitSuccessTitle'),
          this.countryI18n.fe('emitSuccessBody')
        );
        if (this.venta.id_cliente && this.facturacionElectronica.requiereFlujoEnviarDteSeparado()) {
          this.enviarDTE();
        }
        this.emiting = false;

        if (debeImprimirTrasFacturar(this.apiService.auth_user()?.empresa, this.debeImprimir)) {
          this.imprimir(venta);
        }
        if (this.preCuentaId && this.venta.id) {
          this.navegarPostFacturaPreCuenta(this.venta.id);
        } else if (this.pedidoCanalId && this.venta.id) {
          this.navegarPostFacturaPedidoCanal(this.venta.id);
        } else if (this.debePreguntarEnvioBoxful()) {
          this.preguntarGenerarEnvioBoxful(this.venta);
        } else {
          this.router.navigate(['/ventas']);
        }
      })
      .catch((error: any) => {
        this.emiting = false;
        if (error?.venta) {
          this.venta = error.venta;
        }
        const xml = xmlComprobanteDesdeRechazoFeCr(error);
        if (this.esFeCostaRicaFacturacion() && xml) {
          abrirVentanaTextoFeCr(xml, 'application/xml', 'XML comprobante CR');
          this.alertService.info(
            'Depuración FE',
            'Se abrió una ventana con el XML del intento de emisión (sin firma o según respuesta del servidor).'
          );
        }
        const msg = typeof error === 'string' ? error : error?.message ?? error;
        this.alertService.warning('El documento no fue emitido.', msg);
        if (this.debePreguntarEnvioBoxful()) {
          this.preguntarGenerarEnvioBoxful(this.venta);
        } else {
          this.router.navigate(['/ventas']);
        }
        this.cdr.markForCheck();
      });
  }

  enviarDTE() {
    this.sending = true;
    this.apiService.store('enviarDTE', this.venta).pipe(this.untilDestroyed()).subscribe(
      (dte) => {
        this.alertService.success(this.countryI18n.fe('sendSuccessTitle'), this.countryI18n.fe('sendSuccessBody'));
        this.sending = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(this.countryI18n.fe('sendError'));
        this.sending = false;
        this.cdr.markForCheck();
      }
    );
  }

  public setBodega() {
    const bodegaSeleccionada = this.bodegas.find((b: any) => b.id == this.venta.id_bodega);
    if (bodegaSeleccionada) {
      this.venta.id_sucursal = bodegaSeleccionada.id_sucursal;
      this.cargarDocumentos();
    }
  }

  toggleDiv(): void {
    this.opAvanzadas = !this.opAvanzadas; // Cambiar entre true y false
  }
  toggleDivFacturacion(): void {
    this.opAvanzadasFacturacion = !this.opAvanzadasFacturacion; // Cambiar entre true y false
  }

  updateCustomFields() {
    //verificar si hay campos personalizados
    if (this.customFields.data.length === 0) {
      return;
    }
    this.activeCustomFields = this.customFields.data.filter((f: any) =>
      this.selectedCustomFields.includes(f.id)
    );

    // Limpiar campos personalizados que ya no están seleccionados
    if (this.venta.detalles) {
      this.venta.detalles.forEach((detalle: any) => {
        if (detalle.custom_fields) {
          detalle.custom_fields = detalle.custom_fields.filter((cf: any) =>
            this.selectedCustomFields.includes(cf.custom_field?.id)
          );
        }
      });
    }
  }

  public isColumnEnabled(columnName: string): boolean {
    return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
  }

  public verificarAccesoPropina() {
    this.funcionalidadesService.verificarAcceso('cobro-propina').pipe(this.untilDestroyed()).subscribe(
        (acceso) => {
            this.tieneAccesoPropina = acceso;
            this.cdr.markForCheck();
        },
        (error) => {
            console.error('Error al verificar acceso a propina:', error);
            this.tieneAccesoPropina = false;
            this.cdr.markForCheck();
        }
    );
}

  public verificarAccesoMultimoneda(): void {
    this.funcionalidadesService.verificarAcceso('multimoneda').pipe(this.untilDestroyed()).subscribe({
      next: (acceso) => {
        this.tieneMultimoneda = acceso;
        if (acceso) {
          this.inicializarMonedaVenta();
        }
        this.cdr.markForCheck();
      },
      error: () => {
        this.tieneMultimoneda = false;
        this.cdr.markForCheck();
      },
    });
  }

  /** Normaliza detalles: infiere tipo_gravado y sub_total si faltan (ventas existentes). Asegura gravada/exenta/no_sujeta para que el IVA cuadre. */
  /** Adapta payload de cotizacion_ventas al shape que usa FacturacionComponent. */
  private adaptarCotizacionVentaSiAplica(venta: any, keepAsCotizacion: boolean) {
    if (!venta) {
      return venta;
    }
    venta.cotizacion = keepAsCotizacion ? 1 : 0;
    venta.retencion = venta.aplicar_retencion ?? venta.retencion;
    if (Array.isArray(venta.detalles)) {
      venta.detalles = venta.detalles.map((detalle: any) => ({
        ...detalle,
        descripcion: detalle.descripcion || detalle.producto?.nombre || detalle.nombre_producto || '',
        nombre_producto: detalle.nombre_producto || detalle.producto?.nombre || detalle.descripcion || '',
        img: detalle.img || detalle.producto?.img,
        costo: detalle.costo ?? detalle.total_costo ?? detalle.producto?.costo ?? 0,
      }));
    }
    return venta;
  }

  private normalizarDetallesTipoGravado(venta: any) {
    if (!venta?.detalles?.length) return;
    if (this.esFeCostaRicaFacturacion()) {
      migrarExoneracionLegacyUtil(venta);
    }
    const tiposValidos = ['gravada', 'exenta', 'no_sujeta', 'exonerada'];
    venta.detalles.forEach((d: any) => {
      if (d.sub_total == null || d.sub_total === undefined) {
        d.sub_total = Number((parseFloat(d.cantidad) * parseFloat(d.precio)).toFixed(4));
      }
      const totalLinea = parseFloat(d.total) ?? (parseFloat(d.sub_total) - parseFloat(d.descuento || 0));
      if (!d.tipo_gravado) {
        const ex = parseFloat(d.exenta) || 0;
        const no = parseFloat(d.no_sujeta) || 0;
        d.tipo_gravado = ex > 0 ? 'exenta' : (no > 0 ? 'no_sujeta' : 'gravada');
      }
      const tipo = String(d.tipo_gravado).toLowerCase();
      d.tipo_gravado = tiposValidos.includes(tipo) ? tipo : 'gravada';
      d.gravada =
        d.tipo_gravado === 'gravada' || d.tipo_gravado === 'exonerada' ? totalLinea : 0;
      d.exenta = d.tipo_gravado === 'exenta' ? totalLinea : 0;
      d.no_sujeta = d.tipo_gravado === 'no_sujeta' ? totalLinea : 0;
    });
  }

  /**
   * Manejar canje de puntos desde el componente hijo
   */
  public onPuntosCanjeados(datos: {puntos: number, descuento: number}): void {
    this.puntosCanjeados = datos.puntos;
    this.descuentoPuntos = datos.descuento;

    // Actualizar campos de la venta
    this.venta.puntos_canjeados = this.puntosCanjeados;
    this.venta.descuento_puntos = this.descuentoPuntos;

    // Recalcular totales
    this.sumTotal();

    console.log('Puntos canjeados:', {
      puntos: this.puntosCanjeados,
      descuento: this.descuentoPuntos
    });
  }

  /**
   * Resetear información de puntos
   */
  private resetearPuntos(): void {
    this.puntosCanjeados = 0;
    this.descuentoPuntos = 0;
    this.venta.puntos_canjeados = 0;
    this.venta.descuento_puntos = 0;
  }

  /**
   * Obtener ID de empresa
   */
  public getEmpresaId(): number {
    return this.apiService.auth_user().empresa.id;
  }

  /**
   * Abrir PDF del estado de cuenta del cliente en nueva pestaña
   */
  public abrirEstadoCuentaPdf(): void {
    if (!this.venta?.cliente?.id) return;
    const url = `${this.apiService.baseUrl}/api/cliente/estado-de-cuenta/${this.venta.cliente.id}?token=${this.apiService.auth_token()}`;
    window.open(url, '_blank');
  }

  // ==================== MÉTODOS PARA MODAL DE PUNTOS ====================

  /**
   * Cargar puntos del cliente para mostrar en el botón
   */
  private cargarPuntosCliente(): void {
    if (!this.venta.cliente || !this.venta.cliente.id) {
      this.puntosCliente = 0;
      return;
    }

    this.loadingPuntos = true;
    this.fidelizacionService.getPuntosDisponiblesInfo(this.venta.cliente.id, this.getEmpresaId())
      .subscribe({
        next: (response) => {
          if (response.success && response.data) {
            this.puntosCliente = response.data.puntos_disponibles;
          } else {
            this.puntosCliente = 0;
          }
          this.loadingPuntos = false;
        },
        error: (error) => {
          console.error('Error al cargar puntos del cliente:', error);
          this.puntosCliente = 0;
          this.loadingPuntos = false;
        }
      });
  }

  /**
   * Abrir modal de puntos
   */
  public abrirModalPuntos(): void {
    if (!this.venta.cliente || !this.venta.cliente.id) {
      return;
    }

    this.modalPuntosRef = this.modalService.show(this.modalPuntosTemplate, {
      class: 'modal-lg'
    });

    this.cargarDatosModal();
  }

  /**
   * Cerrar modal de puntos
   */
  public cerrarModalPuntos(): void {
    if (this.modalPuntosRef) {
      this.modalPuntosRef.hide();
    }
  }

  /**
   * Cargar datos completos para el modal
   */
  private cargarDatosModal(): void {
    this.loadingModalPuntos = true;
    this.fidelizacionService.getPuntosDisponiblesInfo(this.venta.cliente.id, this.getEmpresaId())
      .subscribe({
        next: (response) => {
          if (response.success && response.data) {
            this.puntosInfoModal = response.data;
            this.configuracionModal = response.data.configuracion || null;
            this.calcularPuntosProximosAExpirarModal();

            // Si ya hay puntos aplicados, cargar los valores actuales
            if (this.puntosCanjeados > 0) {
              this.usarPuntosModal = true;
              this.puntosACanjearModal = this.puntosCanjeados;
            } else {
              this.usarPuntosModal = false;
              this.puntosACanjearModal = 0;
            }
          } else {
            this.puntosInfoModal = null;
            this.configuracionModal = null;
          }
          this.loadingModalPuntos = false;
        },
        error: (error) => {
          console.error('Error al cargar datos del modal:', error);
          this.puntosInfoModal = null;
          this.configuracionModal = null;
          this.loadingModalPuntos = false;
        }
      });
  }

  /**
   * Calcular puntos próximos a expirar para el modal
   */
  private calcularPuntosProximosAExpirarModal(): void {
    if (!this.puntosInfoModal || !this.puntosInfoModal.ganancias_detalle) {
      this.puntosProximosAExpirarModal = [];
      return;
    }

    this.puntosProximosAExpirarModal = this.puntosInfoModal.ganancias_detalle
      .filter(ganancia => ganancia.puntos_disponibles > 0 && ganancia.dias_para_expirar <= 30)
      .sort((a, b) => a.dias_para_expirar - b.dias_para_expirar)
      .slice(0, 5);
  }

  /**
   * Toggle usar puntos en modal
   */
  public onToggleUsarPuntosModal(): void {
    if (!this.usarPuntosModal) {
      this.puntosACanjearModal = 0;
    } else {
      // Establecer el mínimo por defecto
      const minimo = this.configuracionModal?.minimo_canje || 1;
      this.puntosACanjearModal = minimo;
    }
  }

  /**
   * Cambiar puntos a canjear en modal
   */
  public onCambiarPuntosModal(): void {
    if (!this.puntosInfoModal || !this.configuracionModal) return;

    // Validaciones básicas
    if (this.puntosACanjearModal < 0) {
      this.puntosACanjearModal = 0;
    }

    const minimo = this.configuracionModal.minimo_canje || 1;
    const maximo = this.getMaximoCanje();
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles;

    // Validar y corregir si excede puntos disponibles
    if (this.puntosACanjearModal > puntosDisponibles) {
      this.puntosACanjearModal = puntosDisponibles;
      this.alertService.warning('Puntos insuficientes',
        `Solo tienes ${puntosDisponibles} puntos disponibles`);
    }

    // Validar y corregir si excede el máximo permitido
    if (this.puntosACanjearModal > maximo) {
      this.puntosACanjearModal = maximo;
      this.alertService.warning('Límite excedido',
        `El máximo de canje para ${this.configuracionModal.tipo_cliente} es ${maximo} puntos`);
    }

    // Solo mostrar advertencia del mínimo, no corregir automáticamente
    if (this.puntosACanjearModal > 0 && this.puntosACanjearModal < minimo) {
      this.alertService.warning('Cantidad inválida',
        `El mínimo de canje para ${this.configuracionModal.tipo_cliente} es ${minimo} puntos`);
    }
  }

  /**
   * Usar todos los puntos disponibles en modal
   */
  public usarTodosPuntosModal(): void {
    if (!this.puntosInfoModal || !this.configuracionModal) return;

    this.puntosACanjearModal = this.getMaximoCanje();
    this.usarPuntosModal = true;
  }

  /**
   * Calcular descuento total en modal
   */
  public getDescuentoTotalModal(): number {
    if (!this.configuracionModal) return 0;
    return this.puntosACanjearModal * (this.configuracionModal.valor_punto || 0.01);
  }

  /**
   * Aplicar canje desde modal
   */
  public aplicarCanjeModal(): void {
    if (!this.usarPuntosModal || this.puntosACanjearModal <= 0) {
      return;
    }

    // Validar que tenemos la información necesaria
    if (!this.puntosInfoModal || !this.configuracionModal) {
      this.alertService.error('No se pudo cargar la información de puntos');
      return;
    }

    // Validaciones de reglas de negocio
    const minimo = this.configuracionModal.minimo_canje || 1;
    const maximo = this.getMaximoCanje();
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles;

    // Validar mínimo de canje
    if (this.puntosACanjearModal < minimo) {
      this.alertService.warning('Cantidad inválida',
        `El mínimo de canje para ${this.configuracionModal.tipo_cliente} es ${minimo} puntos`);
      return;
    }

    // Validar máximo de canje
    if (this.puntosACanjearModal > maximo) {
      this.alertService.warning('Límite excedido',
        `El máximo de canje para ${this.configuracionModal.tipo_cliente} es ${maximo} puntos`);
      return;
    }

    // Validar puntos disponibles
    if (this.puntosACanjearModal > puntosDisponibles) {
      this.alertService.warning('Puntos insuficientes',
        `Solo tienes ${puntosDisponibles} puntos disponibles`);
      return;
    }

    // Aplicar los valores a la venta
    this.puntosCanjeados = this.puntosACanjearModal;
    this.descuentoPuntos = this.getDescuentoTotalModal();
    this.venta.puntos_canjeados = this.puntosCanjeados;
    this.venta.descuento_puntos = this.descuentoPuntos;

    // Recalcular total
    this.sumTotal();

    // Actualizar botón de puntos
    this.puntosCliente = (this.puntosInfoModal?.puntos_disponibles || 0) - this.puntosCanjeados;

    // Mostrar mensaje de éxito
    this.alertService.success('¡Descuento aplicado!',
      `Se aplicó un descuento de $${this.descuentoPuntos.toFixed(2)} por ${this.puntosCanjeados} puntos`);

    // Mantener el modal abierto para permitir ajustes
  }

  /**
   * Obtener clase CSS para días de expiración
   */
  public getDiasExpiracionClass(dias: number): string {
    if (dias <= 3) return 'text-danger fw-bold';
    if (dias <= 7) return 'text-warning fw-bold';
    if (dias <= 30) return 'text-info';
    return 'text-muted';
  }

  /**
   * Quitar descuento por puntos
   */
  public quitarDescuentoPuntos(): void {
    // Resetear valores
    this.puntosCanjeados = 0;
    this.descuentoPuntos = 0;
    this.venta.puntos_canjeados = 0;
    this.venta.descuento_puntos = 0;

    // Resetear modal
    this.usarPuntosModal = false;
    this.puntosACanjearModal = 0;

    // Recalcular total
    this.sumTotal();

    // Actualizar botón de puntos (recargar puntos disponibles)
    this.cargarPuntosCliente();

    // Mostrar mensaje
    this.alertService.success('Descuento removido', 'El descuento por puntos ha sido eliminado');

    // Cerrar modal
    this.cerrarModalPuntos();
  }

  /**
   * Obtener máximo de canje permitido
   */
  public getMaximoCanje(): number {
    if (!this.configuracionModal || !this.puntosInfoModal) {
      return 0;
    }

    const maximoConfiguracion = this.configuracionModal.maximo_canje || 1000;
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles || 0;

    return Math.min(maximoConfiguracion, puntosDisponibles);
  }

  /**
   * Obtener valor del punto formateado
   */
  public getValorPunto(): string {
    const valor = this.configuracionModal?.valor_punto || 0.01;
    return `$${Number(valor).toFixed(3)}`;
  }

  /**
   * Verificar si el canje es válido para habilitar el botón
   */
  public isCanjeValido(): boolean {
    if (!this.usarPuntosModal || !this.puntosInfoModal || !this.configuracionModal) {
      return false;
    }

    const minimo = this.configuracionModal.minimo_canje || 1;
    const maximo = this.getMaximoCanje();
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles;

    return this.puntosACanjearModal >= minimo &&
           this.puntosACanjearModal <= maximo &&
           this.puntosACanjearModal <= puntosDisponibles &&
           this.puntosACanjearModal > 0;
  }

  /**
   * Formatear números
   */
  public formatNumber(value: number): string {
    return value?.toLocaleString() || '0';
  }

  private verificarFidelizacionHabilitada() {
        this.funcionalidadesService.verificarAcceso('fidelizacion-clientes').subscribe({
            next: (tieneAcceso: boolean) => {
                this.tieneFidelizacionHabilitada = tieneAcceso && this.apiService.isFidelizacionCompleta();
            },
            error: (error) => {
                console.error('Error al verificar acceso a fidelización:', error);
                this.tieneFidelizacionHabilitada = false;
            }
        });
    }

    public getTotalConPropina(): number {
        const total = parseFloat(this.venta?.total || 0);
        const propina = parseFloat(this.venta?.propina || 0);
        return total + propina;
    }

}
