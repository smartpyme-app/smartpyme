import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute, RouterModule } from '@angular/router';
import { CommonModule, DecimalPipe } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { NgSelectModule } from '@ng-select/ng-select';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe } from '@pipes/sum.pipe';
import { FilterPipe } from '@pipes/filter.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import { FE_PAIS_CR, FE_PAIS_HN, FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import { migrarExoneracionCrLegacyADetalles as migrarExoneracionLegacyUtil } from '@shared/modals/fe-cr-exoneracion-detalle/fe-cr-exoneracion-detalle.util';
import {
  formatoCorrelativoHn,
  nombresDocumentosVentaNormales as nombresVentaPorPais,
} from '@views/ventas/documentos/documento-nombre-options';
import { xmlComprobanteDesdeRechazoFeCr } from '@services/facturacion-electronica/fe-cr-http-error.util';
import { abrirVentanaTextoFeCr } from '@services/facturacion-electronica/fe-cr-abrir-xml.util';
import { BuscadorClientesComponent } from '@shared/parts/buscador-clientes/buscador-clientes.component';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { VentaDetallesV2Component } from './detalles/venta-detalles-v2.component';
import { CrearProyectoComponent } from '@shared/modals/crear-proyecto/crear-proyecto.component';
import { MetodosDePagoComponent } from '../facturacion-tienda/metodos-de-pago/metodos-de-pago.component';
import { pedirPinDescuentoSiAplica } from '../venta-descuento-autorizacion.util';
import { FidelizacionService, PuntosDisponiblesInfo, ConfiguracionCliente } from '@services/fidelizacion.service';
import { GiftCardsService, GiftCardLookup } from '@services/gift-cards.service';
import { esFormaPagoGiftCard, montoPagoGiftCardVenta, ventaUsaGiftCard } from '@utils/gift-card.util';
import { aplicarPrefillCredito, prepararVentaParaFacturarCuota } from '@views/ventas/creditos/creditos-facturar';
import { aplicarPlanAVenta, generarPreviewCuotas, planCuadra, restoreSnapshotVenta, snapshotVentaMontos, sumaMontosCuotas, SnapshotMontosVenta, PreviewCuota } from '@views/ventas/creditos/creditos-cuotas';
import { MHService } from '@services/MH.service';
import { RestauranteService } from '@services/restaurante.service';
import Swal from 'sweetalert2';
import { CountryI18nService } from '@services/country-i18n.service';
import {
  acumularImpuestosVentaConCierreResidual,
  calcularMontosLineaDetalle,
  copiarImpuestosProductoAlDetalle,
  esImpuestoIva,
  hidratarImpuestosProductosEnDetalles,
  normalizarPorcentajeImpuestoDetalle,
  prepararDetallesParaFacturarDesdeCotizacion,
  redondearMoneda,
  resolverPorcentajeImpuestoVenta,
  sincronizarTipoGravadoPorCobroIva,
  sumarDescuentoConIvaEncabezadoVenta,
  sumarSubTotalEncabezadoVenta,
  sumarTotalEncabezadoVenta,
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
import * as moment from 'moment';

@Component({
  selector: 'app-facturacion-v2',
  templateUrl: './facturacion-v2.component.html',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    NgSelectModule,
    RouterModule,
    CurrencyPipe,
    DecimalPipe,
    FilterPipe,
    BuscadorClientesComponent,
    CrearClienteComponent,
    CrearProyectoComponent,
    MetodosDePagoComponent,
    VentaDetallesV2Component,
    TranslatePipe,
    SharedModule,
  ],
  providers: [SumPipe],
})
export class FacturacionV2Component implements OnInit {
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
  private impuestosVentaCatalogoCargado = false;
  public recintos: any = [];
  public regimenes: any = [];
  public incoterms: any = [];
  public bancos: any = [];
  public canales: any = [];
  public supervisor: any = {};
  public loading = false;
  public saving = false;
  public sending = false;
  public emiting = false;
  public duplicarventa = false;
  public documentoCreditoBloqueado = false;
  public creditosClientesActivo = false;
  public creditoSnapshot: SnapshotMontosVenta | null = null;
  public planCuotasForm: any = { tipo: 'bien', n_cuotas: 2, fecha_inicio: '', concepto: '' };
  public planCuotasPreview: PreviewCuota[] = [];
  public facturarCotizacion = false;
  public api: boolean = false;
  public tieneAccesoPropina: boolean = false;
  public tieneMultimoneda: boolean = false;
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

  preCuentaId: number | null = null;
  sesionId: number | null = null;
  pedidoCanalId: number | null = null;

  // Información de puntos canjeados
  public puntosCanjeados: number = 0;
  public descuentoPuntos: number = 0;
  public puntosCliente: number = 0;
  public loadingPuntos: boolean = false;
  public loadingModalPuntos: boolean = false;
  public puntosInfoModal: PuntosDisponiblesInfo | null = null;
  public configuracionModal: ConfiguracionCliente | null = null;
  public puntosProximosAExpirarModal: any[] = [];
  public usarPuntosModal: boolean = false;
  public puntosACanjearModal: number = 0;

  modalRef!: BsModalRef;
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

  @ViewChild(VentaDetallesV2Component)
  protected ventaDetallesV2?: VentaDetallesV2Component;

  constructor(
    public apiService: ApiService,
    private facturacionElectronica: FacturacionElectronicaService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private sumPipe: SumPipe,
    private route: ActivatedRoute,
    private router: Router,
    private funcionalidadesService: FuncionalidadesService,
    private restauranteService: RestauranteService,
    private fidelizacionService: FidelizacionService,
    private giftCardsService: GiftCardsService,
    private countryI18n: CountryI18nService,
  ) {
    this.router.routeReuseStrategy.shouldReuseRoute = function () {
      return false;
    };
  }

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
        this.alertService.success('Venta creada', 'Puede generar el envío BoxFul después desde el listado de ventas.');
      }
    });
  }

  private resolverPaqueteStubBoxful(venta: any): any | null {
    const paquetes = venta?.paquetes || this.venta?.paquetes || [];
    const stub = (paquetes as any[]).find((p) =>
      (p.transportista === 'Boxful' || p.transportista === 'boxful')
      && (!p.num_guia || String(p.num_guia).trim() === '' || String(p.num_guia).startsWith('PENDING-'))
    );
    if (stub?.id) return stub;
    if (venta?.boxful_paquete_stub_id || this.venta?.boxful_paquete_stub_id) {
      return { id: venta?.boxful_paquete_stub_id || this.venta?.boxful_paquete_stub_id, peso: 1 };
    }
    if (this.paqueteData?.id) return { id: this.paqueteData.id, peso: this.paqueteData.peso || 1 };
    return null;
  }

  private abrirWizardBoxfulDesdeVenta(venta: any): void {
    if (!venta?.id_cliente) {
      this.alertService.warning('Atención', 'La venta no tiene cliente asignado. Genere el envío después desde el listado de ventas.');
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
      id: stub?.id || null, peso, alto: 11, ancho: 43, largo: 47.5, es_fragil: false,
      parcels: [{ peso, alto: 11, ancho: 43, largo: 47.5, es_fragil: false, contenido: '', valor: parseFloat(venta.total || 50) }]
    };
    this.mostrarModalBoxful = true;
    this.alertService.success('Venta creada', 'Complete los datos del envío BoxFul.');
  }

  onBoxfulGuiaGenerada(guia: any): void {
    const numGuia = guia?.shipmentNumber || guia?.data?.shipmentNumber || '';
    if (numGuia) {
      this.alertService.success('Logística Boxful', `Guía #${numGuia} generada correctamente.`);
    }
  }

  cerrarModalBoxful(): void {
    this.mostrarModalBoxful = false;
    this.boxfulVentaId = null;
    this.boxfulClienteId = null;
    this.boxfulSugerirCod = false;
    this.boxfulMontoCod = null;
    this.boxfulPaqueteData = { peso: 1, alto: 11, ancho: 43, largo: 47.5, es_fragil: false, id: null, parcels: [] };
    this.router.navigate(['/ventas']);
  }

  ngOnInit() {
    this.cargarDatosIniciales();
    this.loadData();
    this.verificarAccesoPropina();
    this.verificarAccesoMultimoneda();
    this.verificarFidelizacionHabilitada();
    this.verificarGiftCardsActivo();
    this.verificarAccesoCreditosClientes();
  }

  public loadData() {
    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        if (this.apiService.auth_user().tipo != 'Administrador') {
          this.sucursales = this.sucursales.filter(
            (item: any) => item.id == this.apiService.auth_user().id_sucursal
          );
        }
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('bodegas/list').subscribe(
      (bodegas) => {
        this.bodegas = bodegas;
        if (this.apiService.auth_user().tipo != 'Administrador') {
          this.bodegas = this.bodegas.filter(
            (item: any) =>
              item.id_sucursal == this.apiService.auth_user().id_sucursal
          );
        }
        // Alinear sucursal con la bodega y recargar documentos (el filtro es por sucursal).
        this.sincronizarSucursalDesdeBodega();
        this.cargarDocumentos();
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('usuarios/list').subscribe(
      (usuarios) => {
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
      (error) => {
        this.alertService.error(error);
      }
    );

    if (this.apiService.isModuloBancos()) {
      this.apiService.getAll('banco/cuentas/list').subscribe(
        (bancos) => { this.bancos = bancos; },
        (error) => { this.alertService.error(error); }
      );
    } else {
      this.apiService.getAll('bancos/list').subscribe(
        (bancos) => { this.bancos = bancos; },
        (error) => { this.alertService.error(error); }
      );
    }

    this.apiService.getAll('formas-de-pago/list').subscribe(
      (formaPagos) => {
        this.formaPagos = formaPagos;
        if (this.apiService.isModuloBancos() && this.venta.forma_pago && this.venta.forma_pago !== 'Efectivo' && this.venta.forma_pago !== 'Wompi' && this.venta.forma_pago !== 'Multiple') {
          const formaPagoSeleccionada = formaPagos.find((fp: any) => fp.nombre === this.venta.forma_pago);
          if (formaPagoSeleccionada?.banco?.nombre_banco && !this.venta.detalle_banco) {
            this.venta.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
          }
        }
      },
      (error) => { this.alertService.error(error); }
    );

    this.apiService.getAll('canales/list').subscribe(
      (canales) => {
        this.canales = canales;
        this.venta.id_canal = this.canales[0].id;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('boxful/status').subscribe({
      next: (res: any) => { this.tieneBoxful = !!(res && res.connected); },
      error: () => { this.tieneBoxful = false; }
    });

    this.apiService.getAll('impuestos').subscribe(
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
      },
      (error) => {
        this.impuestosVentaCatalogoCargado = true;
        this.alertService.error(error);
      }
    );

    // this.apiService.getAll('clientes/list').subscribe(
    //   (clientes) => {
    //     this.clientes = clientes;
    //     this.loading = false;
    //   },
    //   (error) => {
    //     this.alertService.error(error);
    //     this.loading = false;
    //   }
    // );

    this.apiService.getAll('proyectos/list').subscribe(
      (proyectos) => {
        this.proyectos = proyectos;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public cargarDocumentos() {
    const seq = ++this.documentosLoadSeq;
    this.apiService.getAll('documentos/list').subscribe(
      (documentos) => {
        if (seq !== this.documentosLoadSeq) return;
        const idSucursal = this.obtenerIdSucursalDocumentos();
        this.documentosSucursal = (documentos || []).filter(
          (doc: any) => idSucursal != null && Number(doc.id_sucursal) === Number(idSucursal)
        );
        this.aplicarFiltroDocumentosVenta();
      },
      (error) => this.alertService.error(error)
    );
  }

  private obtenerIdSucursalDocumentos(): number | null {
    const bodega = this.bodegas?.find((b: any) => Number(b.id) === Number(this.venta?.id_bodega));
    if (bodega?.id_sucursal != null && bodega.id_sucursal !== '') return Number(bodega.id_sucursal);
    if (this.venta?.id_sucursal != null && this.venta.id_sucursal !== '') return Number(this.venta.id_sucursal);
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
      this.documentos = this.documentosSucursal.filter((x: any) => x.nombre == 'Cotización');
      if (this.documentos.length === 0) this.alertService.error('Debe crear un documento de cotización');
      const documento = this.documentos.find((x: any) => x.nombre == 'Cotización');
      if (documento) {
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;
        this.venta.nombre_documento = documento.nombre;
      }
      return;
    }
    if (esVentaConsignaRemision(this.venta)) {
      this.documentos = this.documentosSucursal.filter((doc: any) => doc.nombre === FACTURA_REMISION);
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
    const docActual = this.documentos.find((x: any) => x.id == this.venta.id_documento);
    if (!docActual) {
      const pred = this.documentos.find((x: any) => x.predeterminado == 1);
      if (pred) this.setDocumento(pred.id);
      else if (this.documentos.length > 0) this.setDocumento(this.documentos[0].id);
      else {
        this.venta.id_documento = null;
        this.venta.correlativo = null;
        this.venta.nombre_documento = undefined;
      }
    } else {
      this.venta.nombre_documento = docActual.nombre;
      if (!this.venta.id || this.venta.correlativo == null || this.venta.correlativo === '') {
        this.venta.correlativo = docActual.correlativo;
      }
    }
  }

  private seleccionarDocumentoRemisionConsigna(): void {
    const documento = this.documentos.find((x: any) => x.nombre === FACTURA_REMISION);
    if (!documento) {
      this.alertService.warning('Consigna', 'No hay un documento "Factura de remisión" configurado para esta sucursal.');
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
    this.migrarExoneracionCrLegacyADetalles();
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
      this.venta.estado = 'Pendiente';
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
        this.venta.observaciones = ((this.venta.observaciones || '') + ' Mesa ' + (navState.preCuentaData.mesa_numero || '')).trim();
        this.venta.detalles = this.mapearDetallesConsumoExterno(detalles);
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

    // Para editar cotizaciones Pre-venta
    if (this.route.snapshot.paramMap.get('id')!) {
      this.apiService
        .read('venta/', +this.route.snapshot.paramMap.get('id')!)
        .subscribe(
          (venta) => {
            this.venta = venta;
            this.retencionIvaGcUsuarioDecidio = true;
            this.normalizarDetallesTipoGravado(this.venta);
            hidratarImpuestosProductosEnDetalles(
              this.venta.detalles,
              this.apiService.auth_user()?.empresa?.iva
            );
            this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
            this.migrarExoneracionCrLegacyADetalles();
            this.sincronizarPreviewMonedaDesdeVenta();
            this.sumTotal();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
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
            this.migrarExoneracionCrLegacyADetalles();
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
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
    }

    // Facturar cotizacion
    if (
      this.route.snapshot.queryParamMap.get('facturar_cotizacion')! &&
      this.route.snapshot.queryParamMap.get('id_venta')!
    ) {
      if (this.apiService.restriccionesCotizacionesVendedoresActivas()) {
        this.alertService.error('No tiene permiso para facturar cotizaciones.');
        this.router.navigate(['/cotizaciones']);
      } else {
        this.facturarCotizacion = true;
        this.apiService
          .read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!)
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
                this.venta.cliente.nombre =
                  this.venta.cliente.tipo == 'Empresa'
                    ? this.venta.cliente.nombre_empresa
                    : this.venta.cliente.nombre_completo;
              }
              this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
              this.migrarExoneracionCrLegacyADetalles();
              this.venta.fecha = this.apiService.date();
              this.venta.fecha_pago = this.apiService.date();
              this.venta.id_documento = null;
              this.venta.correlativo = null;
              this.venta.estado = 'Pagada';
              this.venta.condicion = 'Contado';
              this.venta.impuestos = this.impuestos;
              this.venta.observaciones = '';
              this.venta.cotizacion = 0;
              this.venta.num_cotizacion = this.venta.id;
              this.venta.id = null;
              this.syncVentaCreditoConsignaFlagsFromEstado();
              this.refrescarMonedaTrasResetFecha();

              const indicesExonerada = (this.venta.detalles || [])
                .map((d: any, i: number) => String(d?.tipo_gravado || '').toLowerCase() === 'exonerada' ? i : -1)
                .filter((i: number) => i >= 0);
              prepararDetallesParaFacturarDesdeCotizacion(
                this.venta.detalles,
                !!this.venta.cobrar_impuestos,
                Number(this.apiService.auth_user()?.empresa?.iva ?? 0),
                {
                  preservePrecioIva: true,
                  paisEmpresa: this.apiService.auth_user()?.empresa?.pais,
                }
              );
              indicesExonerada.forEach((i: number) => {
                const detalle = this.venta.detalles[i];
                if (!detalle) {
                  return;
                }
                detalle.tipo_gravado = 'exonerada';
                const precioSinIva = parseFloat(detalle.precio || 0);
                detalle.sub_total = Number((parseFloat(detalle.cantidad || 0) * precioSinIva).toFixed(4));
                detalle.total = (parseFloat(detalle.sub_total) - parseFloat(detalle.descuento || 0)).toFixed(4);
                detalle.gravada = detalle.total;
                detalle.exenta = 0;
                detalle.no_sujeta = 0;
                detalle.total_iva = detalle.total;
              });
              this.reiniciarDocumentoTrasCargarVentaBase();

              this.sumTotal();

              // Para proyectos
              if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
                this.venta.detalles = [];
              }
            },
            (error) => {
              this.alertService.error(error);
              this.loading = false;
            },
          );
      }
    }

    // Facturar orden de compra
    if (this.route.snapshot.queryParamMap.get('facturar_orden_compra')!) {
      this.apiService.read('orden-de-compra/solicitud/', +this.route.snapshot.queryParamMap.get('id_orden_compra')!).subscribe((ordenCompra) => {
        this.venta.num_orden = ordenCompra.id;

        this.apiService.getAll('clientes/buscar/' + (ordenCompra.empresa.dui ?? ordenCompra.empresa.nit)).subscribe((empresa) => {
          if(empresa.length > 0){
            this.setCliente(empresa[0]);
            console.log(empresa);

            // Solo procesar productos si el cliente existe
            this.procesarProductosOrdenCompra(ordenCompra.detalles);
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
            return;
          }
        });
      }, (error) => { this.alertService.error(error); this.loading = false; }
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
      this.apiService.getAll('producto/buscar-by-code/'+ detalleCompra.codigo).subscribe((producto) => {
        if (producto) {
          const detalle: any = {};
          detalle.cantidad = detalleCompra.cantidad;
          detalle.descripcion = producto.nombre;
          detalle.id_producto = producto.id;
          detalle.img = producto.img;
          detalle.tipo = producto.tipo;
          detalle.tipo_gravado = 'gravada';

          detalle.stock = this.resolveStockParaDetalle(producto);

          // En v2, lista sin IVA (como configuración del producto); columna Precio muestra ese valor cuando hay lista
          const ivaEmpresa = this.apiService.auth_user()?.empresa?.iva ?? 0;
          const pctImpuesto = resolverPorcentajeImpuestoVenta(producto.porcentaje_impuesto, ivaEmpresa);
          detalle.porcentaje_impuesto = normalizarPorcentajeImpuestoDetalle(
            producto.porcentaje_impuesto,
            ivaEmpresa
          );
          copiarImpuestosProductoAlDetalle(detalle, producto, ivaEmpresa);

          const precioSinIva = parseFloat(producto.precio);
          const precioConIva = precioSinIva * (1 + pctImpuesto / 100);
          detalle.precio_iva = redondearMoneda(precioConIva).toFixed(2);
          detalle.precio = precioSinIva.toFixed(4);

          detalle.precios = producto.precios
            ? producto.precios.map((p: any) => {
                const sin = parseFloat(p.precio);
                return {
                  ...p,
                  precio: sin.toFixed(4),
                  precio_sin_iva: sin,
                };
              })
            : [];
          detalle.precios.unshift({
            precio: precioSinIva.toFixed(4),
            precio_sin_iva: precioSinIva,
          });

          this.aplicarCoincidenciaListaPreciosOrden(detalle, detalleCompra, pctImpuesto);

          if (detalle.precios?.length) {
            detalle.precios[0] = {
              precio: typeof detalle.precio === 'string' ? detalle.precio : parseFloat(detalle.precio).toFixed(4),
              precio_sin_iva: parseFloat(detalle.precio),
            };
          }

          if (
            this.apiService.auth_user().empresa.valor_inventario == 'promedio' &&
            producto.costo_promedio > 0
          ) {
            detalle.costo = parseFloat(producto.costo_promedio);
          } else {
            detalle.costo = parseFloat(producto.costo);
          }
          detalle.id_vendedor = this.venta.id_vendedor;
          detalle.exenta = 0;
          detalle.no_sujeta = 0;
          detalle.cuenta_a_terceros = 0;
          detalle.descuento = 0;
          detalle.descuento_porcentaje = 0;
          detalle.descuento_monto = 0;
          detalle.descuento_is_monto = false;
          this.aplicarLineaGravadaDesdePrecio(detalle);
          this.venta.detalles.push(detalle);
          this.sumTotal();
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
      });
    });

    // Cita a venta
    if (this.route.snapshot.queryParamMap.get('id_cita')!) {
      this.loading = true;
      this.apiService
        .read('evento/', +this.route.snapshot.queryParamMap.get('id_cita')!)
        .subscribe(
          (evento) => {
            this.evento = evento;
            this.venta.id_cliente = evento.id_cliente;
            this.venta.id_evento = evento.id;

            this.evento.productos.forEach((detalleProducto: any) => {
              this.apiService
                .read('producto/', detalleProducto.id_producto)
                .subscribe(
                  (producto) => {
                    let detalle: any = {};
                    detalle.id_producto = producto.id;
                    detalle.descripcion = producto.nombre;
                    detalle.img = producto.img;
                    // En v2, el precio ya incluye IVA
                    detalle.precio = parseFloat(producto.precio);
                    detalle.costo = parseFloat(producto.costo);
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

                    if (!this.venta.propina) {
                      this.venta.propina = 0;
                    }

                    if (!detalle.gravada) {
                      detalle.gravada = detalle.total;
                    }

                    this.venta.detalles.push(detalle);
                    this.sumTotal();
                    this.loading = false;
                    console.log(this.venta);
                  },
                  (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                  }
                );
            });
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
    }

    this.cargarDocumentos();
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

  /**
   * Calcula el precio sin IVA a partir de un precio con IVA incluido
   */
  private calcularPrecioSinIva(precioConIva: number, porcentajeIva: number): number {
    if (!porcentajeIva || porcentajeIva === 0) {
      return precioConIva;
    }
    return precioConIva / (1 + porcentajeIva / 100);
  }

  /**
   * Calcula el IVA a partir de un precio con IVA incluido
   */
  private calcularIvaDesdePrecioConIva(precioConIva: number, porcentajeIva: number): number {
    if (!porcentajeIva || porcentajeIva === 0) {
      return 0;
    }
    return precioConIva - this.calcularPrecioSinIva(precioConIva, porcentajeIva);
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

    this.apiService.getAll('moneda/config').subscribe({
      next: (cfg: any) => {
        this.monedaConfig = {
          moneda_funcional: String(cfg?.moneda_funcional || 'USD').toUpperCase(),
          monedas_documento: Array.isArray(cfg?.monedas_documento) ? cfg.monedas_documento : [],
          fuente: cfg?.fuente || 'manual',
          permitir_editar: !!cfg?.permitir_editar,
        };
        cargar();
      },
      error: () => {
        this.monedaConfig = {
          moneda_funcional: this.monedaFuncional,
          monedas_documento: this.monedasDocumento,
          fuente: 'manual',
          permitir_editar: true,
        };
        cargar();
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
    this.apiService.getAll('moneda/tipo-cambio', { fecha }).subscribe({
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
      },
      error: (err: any) => {
        const msg = err?.error?.error || err?.error?.message || 'No hay tipo de cambio disponible para esta fecha.';
        this.tcPreview = { rate: null, date: fecha, loading: false, error: msg };
        if (!this.permitirEditarTipoCambioVentas) {
          this.venta.exchange_rate = null;
        } else if (parseFloat(this.venta.exchange_rate) === 1) {
          this.venta.exchange_rate = null;
        }
      },
    });
  }

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

  /** Catálogo MH (incoterm, recinto, régimen) y DTE 11: solo El Salvador. */
  esFacturacionElSalvador(): boolean {
    return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_SV;
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

  /**
   * Ventas antiguas guardaban exoneración en ventas.fe_cr_exoneracion; copiar a cada detalle sin datos propios.
   */
  migrarExoneracionCrLegacyADetalles(): void {
    if (!this.esFeCostaRicaFacturacion()) {
      return;
    }
    migrarExoneracionLegacyUtil(this.venta);
  }

  onCobrarImpuestosChange(): void {
    this.ventaDetallesV2?.sincronizarIvasDetalles();
    this.sumTotal();
  }

  /**
   * Totales por línea (gravada), coherente con VentaDetallesV2 — usado al cargar orden de compra.
   */
  private aplicarLineaGravadaDesdePrecio(detalle: any): void {
    const empresaIva = this.apiService.auth_user()?.empresa?.iva;
    const pctDetalle = this.venta.cobrar_impuestos
      ? resolverPorcentajeImpuestoVenta(detalle?.porcentaje_impuesto, empresaIva)
      : 0;
    const precioSinIva = parseFloat(detalle.precio || 0);

    if (detalle.precio_iva == null || detalle.precio_iva === '') {
      detalle.precio_iva =
        pctDetalle > 0
          ? redondearMoneda(precioSinIva * (1 + pctDetalle / 100)).toFixed(2)
          : redondearMoneda(precioSinIva).toFixed(2);
    }

    calcularMontosLineaDetalle(
      detalle,
      !!this.venta.cobrar_impuestos,
      empresaIva,
      { preservePrecioIva: true }
    );
  }

  /**
   * En la orden de solicitud, `costo` es el precio unitario **con IVA**.
   */
  private precioConIvaReferenciaDesdeOrden(det: any): number | null {
    const desdeCosto = Number(det?.costo);
    return Number.isFinite(desdeCosto) && desdeCosto > 0 ? desdeCosto : null;
  }

  private igualdadPrecioMercado(a: number, b: number): boolean {
    if (!Number.isFinite(a) || !Number.isFinite(b)) {
      return false;
    }
    const diff = Math.abs(a - b);
    return diff <= 0.015 || diff <= 1e-6 * Math.max(Math.abs(a), Math.abs(b), 1);
  }

  /**
   * Compara solo precio unitario CON IVA (solicitud vs columna CON IVA de cada opción);
   * al empatar guarda sin IVA de esa opción.
   */
  private aplicarCoincidenciaListaPreciosOrden(detalle: any, detalleCompra: any, pctImpuesto: number): void {
    const referenciaConIva = this.precioConIvaReferenciaDesdeOrden(detalleCompra);
    const lista = detalle?.precios;
    if (referenciaConIva == null || !Array.isArray(lista) || lista.length === 0) {
      return;
    }
    const pct = Number(pctImpuesto) || 0;
    let mejor: any = null;
    let mejorErr = Infinity;
    for (const p of lista) {
      const sinLista = parseFloat(String(p?.precio));
      if (!Number.isFinite(sinLista)) continue;
      const conLista = pct > 0 ? sinLista * (1 + pct / 100) : sinLista;
      if (!this.igualdadPrecioMercado(referenciaConIva, conLista)) continue;
      const err = Math.abs(referenciaConIva - conLista);
      if (err < mejorErr - 1e-9) {
        mejorErr = err;
        mejor = p;
      }
    }
    if (mejor == null || !Number.isFinite(mejorErr) || mejorErr > 0.015) return;

    const sinSel =
      mejor.precio_sin_iva !== undefined &&
      mejor.precio_sin_iva !== null &&
      mejor.precio_sin_iva !== ''
        ? Number(mejor.precio_sin_iva)
        : parseFloat(String(mejor.precio));
    if (!Number.isFinite(sinSel)) return;
    detalle.precio = sinSel.toFixed(4);
    detalle.precio_iva = pct > 0
      ? redondearMoneda(sinSel * (1 + pct / 100)).toFixed(2)
      : redondearMoneda(sinSel).toFixed(2);
  }

  /** Stock desde producto ya cargado por bodega (misma regla que el buscador v2). */
  private resolveStockParaDetalle(producto: any): number | null {
    if (!producto) {
      return null;
    }
    if (producto.tipo == 'Compuesto' && producto.composiciones) {
      producto.composiciones.forEach((composicion: any) => {
        if (!composicion.compuesto?.inventarios) {
          return;
        }
        composicion.compuesto.inventarios = composicion.compuesto.inventarios.filter(
          (item: any) => item.id_bodega == this.venta.id_bodega,
        );
        const stockComp = parseFloat(this.sumPipe.transform(composicion.compuesto.inventarios, 'stock'));
        if (stockComp < composicion.cantidad) {
          producto.inventarios = [];
        }
      });
    }

    producto.inventarios = producto.inventarios?.filter((item: any) => item.id_bodega == this.venta.id_bodega) || [];
    if (producto.inventario_por_lotes && producto.lotes?.length > 0) {
      const lotesBodega = this.venta.id_bodega
        ? producto.lotes.filter((l: any) => l.id_bodega == this.venta.id_bodega)
        : producto.lotes;
      return lotesBodega.reduce((sum: number, lote: any) => sum + (parseFloat(lote.stock) || 0), 0);
    }
    if (producto.tipo != 'Servicio' && producto.inventarios.length > 0) {
      return parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
    }
    return null;
  }

  public sumTotal() {
    // Asegurar que detalles existe y es un array
    if (!this.venta.detalles || !Array.isArray(this.venta.detalles)) {
      this.venta.detalles = [];
    }

    // Asegurar que impuestos existe y es un array
    if (!this.venta.impuestos || !Array.isArray(this.venta.impuestos)) {
      this.venta.impuestos = [];
    }

    if (
      this.venta.cobrar_impuestos &&
      this.venta.impuestos.length === 0 &&
      this.impuestos?.length > 0
    ) {
      this.venta.impuestos = [...this.impuestos];
    }

    const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
    this.venta.detalles.forEach((d: any) => {
      if (String(d?.tipo_gravado || '').toLowerCase() === 'exonerada') {
        return;
      }
      calcularMontosLineaDetalle(d, !!this.venta.cobrar_impuestos, empresaIva, {
        preservePrecioIva: true,
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
    const paisEmpresa = this.apiService.auth_user()?.empresa?.pais;
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

    // Si el catálogo llegó tarde (pedido/pre-cuenta), asignar impuestos y recalcular montos.
    if (
      this.venta.cobrar_impuestos &&
      (!Array.isArray(this.venta.impuestos) || this.venta.impuestos.length === 0) &&
      Array.isArray(this.impuestos) &&
      this.impuestos.length > 0
    ) {
      this.venta.impuestos = this.impuestos;
      const ivaRecalc = acumularImpuestosVentaConCierreResidual(
        this.venta.impuestos,
        this.venta.detalles,
        true,
        empresaIva,
        paisEmpresa,
        descuentoPuntos
      );
      this.venta.iva = ivaRecalc.toFixed(4);
    }

    const rawDescuento = parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'));
    this.venta.descuento = Number(rawDescuento).toFixed(4);
    // Mostrar descuento en términos del precio con IVA (campo Precio / $ descuento).
    this.venta.descuento_con_iva = sumarDescuentoConIvaEncabezadoVenta(
      this.venta.detalles,
      empresaIva,
      !!this.venta.cobrar_impuestos
    ).toFixed(4);
    const rawTotalCosto = parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'));
    this.venta.total_costo = Number(rawTotalCosto).toFixed(4);

    // Total: suma de líneas con IVA (redondeo por línea) + tributos especiales (turismo, etc.),
    // estos últimos se mantienen aunque el IVA esté apagado.
    const totalNum = sumarTotalEncabezadoVenta(
      this.venta.detalles,
      this.venta.impuestos,
      {
        empresaIva,
        cobrarImpuestos: !!this.venta.cobrar_impuestos,
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

    onAlMenosUnPaqueteCuentaTerceros(): void {
      this.habilitarCuentaTerceros = true;
      this.sumTotal();
    }

    private montoMinimoRetencionIvaGc(): number {
        const v = this.apiService.auth_user()?.empresa?.monto_minimo_retencion_iva_gc;
        const n = parseFloat(v);
        return !isNaN(n) && n >= 0 ? n : 100;
    }

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

    public onRetencionIvaManualChange(): void {
        this.retencionIvaGcUsuarioDecidio = true;
        this.sumTotal();
    }

    // Cliente
    private cargarPrefillCreditoCuota(): void {
        const idCuota = this.route.snapshot.queryParamMap.get('credito_cuota');
        if (!idCuota) {
            return;
        }
        this.apiService.get('creditos-clientes/cuotas/' + idCuota + '/prefill').subscribe({
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
                        return;
                    }
                    if (attempt < 40) {
                        setTimeout(() => applyDoc(attempt + 1), 100);
                    }
                };
                applyDoc();
            },
            error: (err) => this.alertService.error(err),
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
        this.apiService.read('venta/', idVenta).subscribe({
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
                this.migrarExoneracionCrLegacyADetalles();
                this.sumTotal();
            },
            error: (err) => {
                this.alertService.error(err);
                this.loading = false;
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

            // Resetear y cargar puntos del cliente (si fidelización habilitada)
            this.resetearPuntos();
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

            // Obtener saldo pendiente si el cliente tiene límite de crédito
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
            this.puntosCliente = 0;
            this.resetearPuntos();
        }
        console.log(cliente);
    }

    /**
     * Abrir PDF del estado de cuenta del cliente en nueva pestaña
     */
    public abrirEstadoCuentaPdf(): void {
        if (!this.venta?.cliente?.id) return;
        const url = `${this.apiService.baseUrl}/api/cliente/estado-de-cuenta/${this.venta.cliente.id}?token=${this.apiService.auth_token()}`;
        window.open(url, '_blank');
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
            this.venta.fecha_pago = moment().add(1, 'month').format('YYYY-MM-DD');
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
        this.funcionalidadesService.verificarAcceso('creditos-clientes').subscribe({
            next: (acceso) => { this.creditosClientesActivo = acceso; },
            error: () => { this.creditosClientesActivo = false; },
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
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public confirmarPlanCuotas(): void {
        if (!this.creditoSnapshot || !this.planCuotasCuadra) {
            this.alertService.error('La suma de las cuotas debe coincidir con el monto del contrato.');
            return;
        }
        aplicarPlanAVenta(this.venta, { ...this.planCuotasForm, cuotas: this.planCuotasPreview }, this.creditoSnapshot);
        this.sumTotal();
        this.modalRef?.hide();
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
                this.alertService.warning('Consigna', 'Se vació el detalle. Toda la factura quedará en consigna; no puede mezclar líneas de venta normal y consigna.');
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

        // Si módulo bancos: asignar banco por defecto del método de pago
        if (this.apiService.isModuloBancos() && this.requiereBanco()) {
            const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.venta.forma_pago);
            if (formaPagoSeleccionada?.banco?.nombre_banco) {
                this.venta.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else {
                this.venta.detalle_banco = '';
            }
        } else if (!this.requiereBanco()) {
            this.venta.detalle_banco = '';
            this.mensajeErrorBanco = '';
        }
        this.actualizarCambioEfectivo();
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
        this.venta.nombre_documento = documento.nombre;
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;

        if (this.venta.nombre_documento == 'Factura de exportación' && this.esFacturacionElSalvador()) {
            this.apiService.getAll('recintos').subscribe(
                (recintos) => {
                    this.recintos = recintos;
                },
                (error) => {
                    this.alertService.error(error);
                }
            );
            this.apiService.getAll('regimenes').subscribe(
                (regimenes) => {
                    this.regimenes = regimenes;
                },
                (error) => {
                    this.alertService.error(error);
                }
            );
            this.apiService.getAll('incoterms').subscribe(
                (incoterms) => {
                    this.incoterms = incoterms;
                },
                (error) => {
                    this.alertService.error(error);
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
        this.modalRef = this.modalService.show(template, {
            class: 'modal-md',
            backdrop: 'static',
        });
    }

  public onFacturar() {
    if (this.bloquearPorMonedaSinTc) {
      this.alertService.error(
        'No hay tipo de cambio disponible para USD en la fecha indicada. Configure el TC del país o intente más tarde.'
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

    if ((this.venta.credito || this.venta.estado === 'Pendiente' || this.venta.forma_pago === 'Crédito') && !this.venta.id_cliente) {
      this.alertService.error('El cliente es requerido para los créditos y la facturación.');
      return;
    }

    if (!this.validarGiftCardAntesFacturar()) {
      return;
    }

    if (this.venta.cobrar_impuestos) {
      const vacios =
        !this.venta.impuestos ||
        !Array.isArray(this.venta.impuestos) ||
        this.venta.impuestos.length === 0;
      if (vacios && this.impuestos?.length > 0) {
        this.venta.impuestos = [...this.impuestos];
      }
      const aunVacios =
        !this.venta.impuestos ||
        !Array.isArray(this.venta.impuestos) ||
        this.venta.impuestos.length === 0;
      if (aunVacios) {
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

    if (
      confirm(
        '¿Confirma procesar la ' +
          (this.venta.cotizacion == 1 ? ' cotización.' : 'venta.')
      )
    ) {
      if (!this.venta.recibido) this.venta.recibido = this.venta.total;

      if (this.venta.forma_pago == 'Wompi' && !this.venta.consigna) {
        this.venta.estado = 'Pendiente';
      }
      aplicarEstadoConsignaEnVenta(this.venta);
      this.onSubmit();
    }
  }

  private tieneDetallesInvalidosParaFacturar(): boolean {
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

    return false;
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
      this.venta.detalles = this.mapearDetallesConsumoExterno(detalles);
      if (
        (!Array.isArray(this.venta.impuestos) || this.venta.impuestos.length === 0) &&
        Array.isArray(this.impuestos) &&
        this.impuestos.length > 0
      ) {
        this.venta.impuestos = this.impuestos;
      }
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
      },
      error: (error) => {
        this.giftCardInfo = null;
        this.giftCardLookupError = error?.error?.message || 'Gift card no encontrada';
        this.giftCardLookupLoading = false;
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
      next: (acceso) => { this.giftCardsActivo = acceso; },
      error: () => { this.giftCardsActivo = false; },
    });
  }

  // Guardar venta
  public async onSubmit() {
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

    const endpointSave = this.venta.cotizacion == 1 ? 'cotizacionVentas' : 'facturacion';
    const pin = await pedirPinDescuentoSiAplica(this.apiService, this.venta);
    if (pin === false) {
      this.saving = false;
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
        Object.assign(this.venta, venta);
        if (
          (!this.venta.detalles || !Array.isArray(this.venta.detalles) || this.venta.detalles.length === 0) &&
          Array.isArray(detallesAntes) &&
          detallesAntes.length > 0
        ) {
          this.venta.detalles = detallesAntes;
        }

        // Si es cotización
        if (this.facturarCotizacion) {
          this.apiService
            .read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!)
            .subscribe(
              (venta) => {
                venta.estado = 'Facturada';
                this.apiService.store('venta', venta).subscribe(
                  (venta) => {},
                  (error) => {
                    this.alertService.error(error);
                    this.saving = false;
                  }
                );
              },
              (error) => {
                this.alertService.error(error);
                this.saving = false;
              }
            );
        }

        if (this.modalRef) {
          this.modalRef.hide();
        }
        this.saving = false;

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
        this.saving = false;
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
              'Venta creado',
              'La venta fue añadida exitosamente.'
            );
          }
        }
  }

  //Limpiar

  public limpiar() {
    if (!debeDispararAtajoTcla('Delete', document.activeElement)) {
      return;
    }
    this.modalRef = this.modalService.show(this.supervisorTemplate, {
      class: 'modal-xs',
    });
  }

  public supervisorCheck() {
    this.loading = true;
    this.apiService.store('usuario-validar', this.supervisor).subscribe(
      (supervisor) => {
        this.modalRef.hide();
        this.cargarDatosIniciales();
        this.loading = false;
        this.supervisor = {};
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
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
      });
  }

  enviarDTE() {
    this.sending = true;
    this.apiService.store('enviarDTE', this.venta).subscribe(
      (dte) => {
        this.alertService.success(this.countryI18n.fe('sendSuccessTitle'), this.countryI18n.fe('sendSuccessBody'));
        this.sending = false;
      },
      (error) => {
        this.alertService.error(this.countryI18n.fe('sendError'));
        this.sending = false;
      }
    );
  }

  public setBodega() {

    let bodegaSeleccionada = this.bodegas.find((b: any) => b.id == this.venta.id_bodega);
   // console.log("bodega", bodegaSeleccionada);
    this.venta.id_sucursal = bodegaSeleccionada.id_sucursal;

    if (bodegaSeleccionada) {
     // console.log("bodegaSeleccionada", bodegaSeleccionada);
      this.venta.id_sucursal = bodegaSeleccionada.id_sucursal;

      this.apiService.getAll('documentos/list').subscribe(
        (documentos) => {
          this.documentos = documentos.filter(
            (x: any) => x.id_sucursal == this.venta.id_sucursal
          );

          if (this.venta.cotizacion == 1) {
            this.documentos = this.documentos.filter(
              (x: any) => x.nombre == 'Cotización'
            );
          } else {
            this.documentos = this.documentos.filter(
              (x: any) =>
                x.nombre !== 'Cotización' && x.nombre !== 'Orden de compra'
            );
          }

          let documentoPredeterminado = this.documentos.find(
            (x: any) => x.predeterminado == 1
          );
          if (documentoPredeterminado) {
            this.setDocumento(documentoPredeterminado.id);
          } else if (this.documentos.length > 0) {
            this.setDocumento(this.documentos[0].id);
          }
        },
        (error) => {
          this.alertService.error(error);
        }
      );
    }
  }

  public isColumnEnabled(columnName: string): boolean {
    return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
  }

  /** Detalles desde pre-cuenta restaurante o pedido canal: precios sin IVA, IVA desglosado en línea (v2). */
  private mapearDetallesConsumoExterno(detalles: any[]): any[] {
    const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
    return detalles.map((d: any) => {
      const cant = parseFloat(String(d.cantidad)) || 0;
      const descLine = parseFloat(String(d.descuento ?? 0)) || 0;
      const pctNum = resolverPorcentajeImpuestoVenta(d.porcentaje_impuesto, empresaIva);
      let precioSinIva = parseFloat(String(d.precio)) || 0;
      let precioConIva = d.precio_con_iva != null && d.precio_con_iva !== ''
        ? parseFloat(String(d.precio_con_iva))
        : (pctNum > 0 ? precioSinIva * (1 + pctNum / 100) : precioSinIva);

      if (d.precios_sin_iva === false && pctNum > 0) {
        precioConIva = precioSinIva;
        precioSinIva = this.calcularPrecioSinIva(precioConIva, pctNum);
      }

      const detalle: any = {
        id_producto: d.id_producto,
        id_presentacion: d.id_presentacion ?? null,
        cantidad: cant,
        precio: precioSinIva.toFixed(4),
        precio_iva: redondearMoneda(precioConIva).toFixed(2),
        descripcion: d.descripcion || '',
        costo: 0,
        descuento: descLine.toFixed(4),
        descuento_porcentaje: 0,
        tipo_gravado: 'gravada',
        porcentaje_impuesto: pctNum,
        exenta: 0,
        no_sujeta: 0,
        cuenta_a_terceros: 0,
      };
      this.aplicarLineaGravadaDesdePrecio(detalle);
      return detalle;
    });
  }

  /** Normaliza detalles: infiere tipo_gravado y sub_total si faltan (ventas existentes). Asegura gravada/exenta/no_sujeta para que el IVA cuadre. */
  private normalizarDetallesTipoGravado(venta: any) {
    if (!venta?.detalles?.length) return;
    const tiposValidos = ['gravada', 'exenta', 'no_sujeta', 'exonerada'];
    venta.detalles.forEach((d: any) => {
      if (d.sub_total == null || d.sub_total === undefined) {
        const precio = parseFloat(d.precio) || 0;
        d.sub_total = Number((parseFloat(d.cantidad) * precio).toFixed(4));
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

  public verificarAccesoPropina() {
    this.funcionalidadesService.verificarAcceso('cobro-propina').subscribe(
        (acceso) => {
            this.tieneAccesoPropina = acceso;
        },
        (error) => {
            console.error('Error al verificar acceso a propina:', error);
            this.tieneAccesoPropina = false;
        }
    );
}

  public verificarAccesoMultimoneda(): void {
    this.funcionalidadesService.verificarAcceso('multimoneda').subscribe({
      next: (acceso) => {
        this.tieneMultimoneda = acceso;
        if (acceso) {
          this.inicializarMonedaVenta();
        }
      },
      error: () => {
        this.tieneMultimoneda = false;
      },
    });
  }

public getTotalConPropina(): number {
    const total = parseFloat(this.venta?.total || 0);
    const propina = parseFloat(this.venta?.propina || 0);
    return total + propina;
}

  // ==================== FIDELIZACIÓN - PUNTOS ====================

  private resetearPuntos(): void {
    this.puntosCanjeados = 0;
    this.descuentoPuntos = 0;
    this.venta.puntos_canjeados = 0;
    this.venta.descuento_puntos = 0;
  }

  public getEmpresaId(): number {
    return this.apiService.auth_user().empresa.id;
  }

  private cargarPuntosCliente(): void {
    if (!this.venta.cliente?.id) {
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
        error: () => {
          this.puntosCliente = 0;
          this.loadingPuntos = false;
        }
      });
  }

  public abrirModalPuntos(): void {
    if (!this.venta.cliente?.id) return;
    this.modalPuntosRef = this.modalService.show(this.modalPuntosTemplate, { class: 'modal-lg' });
    this.cargarDatosModal();
  }

  public cerrarModalPuntos(): void {
    this.modalPuntosRef?.hide();
  }

  private cargarDatosModal(): void {
    this.loadingModalPuntos = true;
    this.fidelizacionService.getPuntosDisponiblesInfo(this.venta.cliente.id, this.getEmpresaId())
      .subscribe({
        next: (response) => {
          if (response.success && response.data) {
            this.puntosInfoModal = response.data;
            this.configuracionModal = response.data.configuracion || null;
            this.calcularPuntosProximosAExpirarModal();
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
        error: () => {
          this.puntosInfoModal = null;
          this.configuracionModal = null;
          this.loadingModalPuntos = false;
        }
      });
  }

  private calcularPuntosProximosAExpirarModal(): void {
    if (!this.puntosInfoModal?.ganancias_detalle) {
      this.puntosProximosAExpirarModal = [];
      return;
    }
    this.puntosProximosAExpirarModal = this.puntosInfoModal.ganancias_detalle
      .filter((g: any) => g.puntos_disponibles > 0 && g.dias_para_expirar <= 30)
      .sort((a: any, b: any) => a.dias_para_expirar - b.dias_para_expirar)
      .slice(0, 5);
  }

  public onToggleUsarPuntosModal(): void {
    if (!this.usarPuntosModal) {
      this.puntosACanjearModal = 0;
    } else {
      this.puntosACanjearModal = this.configuracionModal?.minimo_canje || 1;
    }
  }

  public onCambiarPuntosModal(): void {
    if (!this.puntosInfoModal || !this.configuracionModal) return;
    if (this.puntosACanjearModal < 0) this.puntosACanjearModal = 0;
    const minimo = this.configuracionModal.minimo_canje || 1;
    const maximo = this.getMaximoCanje();
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles;
    if (this.puntosACanjearModal > puntosDisponibles) {
      this.puntosACanjearModal = puntosDisponibles;
      this.alertService.warning('Puntos insuficientes', `Solo tienes ${puntosDisponibles} puntos disponibles`);
    }
    if (this.puntosACanjearModal > maximo) {
      this.puntosACanjearModal = maximo;
      this.alertService.warning('Límite excedido', `El máximo de canje para ${this.configuracionModal.tipo_cliente} es ${maximo} puntos`);
    }
    if (this.puntosACanjearModal > 0 && this.puntosACanjearModal < minimo) {
      this.alertService.warning('Cantidad inválida', `El mínimo de canje para ${this.configuracionModal.tipo_cliente} es ${minimo} puntos`);
    }
  }

  public usarTodosPuntosModal(): void {
    if (!this.puntosInfoModal || !this.configuracionModal) return;
    this.puntosACanjearModal = this.getMaximoCanje();
    this.usarPuntosModal = true;
  }

  public getDescuentoTotalModal(): number {
    if (!this.configuracionModal) return 0;
    return this.puntosACanjearModal * (this.configuracionModal.valor_punto || 0.01);
  }

  public aplicarCanjeModal(): void {
    if (!this.usarPuntosModal || this.puntosACanjearModal <= 0) return;
    if (!this.puntosInfoModal || !this.configuracionModal) {
      this.alertService.error('No se pudo cargar la información de puntos');
      return;
    }
    const minimo = this.configuracionModal.minimo_canje || 1;
    const maximo = this.getMaximoCanje();
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles;
    if (this.puntosACanjearModal < minimo) {
      this.alertService.warning('Cantidad inválida', `El mínimo de canje para ${this.configuracionModal.tipo_cliente} es ${minimo} puntos`);
      return;
    }
    if (this.puntosACanjearModal > maximo) {
      this.alertService.warning('Límite excedido', `El máximo de canje para ${this.configuracionModal.tipo_cliente} es ${maximo} puntos`);
      return;
    }
    if (this.puntosACanjearModal > puntosDisponibles) {
      this.alertService.warning('Puntos insuficientes', `Solo tienes ${puntosDisponibles} puntos disponibles`);
      return;
    }
    this.puntosCanjeados = this.puntosACanjearModal;
    this.descuentoPuntos = this.getDescuentoTotalModal();
    this.venta.puntos_canjeados = this.puntosCanjeados;
    this.venta.descuento_puntos = this.descuentoPuntos;
    this.sumTotal();
    this.puntosCliente = (this.puntosInfoModal?.puntos_disponibles || 0) - this.puntosCanjeados;
    this.alertService.success('¡Descuento aplicado!', `Se aplicó un descuento de $${this.descuentoPuntos.toFixed(2)} por ${this.puntosCanjeados} puntos`);
  }

  public getDiasExpiracionClass(dias: number): string {
    if (dias <= 3) return 'text-danger fw-bold';
    if (dias <= 7) return 'text-warning fw-bold';
    if (dias <= 30) return 'text-info';
    return 'text-muted';
  }

  public quitarDescuentoPuntos(): void {
    this.resetearPuntos();
    this.usarPuntosModal = false;
    this.puntosACanjearModal = 0;
    this.sumTotal();
    this.cargarPuntosCliente();
    this.alertService.success('Descuento removido', 'El descuento por puntos ha sido eliminado');
    this.cerrarModalPuntos();
  }

  public getMaximoCanje(): number {
    if (!this.configuracionModal || !this.puntosInfoModal) return 0;
    const maximoConfiguracion = this.configuracionModal.maximo_canje || 1000;
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles || 0;
    return Math.min(maximoConfiguracion, puntosDisponibles);
  }

  public getValorPunto(): string {
    const valor = this.configuracionModal?.valor_punto || 0.01;
    return `$${Number(valor).toFixed(3)}`;
  }

  public isCanjeValido(): boolean {
    if (!this.usarPuntosModal || !this.puntosInfoModal || !this.configuracionModal) return false;
    const minimo = this.configuracionModal.minimo_canje || 1;
    const maximo = this.getMaximoCanje();
    const puntosDisponibles = this.puntosInfoModal.puntos_disponibles;
    return this.puntosACanjearModal >= minimo &&
      this.puntosACanjearModal <= maximo &&
      this.puntosACanjearModal <= puntosDisponibles &&
      this.puntosACanjearModal > 0;
  }

  public formatNumber(value: number): string {
    return value?.toLocaleString() || '0';
  }

  private verificarFidelizacionHabilitada(): void {
    this.funcionalidadesService.verificarAcceso('fidelizacion-clientes').subscribe({
      next: (tieneAcceso: boolean) => {
        this.tieneFidelizacionHabilitada = tieneAcceso && this.apiService.isFidelizacionCompleta();
      },
      error: () => { this.tieneFidelizacionHabilitada = false; }
    });
  }
}

