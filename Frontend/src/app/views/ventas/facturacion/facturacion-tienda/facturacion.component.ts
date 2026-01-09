import { Component, OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { FuncionalidadesService } from '@services/functionalities.service';
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
import Swal from 'sweetalert2';

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
  public mensajeValidacionFecha: string = '';
  public mensajeErrorBanco: string = '';

  public modalCredito!: any; // BsModalRef

  @ViewChild('msupervisor')
  public supervisorTemplate!: TemplateRef<any>;

  @ViewChild('mcredito')
  public creditoTemplate!: TemplateRef<any>;

  private cdr = inject(ChangeDetectorRef);

  constructor(
    public apiService: ApiService,
    public mhService: MHService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private sumPipe: SumPipe,
    private route: ActivatedRoute,
    private router: Router,
    private funcionalidadesService: FuncionalidadesService,
    private sharedDataService: SharedDataService
  ) {
    super(modalManager, alertService);
    this.router.routeReuseStrategy.shouldReuseRoute = function () {
      return false;
    };
  }

  ngOnInit() {
    this.cargarDatosIniciales();
    this.loadData();
    this.verificarAccesoPropina();
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

    this.apiService.getAll('formas-de-pago/list').pipe(this.untilDestroyed()).subscribe(
      (formaPagos) => {
        this.formaPagos = formaPagos;
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
        this.impuestos = impuestos;
        if (!this.venta.impuestos || this.venta.iva == 0) {
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
                doc.nombre === 'Factura' || doc.nombre === 'Crédito fiscal' || doc.nombre === 'Factura de exportación' || doc.nombre === 'Ticket' || doc.nombre === 'Recibo' || doc.nombre === 'Sujeto excluido'
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
    this.venta.id_bodega = this.apiService.auth_user().id_sucursal;
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
    }

    if (this.route.snapshot.paramMap.get('id')) {
      this.editar = true;
      const endpoint = this.venta.cotizacion == 1 ? 'cotizacion/' : 'venta/';
      const isCotizacion = this.venta.cotizacion == 1 ? true : false;
      this.apiService
        .read(endpoint, +this.route.snapshot.paramMap.get('id')!)
        .pipe(this.untilDestroyed())
        .subscribe((venta) => {
          this.venta = venta;
          this.venta.cotizacion = isCotizacion ? 1 : 0;
          this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;

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
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

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
      this.facturarCotizacion = true;

      this.apiService.getAll('impuestos').pipe(this.untilDestroyed()).subscribe(
        (impuestos) => {
          this.impuestos = impuestos;
          this.cdr.markForCheck();

          this.apiService.read(
            'cotizacionVentas/',
            +this.route.snapshot.queryParamMap.get('id_venta')!
          ).pipe(this.untilDestroyed()).subscribe(
            (venta) => {
              this.venta = venta;
              this.venta.cobrar_impuestos = venta.cobrar_impuestos;
              this.venta.retencion = venta.aplicar_retencion;
              this.venta.fecha = this.apiService.date();
              this.venta.fecha_pago = this.apiService.date();
              this.venta.estado = 'Pagada';
              this.venta.cotizacion = 0;
              this.venta.num_cotizacion = this.venta.id;
              this.venta.id = null;

              if (!this.venta.impuestos || this.venta.impuestos.length === 0) {
                this.venta.impuestos = this.impuestos;
              }

              this.venta.detalles.forEach((detalle: any) => {
                if (detalle.codigo_combo) {
                  detalle.descripcion = detalle.combo.nombre;
                  detalle.detalles = detalle.combo.detalles;
                } else {
                  detalle.descripcion = detalle.producto.nombre;
                }
                detalle.id = null;
              });

              if (this.route.snapshot.queryParamMap.get('id_proyecto')) {
                this.venta.detalles = [];
              }

              // Cargar los documentos y buscar una factura
              this.apiService.getAll('documentos/list').pipe(this.untilDestroyed()).subscribe(
                (documentos) => {
                  this.documentos = documentos;
                  this.documentos = this.documentos.filter(
                    (x: any) => x.id_sucursal == this.venta.id_sucursal
                  );

                  // Filtrar solo documentos tipo factura
                  const docsFiltrados = this.documentos.filter(
                    (x: any) => x.nombre != 'Cotización' && x.nombre != 'Orden de compra'
                  );

                  // Buscar documento predeterminado o tomar el primero
                  let documentoFactura = docsFiltrados.find(
                    (doc: any) => doc.predeterminado == 1
                  );

                  if (documentoFactura) {
                    this.venta.id_documento = documentoFactura.id;
                    this.venta.correlativo = documentoFactura.correlativo;
                    this.venta.nombre_documento = documentoFactura.nombre;
                  } else if (docsFiltrados.length > 0) {
                    this.venta.id_documento = docsFiltrados[0].id;
                    this.venta.correlativo = docsFiltrados[0].correlativo;
                    this.venta.nombre_documento = docsFiltrados[0].nombre;
                  }

                  // Actualizar la lista de documentos
                  this.documentos = docsFiltrados;

                  // Calcular totales
                  this.sumTotal();

                  // Completar carga de otros datos
                  this.loadData();
                  this.cdr.markForCheck();
                },
                (error) => {
                  this.alertService.error(error);
                  this.cdr.markForCheck();
                }
              );
            },
            (error) => {
              this.alertService.error(error);
              this.loading = false;
              this.cdr.markForCheck();
            }
          );
        },
        (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );

      return;
    }

    // Facturar orden de compra
    if (this.route.snapshot.queryParamMap.get('facturar_orden_compra')!) {
      this.apiService.read('orden-de-compra/solicitud/', +this.route.snapshot.queryParamMap.get('id_orden_compra')!).pipe(this.untilDestroyed()).subscribe((ordenCompra) => {
        this.venta.num_orden = ordenCompra.id;
        this.cdr.markForCheck();

        this.apiService.getAll('clientes/buscar/' + (ordenCompra.empresa.dui ?? ordenCompra.empresa.nit)).pipe(this.untilDestroyed()).subscribe((empresa) => {
          if(empresa.length > 0){
            this.setCliente(empresa[0]);
            console.log(empresa);

            // Solo procesar productos si el cliente existe
            this.procesarProductosOrdenCompra(ordenCompra.detalles);
            this.cdr.markForCheck();
          }else{
            Swal.fire({
              title: 'Cliente no encontrado',
              html: `
                <div class="text-left">
                  <p><strong>No se encontró el cliente para poder facturar.</strong></p>
                  <p>Debe crear el cliente con los siguientes datos:</p>
                  <ul class="list-unstyled mt-3">
                    <li><strong>Nombre:</strong> ${ordenCompra.empresa.nombre || 'No disponible'}</li>
                    <li><strong>DUI o NIT:</strong> ${ordenCompra.empresa.dui || ordenCompra.empresa.nit || 'No disponible'}</li>
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
          detalle.gravada = detalle.total;
          detalle.id_vendedor = this.venta.id_vendedor;
          detalle.exenta = 0;
          detalle.no_sujeta = 0;
          detalle.cuenta_a_terceros = 0;
          detalle.total = detalle.precio * detalle.cantidad;
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

    this.venta.sub_total = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'total')
    ).toFixed(4);

    this.venta.exenta = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'exenta')
    ).toFixed(4);
    this.venta.no_sujeta = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'no_sujeta')
    ).toFixed(4);
    this.venta.gravada = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'gravada')
    ).toFixed(4);
    this.venta.cuenta_a_terceros = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'cuenta_a_terceros')
    ).toFixed(4);

    this.venta.iva_percibido = this.venta.percepcion
      ? this.venta.sub_total * 0.01
      : 0;
    this.venta.iva_retenido = this.venta.retencion
      ? this.venta.sub_total * 0.01
      : 0;
    this.venta.renta_retenida = this.venta.renta
      ? this.venta.sub_total * 0.10
      : 0;

    this.venta.impuestos.forEach((impuesto: any) => {
      if (this.venta.cobrar_impuestos) {
        impuesto.monto = this.venta.sub_total * (impuesto.porcentaje / 100);
      } else {
        impuesto.monto = 0;
      }
    });

    this.venta.iva = parseFloat(
      this.sumPipe.transform(this.venta.impuestos, 'monto')
    ).toFixed(4);
    this.venta.descuento = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'descuento')
    ).toFixed(4);
    this.venta.total_costo = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'total_costo')
    ).toFixed(4);
    // Inicializar propina si no existe
    if (!this.venta.propina) {
      this.venta.propina = 0;
    }

    this.venta.total = (
      parseFloat(this.venta.sub_total) +
      parseFloat(this.venta.iva) +
      parseFloat(this.venta.cuenta_a_terceros) +
      parseFloat(this.venta.exenta) +
      parseFloat(this.venta.no_sujeta) +
      parseFloat(this.venta.iva_percibido) -
      parseFloat(this.venta.iva_retenido) -
      parseFloat(this.venta.renta_retenida) +
      parseFloat(this.venta.propina || 0)
    ).toFixed(4);

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

    // Cliente
    public setCliente(cliente:any){
        if(cliente.id){
            cliente.nombre = cliente.tipo == 'Empresa' ? cliente.nombre_empresa : cliente.nombre_completo;
            this.venta.id_cliente = cliente.id;
            this.venta.cliente = cliente;
            if(cliente.tipo_contribuyente == "Grande") {
                this.venta.retencion = 1;
                this.sumTotal();
            }

            // Si el cliente tiene tiempo_pago configurado, ajustar la fecha de pago
            if (cliente.tiempo_pago && this.venta.credito) {
                const fechaBase = this.venta.fecha ? moment(this.venta.fecha) : moment();
                this.venta.fecha_pago = fechaBase.add(cliente.tiempo_pago, 'days').format('YYYY-MM-DD');
            }

            // Limpiar mensaje de validación al cambiar cliente
            this.mensajeValidacionFecha = '';
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

    public updateVenta(venta: any) {
        this.venta = venta;
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

        // Limpiar banco y mensaje de error al cambiar método de pago
        if (!this.requiereBanco()) {
            this.venta.detalle_banco = '';
            this.mensajeErrorBanco = '';
        }
        console.log(this.venta);
    }

    public setDocumento(id_documento: any) {
        let documento = this.documentos.find((x: any) => x.id == id_documento);
        this.venta.nombre_documento = documento.nombre;
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;

        if (this.venta.nombre_documento == 'Factura de exportación') {
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
           this.venta.forma_pago !== 'Wompi';
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

    if (this.venta.detalles) {
      this.venta.detalles.forEach((detalle: any) => {
        if (detalle.custom_fields) {
          detalle.custom_fields = detalle.custom_fields.filter((cf: any) =>
            this.selectedCustomFields.includes(cf.custom_field?.id)
          );
        }
      });
    }

    this.apiService.store('facturacion', this.venta).pipe(this.untilDestroyed()).subscribe(
      (venta) => {

        if (
          this.venta.cotizacion != 1 &&
          this.apiService.auth_user().empresa.impresion_en_facturacion
        ) {
          if (this.apiService.auth_user().empresa.facturacion_electronica) {
            this.venta.id = venta.id;
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
            this.cargarDatosIniciales();
            this.router.navigate(['/venta/crear']);
          }
        } else {
          if (this.venta.cotizacion == 1) {
            this.router.navigate(['/cotizaciones']);
            this.alertService.success(
              'Cotización creada',
              'La cotizacion fue añadida exitosamente.'
            );
          } else {
            this.router.navigate(['/ventas']);
            this.alertService.success(
              'Venta creada',
              'La venta fue añadida exitosamente.'
            );

            //Generar partida contable
            if (
              this.apiService.auth_user().empresa.generar_partidas == 'Auto'
            ) {
              this.apiService
                .store('contabilidad/partida/venta', venta)
                .pipe(this.untilDestroyed())
                .subscribe(
                  (venta) => { this.cdr.markForCheck(); },
                  (error) => {
                    this.alertService.error(error);
                    this.cdr.markForCheck();
                  }
                );
            }
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
    this.mhService
      .emitirDTE(this.venta)
      .then((venta) => {
        this.venta = venta;
        this.alertService.success(
          'DTE emitido.',
          'El documento ha sido emitido.'
        );
        if (this.venta.id_cliente) {
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
        this.cargarDatosIniciales();
        this.router.navigate(['/venta/crear']);
      })
      .catch((error) => {
        this.cargarDatosIniciales();
        this.router.navigate(['/venta/crear']);

        this.emiting = false;
        this.alertService.warning('El documento no fue emitido.', error);
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

}
