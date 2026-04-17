import { Component, OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import { FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
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
import Swal from 'sweetalert2';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

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
        LazyImageDirective
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
  public formaPagos: any = [];
  public sucursales: any = [];
  public bodegas: any = [];
  public impuestos: any = [];
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
  public facturarCotizacion = false;
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
  public tieneFidelizacionHabilitada: boolean = false;
  public mensajeValidacionFecha: string = '';
  public mensajeErrorBanco: string = '';
  public contabilidadHabilitada: boolean = false;

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

  @ViewChild('msupervisor')
  public supervisorTemplate!: TemplateRef<any>;

  @ViewChild('modalPuntos')
  public modalPuntosTemplate!: TemplateRef<any>;

  @ViewChild('mcredito')
  public creditoTemplate!: TemplateRef<any>;

  private cdr = inject(ChangeDetectorRef);

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
    private restauranteService: RestauranteService
  ) {
    super(modalManager, alertService);
    this.router.routeReuseStrategy.shouldReuseRoute = function () {
      return false;
    };
  }

  ngOnInit() {
    this.cargarDatosIniciales();
    this.verificarAccesoContabilidad();
    this.loadData();
    this.verificarAccesoPropina();
    this.verificarFidelizacionHabilitada();
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
        this.cdr.markForCheck();
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

    this.apiService.getAll('impuestos').pipe(this.untilDestroyed()).subscribe(
      (impuestos) => {
        // Filtrar solo los impuestos que aplican a ventas
        this.impuestos = impuestos.filter((impuesto: any) => impuesto.aplica_ventas !== false && impuesto.aplica_ventas !== 0);
        // Al editar cotización/venta no sobrescribir impuestos para no volver a agregarlos
        const esEdicion = !!this.route.snapshot.paramMap.get('id');
        if (!esEdicion && (!this.venta.impuestos || this.venta.iva == 0)) {
          this.venta.impuestos = this.impuestos;
          this.sumTotal();
        }
        this.cdr.markForCheck();
      },
      (error) => {
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
    this.apiService.getAll('documentos/list').pipe(this.untilDestroyed()).subscribe(
      (documentos) => {
        this.documentos = documentos;
        this.documentos = this.documentos.filter(
          (doc: any) => doc.id_sucursal == this.venta.id_sucursal
        );
        if (!this.venta.id_documento && !this.venta.correlativo) {
          let documento = this.documentos.find(
            (x: any) => x.predeterminado == 1
          );
          if (documento) {
            this.venta.id_documento = documento.id;
            this.venta.correlativo = documento.correlativo;
          } else {
            this.venta.id_documento = documentos[0].id;
            this.venta.correlativo = documentos[0].correlativo;
          }

          if (this.venta.cotizacion == 1) {
            this.documentos = this.documentos;
            this.documentos = this.documentos.filter(
              (x: any) => x.nombre == 'Cotización'
            );
            let documento = this.documentos.find(
              (x: any) => x.nombre == 'Cotización'
            );
            if (documento) {
              this.venta.id_documento = documento.id;
              this.venta.correlativo = documento.correlativo;
            }
            //si no existe el documento de cotizacion decirle que debe crearlo
            if (!documento) {
              this.alertService.error('Debe crear un documento de cotización');
            }
          } else {
            this.documentos = this.documentos.filter(
              (doc: any) =>
                doc.nombre === 'Factura' || doc.nombre === 'Crédito fiscal' || doc.nombre === 'Factura de exportación' || doc.nombre === 'Factura comercial' || doc.nombre === 'Ticket' || doc.nombre === 'Recibo' || doc.nombre === 'Sujeto excluido'
            );
          }
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );
  }

  public cargarDatosIniciales() {
    this.venta = {};
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

    // Para cotizaciones Pre-venta
    if (this.route.snapshot.queryParamMap.get('cotizacion')) {
      this.venta.cotizacion = 1;
      this.venta.estado = 'Pendiente';
      this.venta.tipo = 'cotizacion'; // Identificador para cotización
      this.venta.observaciones = this.venta.id_empresa == 2 ? 'Uso del Servicio: La plataforma SmartPyme se proporciona bajo licencia no exclusiva y no transferible, según el plan de suscripción seleccionado por el cliente. El cliente es responsable del uso adecuado de la plataforma y de la exactitud de los datos ingresados. \nPagos: Las tarifas establecidas en la cotización deben ser pagadas puntualmente. Los retrasos en el pago pueden llevar a la suspensión o cancelación del servicio. \nDisponibilidad del Servicio: SmartPyme garantiza un 99% de disponibilidad del servicio, excluyendo mantenimientos programados y eventos de fuerza mayor. \nPropiedad Intelectual: El cliente no podrá realizar ingeniería inversa, descompilar ni modificar la plataforma. \nLimitación de responsabilidad: SmartPyme no se hace responsable de pérdidas de datos causadas por eventos externos, uso indebido de la plataforma o situaciones fuera de su control razonable. \nDuración del acuerdo: Los servicios se brindan durante la vigencia del plan de suscripción. Tras terminación, el cliente tiene derecho a descargar su información antes de que sea eliminada, siempre y cuando no tenga pagos pendientes. En caso de mora, SmartPyme no estará obligada a proporcionar acceso o respaldos hasta que la situación sea regularizada. \nSituaciones excepcionales: \nEn caso de circunstancias extraordinarias que conlleven la finalización de operaciones, la empresa no estará obligada a continuar con la prestación del servicio. Esto incluye, pero no se limita a, solicitudes de acceso perpetuo o indefinido a la plataforma. \nRenovación: Los cobros se efectuarán de forma automática cada mes (acorde a la forma de pago elegida), por lo que de no continuar usando el sistema debe notificarse por escrito al correo electrónico expresando las razones. De esta forma se brindará un plazo de 15 días para extraer la información de su cuenta, posteriormente será eliminada definitivamente. \nPolítica de reembolsos: No se realizan reembolsos ni devoluciones bajo ninguna circunstancia, incluyendo cancelaciones anticipadas, falta de uso del sistema o cualquier otra razón. Al realizar el pago, el cliente acepta esta condición. \nCompromisos de SmartPyme: \nBrindar capacitaciones y soporte técnico a usuarios de negocios. \nGarantizar el correcto funcionamiento de la plataforma en todo momento con altos estándares de seguridad, disponibilidad y confidencialidad. \nOfrecemos acompañamiento y asesoría durante el proceso de implementación, de facturación electrónica u otro correspondiente a la información para el uso necesario de SmartPyme.\nBrindar documentación de confidencialidad para su firma. \nPara SmartPyme será un honor trabajar con usted y apoyar sus esfuerzos en optimizar las operaciones de su empresa y proporcionar información oportuna a través de nuestra plataforma de Inteligencia de Negocios. \nQuedamos atentos a cualquier consulta o información adicional que necesite.' : '';
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
    if (this.route.snapshot.paramMap.get('id')) {
      this.editar = true;
      const endpoint = this.venta.cotizacion == 1 ? 'cotizacion/' : 'venta/';
      const isCotizacion = this.venta.cotizacion == 1 ? true : false;
      this.apiService
        .read('venta/', +this.route.snapshot.paramMap.get('id')!)
        .subscribe(
          (venta) => {
            this.venta = venta;
            this.venta.cotizacion = isCotizacion ? 1 : 0;
            this.normalizarDetallesTipoGravado(this.venta);
            this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
            this.syncVentaCreditoConsignaFlagsFromEstado();
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
            this.normalizarDetallesTipoGravado(this.venta);
            if(!this.venta.cliente){
                this.venta.cliente = {};
            }else{
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
        this.apiService
          .read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!)
          .subscribe(
            (venta) => {
              this.venta = venta;
              this.normalizarDetallesTipoGravado(this.venta);
              if(!this.venta.cliente){
                  this.venta.cliente = {};
              }else{
                this.venta.cliente.nombre = this.venta.cliente.tipo == 'Empresa' ? this.venta.cliente.nombre_empresa : this.venta.cliente.nombre_completo;
              }
              this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
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
              this.venta.detalles.forEach((detalle: any) => {
                detalle.id = null;
              });
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
            const labelDoc = this.apiService.auth_user()?.empresa?.pais === 'El Salvador' ? 'DUI o NIT' : 'Número de identificación o Identificación fiscal';
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
    this.cargarDocumentos();
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
          detalle.costo = parseFloat(producto.costo);
          detalle.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
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

    if(this.venta.cotizacion){
      this.venta.observaciones = this.venta.id_empresa == 2 ? '➢ Uso del Servicio: La plataforma SmartPyme se proporciona bajo licencia no exclusiva y no transferible, según el plan de suscripción seleccionado por el cliente. El cliente es responsable del uso adecuado de la plataforma y de la exactitud de los datos ingresados. \n➢ Pagos: Las tarifas establecidas en la cotización deben ser pagadas puntualmente. Los retrasos en el pago pueden llevar a la suspensión o cancelación del servicio. \n➢ Disponibilidad del Servicio: SmartPyme garantiza un 99% de disponibilidad del servicio, excluyendo mantenimientos programados y eventos de fuerza mayor. \n➢ Propiedad Intelectual: El cliente no podrá realizar ingeniería inversa, descompilar ni modificar la plataforma. \n➢ Limitación de responsabilidad: SmartPyme no se hace responsable de pérdidas de datos causadas por eventos externos, uso indebido de la plataforma o situaciones fuera de su control razonable. \n➢ Duración del acuerdo: Los servicios se brindan durante la vigencia del plan de suscripción. Tras terminación, el cliente tiene derecho a descargar su información antes de que sea eliminada, siempre y cuando no tenga pagos pendientes. En caso de mora, SmartPyme no estará obligada a proporcionar acceso o respaldos hasta que la situación sea regularizada. \n➢ Situaciones excepcionales: \nEn caso de circunstancias extraordinarias que conlleven la finalización de operaciones, la empresa no estará obligada a continuar con la prestación del servicio. Esto incluye, pero no se limita a, solicitudes de acceso perpetuo o indefinido a la plataforma. \n➢ Renovación: Los cobros se efectuarán de forma automática cada mes (acorde a la forma de pago elegida), por lo que de no continuar usando el sistema debe notificarse por escrito al correo electrónico expresando las razones. De esta forma se brindará un plazo de 15 días para extraer la información de su cuenta, posteriormente será eliminada definitivamente. \n➢ Política de reembolsos: No se realizan reembolsos ni devoluciones bajo ninguna circunstancia, incluyendo cancelaciones anticipadas, falta de uso del sistema o cualquier otra razón. Al realizar el pago, el cliente acepta esta condición. \nCompromisos de SmartPyme: \n➢ Brindar capacitaciones y soporte técnico a usuarios de negocios. \n➢ Garantizar el correcto funcionamiento de la plataforma en todo momento con altos estándares de seguridad, disponibilidad y confidencialidad. \n➢ Ofrecemos acompañamiento y asesoría durante el proceso de implementación, de facturación electrónica u otro correspondiente a la información para el uso necesario de SmartPyme.\n➢ Brindar documentación de confidencialidad para su firma. \nPara SmartPyme será un honor trabajar con usted y apoyar sus esfuerzos en optimizar las operaciones de su empresa y proporcionar información oportuna a través de nuestra plataforma de Inteligencia de Negocios. \nQuedamos atentos a cualquier consulta o información adicional que necesite.' : '';
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
    ).total;
    console.log(this.venta);
  }

  public sumTotal() {
    if (
      this.venta.cobrar_impuestos &&
      (!this.venta.impuestos || this.venta.impuestos.length === 0)
    ) {
      this.alertService.warning(
        'Configuración requerida',
        'Debe configurar los impuestos en el módulo de finanzas antes de poder incluir IVA'
      );
      this.venta.cobrar_impuestos = false;
      return;
    }

    // Asegurar que detalles existe y es un array
    if (!this.venta.detalles || !Array.isArray(this.venta.detalles)) {
      this.venta.detalles = [];
    }

    // Asegurar que impuestos existe y es un array
    if (!this.venta.impuestos || !Array.isArray(this.venta.impuestos)) {
      this.venta.impuestos = [];
    }

    // 4 decimales en agregados para cuadrar con líneas (precio sin IVA + IVA por línea); el total final sigue en 2
    const rawSubTotal = parseFloat(this.sumPipe.transform(this.venta.detalles, 'total'));
    this.venta.sub_total = Number(rawSubTotal).toFixed(4);

    this.sincronizarRetencionGranContribuyente();

    const rawExenta = parseFloat(this.sumPipe.transform(this.venta.detalles, 'exenta'));
    this.venta.exenta = Number(rawExenta).toFixed(4);
    const rawNoSujeta = parseFloat(this.sumPipe.transform(this.venta.detalles, 'no_sujeta'));
    this.venta.no_sujeta = Number(rawNoSujeta).toFixed(4);
    const rawGravada = parseFloat(this.sumPipe.transform(this.venta.detalles, 'gravada'));
    this.venta.gravada = Number(rawGravada).toFixed(4);
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

    // IVA por tasa: cada impuesto recibe solo el IVA de los detalles con ese porcentaje
    const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
    const pctIgual = (a: number, b: number) => Math.abs(Number(a) - Number(b)) < 0.01;
    const porcentajesImpuestos = (this.venta.impuestos || []).map((i: any) => Number(i.porcentaje));
    if (this.venta.cobrar_impuestos) {
      this.venta.impuestos.forEach((impuesto: any) => {
        const pctImp = Number(impuesto.porcentaje);
        const monto = this.venta.detalles
          .filter((d: any) => {
            const pctDetalle = (d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '')
              ? Number(d.porcentaje_impuesto) : empresaIva;
            return pctIgual(pctImp, pctDetalle);
          })
          .reduce((sum: number, d: any) => {
            const gravada = parseFloat(d.gravada || 0);
            const ivaLinea = (d.iva != null && d.iva !== '' && parseFloat(d.iva) >= 0)
              ? parseFloat(d.iva) : gravada * (pctImp / 100);
            return sum + ivaLinea;
          }, 0);
        impuesto.monto = parseFloat(Number(monto).toFixed(4));
      });
      // Detalles cuyo % no coincide con ningún impuesto: asignar su IVA al impuesto de la empresa o al primero
      if (this.venta.detalles.length && this.venta.impuestos.length) {
        const ivaSinAsignar = this.venta.detalles
          .filter((d: any) => {
            const pctDetalle = (d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '')
              ? Number(d.porcentaje_impuesto) : empresaIva;
            return !porcentajesImpuestos.some((p: number) => pctIgual(p, pctDetalle));
          })
          .reduce((sum: number, d: any) => {
            const gravada = parseFloat(d.gravada || 0);
            const pct = (d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '')
              ? Number(d.porcentaje_impuesto) : empresaIva;
            const ivaLinea = (d.iva != null && d.iva !== '' && parseFloat(d.iva) >= 0)
              ? parseFloat(d.iva) : gravada * (pct / 100);
            return sum + ivaLinea;
          }, 0);
        if (ivaSinAsignar > 0) {
          const impuestoDestino = this.venta.impuestos.find((i: any) => pctIgual(Number(i.porcentaje), empresaIva))
            || this.venta.impuestos[0];
          impuestoDestino.monto = parseFloat((parseFloat(impuestoDestino.monto) + ivaSinAsignar).toFixed(4));
        }
      }
      this.venta.iva = (parseFloat(this.sumPipe.transform(this.venta.impuestos, 'monto')) || 0).toFixed(4);
    } else {
      this.venta.iva = (0).toFixed(4);
      this.venta.impuestos.forEach((impuesto: any) => { impuesto.monto = 0; });
    }

    const rawDescuento = parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'));
    this.venta.descuento = Number(rawDescuento).toFixed(4);
    const rawTotalCosto = parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'));
    this.venta.total_costo = Number(rawTotalCosto).toFixed(4);

    // El total NO incluye la propina; subtotal e IVA en 4 decimales, total redondeado a moneda (2)
    const descuentoPuntos = parseFloat(this.venta.descuento_puntos || 0) || 0;
    const totalNum =
      parseFloat(this.venta.sub_total) +
      parseFloat(this.venta.iva) +
      parseFloat(this.venta.cuenta_a_terceros) +
      parseFloat(String(this.venta.iva_percibido)) -
      parseFloat(String(this.venta.iva_retenido)) -
      parseFloat(String(this.venta.renta_retenida)) -
      descuentoPuntos;
    this.venta.total = (Math.round(totalNum * 100) / 100).toFixed(2);

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
  }

    /** Monto mínimo (USD u otra moneda de la empresa) para aplicar retención IVA 1% automática a clientes gran contribuyente. */
    private montoMinimoRetencionIvaGc(): number {
        const v = this.apiService.auth_user()?.empresa?.monto_minimo_retencion_iva_gc;
        const n = parseFloat(v);
        return !isNaN(n) && n >= 0 ? n : 100;
    }

    /** Activa o desactiva la retención según subtotal y tipo de contribuyente del cliente. */
    private sincronizarRetencionGranContribuyente(): void {
        const c = this.venta?.cliente;
        if (!c || c.tipo_contribuyente !== 'Grande') {
            return;
        }
        const sub = parseFloat(this.venta.sub_total) || 0;
        const min = this.montoMinimoRetencionIvaGc();
        this.venta.retencion = sub > min;
    }

    // Cliente
    public setCliente(cliente:any){
        if(cliente.id){
            cliente.nombre = cliente.tipo == 'Empresa' ? cliente.nombre_empresa : cliente.nombre_completo;
            this.venta.id_cliente = cliente.id;
            this.venta.cliente = cliente;
            if(cliente.tipo_contribuyente == "Grande") {
                this.sumTotal();
            }
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
            this.venta.estado = 'Pagada';
            this.venta.condicion = 'Contado';
            this.venta.fecha_pago = moment().format('YYYY-MM-DD');
            // Limpiar mensaje de validación al cambiar a contado
            this.mensajeValidacionFecha = '';
        }
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
            this.venta.estado = 'Consigna';
        } else {
            this.setCredito();
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

        // Si el método de pago no es "Efectivo", "Wompi" ni "Multiple", asignar el banco por defecto del método de pago
        if (this.venta.forma_pago && this.venta.forma_pago !== 'Efectivo' && this.venta.forma_pago !== 'Multiple' && this.venta.forma_pago !== 'Wompi') {
            const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.venta.forma_pago);

            if (formaPagoSeleccionada && formaPagoSeleccionada.banco && formaPagoSeleccionada.banco.nombre_banco) {
                // Si la forma de pago tiene un banco asignado, usarlo
                this.venta.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else {
                // Si no tiene banco asignado, limpiar el campo
                this.venta.detalle_banco = '';
            }
            this.mensajeErrorBanco = '';
        } else if (this.venta.forma_pago === 'Efectivo' || this.venta.forma_pago === 'Wompi') {
            // Si es efectivo o Wompi, limpiar el campo de banco
            this.venta.detalle_banco = '';
            this.mensajeErrorBanco = '';
        }
        this.cdr.markForCheck();
    }

    /** Catálogo MH (incoterm, recinto, régimen) y DTE 11: solo El Salvador. */
    esFacturacionElSalvador(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_SV;
    }

    public setDocumento(id_documento: any) {
        let documento = this.documentos.find((x: any) => x.id == id_documento);
        this.venta.nombre_documento = documento.nombre;
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;

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
    // Validar que si el método de pago requiere banco, este esté seleccionado
    this.mensajeErrorBanco = '';

    if (this.venta.cotizacion != 1 && this.requiereBanco() && !this.venta.detalle_banco) {
      this.mensajeErrorBanco = 'Debe seleccionar un banco para este método de pago.';
      this.alertService.error('Debe seleccionar un banco para este método de pago.');
      return;
    }

    if (
      this.venta.cobrar_impuestos &&
      (!this.venta.impuestos || this.venta.impuestos.length === 0)
    ) {
      this.alertService.error(
        'Debe configurar los impuestos en el módulo de finanzas antes de poder incluir IVA'
      );
      return;
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

        if (this.venta.forma_pago == 'Wompi') {
          this.venta.estado = 'Pendiente';
        }
        this.onSubmit();
      }
    });
  }

  /**
   * Verifica si el método de pago requiere selección de banco
   */
  public requiereBanco(): boolean {
    return this.venta.forma_pago &&
           this.venta.forma_pago !== 'Efectivo' &&
           this.venta.forma_pago !== 'Wompi' &&
           this.venta.forma_pago !== 'Multiple';
  }

  // Guardar venta
  public onSubmit() {
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

    this.apiService.store('facturacion', this.venta).subscribe(
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

        if (this.venta.cotizacion != 1) {
          this.generarPartidaVentaSiAutomatico(venta);
        }

        // Si es cotización
        // if (this.facturarCotizacion) {
        //   this.apiService
        //     .read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!)
        //     .subscribe(
        //       (venta) => {
        //         venta.estado = 'Facturada';
        //         this.apiService.store('venta', venta).subscribe(
        //           (venta) => {},
        //           (error) => {
        //             this.alertService.error(error);
        //             this.saving = false;
        //           }
        //         );
        //       },
        //       (error) => {
        //         this.alertService.error(error);
        //         this.saving = false;
        //       }
        //     );
        // }

        if (
          this.venta.cotizacion != 1 &&
          this.apiService.auth_user().empresa.impresion_en_facturacion
        ) {
          if (this.apiService.auth_user().empresa.facturacion_electronica) {
            this.emitirDTE();
          } else {
            window.open(
              this.apiService.baseUrl +
              '/api/reporte/facturacion/' +
              venta.id +
              '?token=' +
              this.apiService.auth_token(),
              'Impresión',
              'width=400'
            );
            if (this.preCuentaId && this.venta.id) {
              this.navegarPostFacturaPreCuenta(this.venta.id);
            } else if (this.pedidoCanalId && this.venta.id) {
              this.navegarPostFacturaPedidoCanal(this.venta.id);
            } else {
              this.cargarDatosIniciales();
              this.loadData();
              this.router.navigate(['/venta/crear']);
            }
          }
        } else {
          if (this.venta.cotizacion == 1) {
            this.router.navigate(['/cotizaciones']);
            this.alertService.success(
              'Cotización creada',
              'La cotizacion fue añadida exitosamente.'
            );
          } else if (this.preCuentaId && this.venta.id) {
            this.navegarPostFacturaPreCuenta(this.venta.id);
          } else if (this.pedidoCanalId && this.venta.id) {
            this.navegarPostFacturaPedidoCanal(this.venta.id);
          } else {
            this.router.navigate(['/ventas']);
            this.alertService.success(
              'Venta creada',
              'La venta fue añadida exitosamente.'
            );
          }
        }

        if (this.modalRef) {
          this.closeModal();
        }
        this.saving = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
        this.cdr.markForCheck();
      }
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

  emitirDTE() {
    this.emiting = true;
    this.facturacionElectronica
      .emitirDTE(this.venta)
      .then((venta) => {
        this.venta = venta;
        this.syncVentaCreditoConsignaFlagsFromEstado();
        this.alertService.success(
          'DTE emitido.',
          'El documento ha sido emitido.'
        );
        if (this.venta.id_cliente && this.facturacionElectronica.requiereFlujoEnviarDteSeparado()) {
          this.enviarDTE();
        }
        this.emiting = false;

        window.open(
          this.apiService.baseUrl +
            '/api/reporte/facturacion/' +
            venta.id +
            '?token=' +
            this.apiService.auth_token(),
          'Impresión',
          'width=400'
        );
        if (this.preCuentaId && this.venta.id) {
          this.navegarPostFacturaPreCuenta(this.venta.id);
        } else if (this.pedidoCanalId && this.venta.id) {
          this.navegarPostFacturaPedidoCanal(this.venta.id);
        } else {
          this.cargarDatosIniciales();
          this.router.navigate(['/venta/crear']);
        }
      })
      .catch((error: any) => {
        this.emiting = false;
        if (error?.venta) {
          this.venta = error.venta;
        }
        const msg = typeof error === 'string' ? error : error?.message ?? error;
        this.alertService.warning('El documento no fue emitido.', msg);
        this.cdr.markForCheck();
      });
  }

  enviarDTE() {
    this.sending = true;
    this.apiService.store('enviarDTE', this.venta).pipe(this.untilDestroyed()).subscribe(
      (dte) => {
        this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
        this.sending = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error('DTE no pudo ser enviado por correo.');
        this.sending = false;
        this.cdr.markForCheck();
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

      this.apiService.getAll('documentos/list').pipe(this.untilDestroyed()).subscribe(
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
          this.cdr.markForCheck();

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

  /** Normaliza detalles: infiere tipo_gravado y sub_total si faltan (ventas existentes). Asegura gravada/exenta/no_sujeta para que el IVA cuadre. */
  private normalizarDetallesTipoGravado(venta: any) {
    if (!venta?.detalles?.length) return;
    const tiposValidos = ['gravada', 'exenta', 'no_sujeta'];
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
      d.gravada = (d.tipo_gravado === 'gravada') ? totalLinea : 0;
      d.exenta = (d.tipo_gravado === 'exenta') ? totalLinea : 0;
      d.no_sujeta = (d.tipo_gravado === 'no_sujeta') ? totalLinea : 0;
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
                this.tieneFidelizacionHabilitada = tieneAcceso;
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
