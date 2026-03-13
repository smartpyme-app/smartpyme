import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { MHService } from '@services/MH.service';
import Swal from 'sweetalert2';

import * as moment from 'moment';

@Component({
  selector: 'app-facturacion-v2',
  templateUrl: './facturacion-v2.component.html',
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
  public formaPagos: any = [];
  public sucursales: any = [];
  public bodegas: any = [];
  public impuestos: any = [];
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
  public facturarCotizacion = false;
  public api: boolean = false;
  public tieneAccesoPropina: boolean = false;
  public mensajeValidacionFecha: string = '';
  public mensajeErrorBanco: string = '';

  modalRef!: BsModalRef;
  modalCredito!: BsModalRef;

  @ViewChild('msupervisor')
  public supervisorTemplate!: TemplateRef<any>;

  @ViewChild('mcredito')
  public creditoTemplate!: TemplateRef<any>;

  constructor(
    public apiService: ApiService,
    public mhService: MHService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private sumPipe: SumPipe,
    private route: ActivatedRoute,
    private router: Router,
    private funcionalidadesService: FuncionalidadesService
  ) {
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
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('usuarios/list').subscribe(
      (usuarios) => {
        this.usuarios = usuarios;
        if (
          this.apiService.auth_user().tipo != 'Administrador' &&
          this.apiService.auth_user().tipo != 'Supervisor' &&
          this.apiService.auth_user().tipo != 'Supervisor Limitado'
        ) {
          this.usuarios = this.usuarios.filter(
            (item: any) => item.id == this.apiService.auth_user().id
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

    this.apiService.getAll('impuestos').subscribe(
      (impuestos) => {
        // Filtrar solo los impuestos que aplican a ventas
        this.impuestos = impuestos.filter((impuesto: any) => impuesto.aplica_ventas !== false && impuesto.aplica_ventas !== 0);
        // Al editar cotización/venta no sobrescribir impuestos para no volver a agregarlos
        const esEdicion = !!this.route.snapshot.paramMap.get('id');
        if (!esEdicion && (!this.venta.impuestos || this.venta.iva == 0)) {
          this.venta.impuestos = this.impuestos;
          this.sumTotal();
        }
      },
      (error) => {
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
    this.apiService.getAll('documentos/list').subscribe(
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
          } else {
            this.documentos = this.documentos.filter(
              (doc: any) =>
                doc.nombre === 'Factura' || doc.nombre === 'Crédito fiscal' || doc.nombre === 'Factura de exportación' || doc.nombre === 'Factura comercial' || doc.nombre === 'Ticket' || doc.nombre === 'Recibo' || doc.nombre === 'Sujeto excluido'
            );
          }
        }
      },
      (error) => {
        this.alertService.error(error);
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
        this.venta.observaciones = this.venta.id_empresa == 2 ? 'Uso del Servicio: La plataforma SmartPyme se proporciona bajo licencia no exclusiva y no transferible, según el plan de suscripción seleccionado por el cliente. El cliente es responsable del uso adecuado de la plataforma y de la exactitud de los datos ingresados. \nPagos: Las tarifas establecidas en la cotización deben ser pagadas puntualmente. Los retrasos en el pago pueden llevar a la suspensión o cancelación del servicio. \nDisponibilidad del Servicio: SmartPyme garantiza un 99% de disponibilidad del servicio, excluyendo mantenimientos programados y eventos de fuerza mayor. \nPropiedad Intelectual: El cliente no podrá realizar ingeniería inversa, descompilar ni modificar la plataforma. \nLimitación de responsabilidad: SmartPyme no se hace responsable de pérdidas de datos causadas por eventos externos, uso indebido de la plataforma o situaciones fuera de su control razonable. \nDuración del acuerdo: Los servicios se brindan durante la vigencia del plan de suscripción. Tras terminación, el cliente tiene derecho a descargar su información antes de que sea eliminada, siempre y cuando no tenga pagos pendientes. En caso de mora, SmartPyme no estará obligada a proporcionar acceso o respaldos hasta que la situación sea regularizada. \nSituaciones excepcionales: \nEn caso de circunstancias extraordinarias que conlleven la finalización de operaciones, la empresa no estará obligada a continuar con la prestación del servicio. Esto incluye, pero no se limita a, solicitudes de acceso perpetuo o indefinido a la plataforma. \nRenovación: Los cobros se efectuarán de forma automática cada mes (acorde a la forma de pago elegida), por lo que de no continuar usando el sistema debe notificarse por escrito al correo electrónico expresando las razones. De esta forma se brindará un plazo de 15 días para extraer la información de su cuenta, posteriormente será eliminada definitivamente. \nPolítica de reembolsos: No se realizan reembolsos ni devoluciones bajo ninguna circunstancia, incluyendo cancelaciones anticipadas, falta de uso del sistema o cualquier otra razón. Al realizar el pago, el cliente acepta esta condición. \nCompromisos de SmartPyme: \nBrindar capacitaciones y soporte técnico a usuarios de negocios. \nGarantizar el correcto funcionamiento de la plataforma en todo momento con altos estándares de seguridad, disponibilidad y confidencialidad. \nOfrecemos acompañamiento y asesoría durante el proceso de implementación, de facturación electrónica u otro correspondiente a la información para el uso necesario de SmartPyme.\nBrindar documentación de confidencialidad para su firma. \nPara SmartPyme será un honor trabajar con usted y apoyar sus esfuerzos en optimizar las operaciones de su empresa y proporcionar información oportuna a través de nuestra plataforma de Inteligencia de Negocios. \nQuedamos atentos a cualquier consulta o información adicional que necesite.' : '';
    }

    // Para editar cotizaciones Pre-venta
    if (this.route.snapshot.paramMap.get('id')!) {
      this.apiService
        .read('venta/', +this.route.snapshot.paramMap.get('id')!)
        .subscribe(
          (venta) => {
            this.venta = venta;
            this.normalizarDetallesTipoGravado(this.venta);
            this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
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
            
            // Obtener porcentaje de IVA para conversión de precios
            const porcentajeIvaTotal = this.venta.cobrar_impuestos 
              ? (this.apiService.auth_user()?.empresa?.iva || 0)
              : 0;
            
            // Ajustar precios de los detalles para v2 (precios incluyen IVA)
            this.venta.detalles.forEach((detalle: any) => {
              detalle.id = null;
              
              // Si el detalle no tiene precio_iva, asumir que precio es sin IVA (versión anterior)
              // y calcular precio_iva
              if (!detalle.precio_iva || detalle.precio_iva === null || detalle.precio_iva === undefined) {
                if (porcentajeIvaTotal > 0) {
                  // El precio actual es sin IVA, calcular precio con IVA
                  detalle.precio_iva = (parseFloat(detalle.precio || 0) * (1 + porcentajeIvaTotal / 100)).toFixed(4);
                } else {
                  // Sin IVA, precio_iva es igual a precio
                  detalle.precio_iva = parseFloat(detalle.precio || 0).toFixed(4);
                }
              } else {
                // Si ya tiene precio_iva, verificar que precio (sin IVA) esté correcto
                if (porcentajeIvaTotal > 0) {
                  const precioSinIvaCalculado = this.calcularPrecioSinIva(parseFloat(detalle.precio_iva), porcentajeIvaTotal);
                  detalle.precio = precioSinIvaCalculado.toFixed(4);
                } else {
                  detalle.precio = parseFloat(detalle.precio_iva).toFixed(4);
                }
              }
              
              // Asegurar que precio_iva esté como número
              detalle.precio_iva = parseFloat(detalle.precio_iva).toFixed(4);
              
              // Recalcular total del detalle usando precio sin IVA
              const precioSinIva = parseFloat(detalle.precio || 0);
              detalle.sub_total = Number((parseFloat(detalle.cantidad || 0) * precioSinIva).toFixed(4));
              detalle.total = (parseFloat(detalle.sub_total) - parseFloat(detalle.descuento || 0)).toFixed(4);
              const tipo = (detalle.tipo_gravado && String(detalle.tipo_gravado).toLowerCase()) || 'gravada';
              detalle.tipo_gravado = ['gravada', 'exenta', 'no_sujeta'].includes(tipo) ? tipo : 'gravada';
              detalle.gravada = detalle.tipo_gravado === 'gravada' ? detalle.total : 0;
              detalle.exenta = detalle.tipo_gravado === 'exenta' ? detalle.total : 0;
              detalle.no_sujeta = detalle.tipo_gravado === 'no_sujeta' ? detalle.total : 0;
              
              // Calcular total_iva para visualización (solo gravada lleva IVA)
              if (detalle.tipo_gravado === 'gravada' && this.venta.cobrar_impuestos && porcentajeIvaTotal > 0) {
                detalle.total_iva = (parseFloat(detalle.total) * (1 + porcentajeIvaTotal / 100)).toFixed(4);
              } else {
                detalle.total_iva = detalle.total;
              }
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
            return;
          }
        });
      }, (error) => { this.alertService.error(error); this.loading = false; }
    );
    console.log(this.venta);
    }
    this.cargarDocumentos();
  }
    // Método para procesar productos de orden de compra
  public procesarProductosOrdenCompra(detalles: any[]) {
    detalles.forEach((detalleCompra: any) => {
      this.apiService.getAll('producto/buscar-by-code/'+ detalleCompra.codigo).subscribe((producto) => {
        if (producto) {
          let detalle: any = {};
          detalle.cantidad = detalleCompra.cantidad;
          detalle.descripcion = producto.nombre;
          detalle.id_producto = producto.id;
          
          // En v2, el precio se muestra con IVA pero se guarda sin IVA
          const iva = this.apiService.auth_user()?.empresa?.iva || 0;
          const precioSinIva = parseFloat(producto.precio);
          const precioConIva = precioSinIva * (1 + iva / 100);
          
          // precio_iva: precio con IVA (para cálculos y visualización)
          detalle.precio_iva = precioConIva.toFixed(4);
          // precio: precio sin IVA (para guardar en BD)
          detalle.precio = precioSinIva.toFixed(4);
          
          detalle.costo = parseFloat(producto.costo);
          detalle.id_vendedor = this.venta.id_vendedor;
          detalle.exenta = 0;
          detalle.no_sujeta = 0;
          detalle.cuenta_a_terceros = 0;
          detalle.total = precioConIva * detalle.cantidad;
          detalle.gravada = detalle.total;
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
    ).total;
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

  public sumTotal() {
    // Asegurar que detalles existe y es un array
    if (!this.venta.detalles || !Array.isArray(this.venta.detalles)) {
      this.venta.detalles = [];
    }

    // Asegurar que impuestos existe y es un array
    if (!this.venta.impuestos || !Array.isArray(this.venta.impuestos)) {
      this.venta.impuestos = [];
    }

    // En v2, los detalles tienen total sin IVA, así que agregamos el IVA al calcular los totales
    // Usar el IVA de la empresa directamente
    const porcentajeIvaTotal = this.venta.cobrar_impuestos 
      ? (this.apiService.auth_user()?.empresa?.iva || 0)
      : 0;

    // Redondear a 2 decimales para evitar diferencias de 1 centavo entre subtotal, IVA y total
    const rawSubTotal = parseFloat(this.sumPipe.transform(this.venta.detalles, 'total'));
    this.venta.sub_total = (Math.round(rawSubTotal * 100) / 100).toFixed(2);

    const rawExenta = parseFloat(this.sumPipe.transform(this.venta.detalles, 'exenta'));
    this.venta.exenta = (Math.round(rawExenta * 100) / 100).toFixed(2);
    const rawNoSujeta = parseFloat(this.sumPipe.transform(this.venta.detalles, 'no_sujeta'));
    this.venta.no_sujeta = (Math.round(rawNoSujeta * 100) / 100).toFixed(2);
    const rawGravada = parseFloat(this.sumPipe.transform(this.venta.detalles, 'gravada'));
    this.venta.gravada = (Math.round(rawGravada * 100) / 100).toFixed(2);
    const rawCuentaTerceros = parseFloat(this.sumPipe.transform(this.venta.detalles, 'cuenta_a_terceros'));
    this.venta.cuenta_a_terceros = (Math.round(rawCuentaTerceros * 100) / 100).toFixed(2);

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
      // Detalles cuyo % no coincide con ningún impuesto: asignar al impuesto empresa o al primero
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
      this.venta.iva = parseFloat(
        this.sumPipe.transform(this.venta.impuestos, 'monto')
      ).toFixed(4);
    } else {
      this.venta.iva = (0).toFixed(2);
      this.venta.impuestos.forEach((impuesto: any) => { impuesto.monto = 0; });
    }

    const rawDescuento = parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'));
    this.venta.descuento = (Math.round(rawDescuento * 100) / 100).toFixed(2);
    const rawTotalCosto = parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'));
    this.venta.total_costo = (Math.round(rawTotalCosto * 100) / 100).toFixed(4);

    // El total NO incluye la propina; calcular con 2 decimales para que cuadre con subtotal + IVA
    const totalNum =
      parseFloat(this.venta.sub_total) +
      parseFloat(this.venta.iva) +
      parseFloat(this.venta.cuenta_a_terceros) +
      parseFloat(String(this.venta.iva_percibido)) -
      parseFloat(String(this.venta.iva_retenido)) -
      parseFloat(String(this.venta.renta_retenida));
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
            if (cliente.limite_credito) {
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
        }
        console.log(cliente);
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
        
        // Si módulo bancos: asignar banco por defecto del método de pago
        if (this.apiService.isModuloBancos() && this.venta.forma_pago && this.venta.forma_pago !== 'Efectivo' && this.venta.forma_pago !== 'Wompi' && this.venta.forma_pago !== 'Multiple') {
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
    }

    public setDocumento(id_documento: any) {
        let documento = this.documentos.find((x: any) => x.id == id_documento);
        this.venta.nombre_documento = documento.nombre;
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;

        if (this.venta.nombre_documento == 'Factura de exportación') {
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
    // Validar que si el método de pago requiere banco, este esté seleccionado
    this.mensajeErrorBanco = '';
    
    if (this.venta.cotizacion != 1 && this.requiereBanco() && !this.venta.detalle_banco) {
      this.mensajeErrorBanco = 'Debe seleccionar un banco para este método de pago.';
      this.alertService.error('Debe seleccionar un banco para este método de pago.');
      return;
    }

    if (
      confirm(
        '¿Confirma procesar la ' +
          (this.venta.cotizacion == 1 ? ' cotización.' : 'venta.')
      )
    ) {
      if (!this.venta.recibido) this.venta.recibido = this.venta.total;

      if (this.venta.forma_pago == 'Wompi') {
        this.venta.estado = 'Pendiente';
      }
      this.onSubmit();
    }
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

    this.apiService.store('facturacion', this.venta).subscribe(
      (venta) => {
        // Actualizar siempre la venta local con la respuesta del backend (id, correlativo, etc.)
        // para que en un siguiente guardado se envíe el mismo correlativo.
        Object.assign(this.venta, venta);

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
            this.cargarDatosIniciales();
            this.loadData();
            this.router.navigate(['/ventas-v2/crear']);
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
              'Venta creado',
              'La venta fue añadida exitosamente.'
            );
          }
        }

        if (this.modalRef) {
          this.modalRef.hide();
        }
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  //Limpiar

  public limpiar() {
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
        if(this.venta.id_cliente){
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
        this.router.navigate(['/ventas-v2/crear']);
      })
      .catch((error) => {
        this.cargarDatosIniciales();
        this.router.navigate(['/ventas-v2/crear']);

        this.emiting = false;
        this.alertService.warning('El documento no fue emitido.', error);
      });
  }

  enviarDTE() {
    this.sending = true;
    this.apiService.store('enviarDTE', this.venta).subscribe(
      (dte) => {
        this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
        this.sending = false;
      },
      (error) => {
        this.alertService.error('DTE no pudo ser enviado por correo.');
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


  /** Normaliza detalles: infiere tipo_gravado y sub_total si faltan (ventas existentes). Asegura gravada/exenta/no_sujeta para que el IVA cuadre. */
  private normalizarDetallesTipoGravado(venta: any) {
    if (!venta?.detalles?.length) return;
    const tiposValidos = ['gravada', 'exenta', 'no_sujeta'];
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
      d.gravada = (d.tipo_gravado === 'gravada') ? totalLinea : 0;
      d.exenta = (d.tipo_gravado === 'exenta') ? totalLinea : 0;
      d.no_sujeta = (d.tipo_gravado === 'no_sujeta') ? totalLinea : 0;
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

public getTotalConPropina(): number {
    const total = parseFloat(this.venta?.total || 0);
    const propina = parseFloat(this.venta?.propina || 0);
    return total + propina;
}

}

