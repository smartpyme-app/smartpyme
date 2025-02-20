import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import Swal from 'sweetalert2';

import * as moment from 'moment';
import { co } from '@fullcalendar/core/internal-common';

@Component({
  selector: 'app-facturacion',
  templateUrl: './facturacion.component.html',
  providers: [SumPipe],
})
export class FacturacionComponent implements OnInit {
  public venta: any = {};
  public evento: any = {};
  public detalle: any = {};
  public clientes: any = [];
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
  public editar = false;

  // public bancos:any = [];
  public canales: any = [];
  public supervisor: any = {};
  public loading = false;
  public saving = false;
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
    private router: Router
  ) {
    this.router.routeReuseStrategy.shouldReuseRoute = function () {
      return false;
    };
  }

  ngOnInit() {
    this.cargarDatosIniciales();
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
    //solo si es una cotizacion if (this.route.snapshot.queryParamMap.get('cotizacion')) {
    if (this.route.snapshot.queryParamMap.get('cotizacion')) {
      this.apiService.getAll('custom-fields', this.filtros).subscribe(
        (customFields) => {
          console.log('customFields', customFields);
          this.customFields = customFields;
          //verificar si hay campos personalizados
          if (this.customFields.data.length > 0) {
            console.log('hay campos personalizados');
            this.customField = true;
          }else{
            console.log('no hay campos personalizados');
            this.customField = false;
          }
          
        },
        (error) => {
          this.alertService.error(error);
        }
      );
    }

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
          this.apiService.auth_user().tipo != 'Supervisor'
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

    // this.apiService.getAll('banco/cuentas/list').subscribe(bancos => {
    //     this.bancos = bancos;
    // }, error => {this.alertService.error(error);});

    this.apiService.getAll('formas-de-pago/list').subscribe(
      (formaPagos) => {
        this.formaPagos = formaPagos;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('canales/list').subscribe(
      (canales) => {
        this.canales = canales;

        if (this.route.snapshot.queryParamMap.get('cotizacion')) {
          this.venta.id_canal = null;
          return;
        }
        this.venta.id_canal = this.canales[0].id;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('impuestos').subscribe(
      (impuestos) => {
        this.impuestos = impuestos;
        if (!this.venta.impuestos || this.venta.iva == 0) {
          this.venta.impuestos = this.impuestos;
          this.sumTotal();
        }
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('clientes/list').subscribe(
      (clientes) => {
        this.clientes = clientes;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );

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
          (x: any) => x.id_sucursal == this.venta.id_sucursal
        );
        console.log(this.documentos);
        console.log(this.venta);

        if (!this.venta.id_documento && !this.venta.correlativo) {
          console.log('entro');

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
            //console.log('entro a cotizacion');
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
              (x: any) =>
                x.nombre != 'Cotización' && x.nombre != 'Orden de compra'
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
    this.venta.detalle_banco = '';
    this.venta.id_cliente = '';
    this.venta.detalles = [];
    this.venta.descuento = 0;
    this.venta.sub_total = 0;
    this.venta.iva_percibido = 0;
    this.venta.iva_retenido = 0;
    this.venta.cotizacion = 0;
    this.venta.iva = 0;
    this.venta.total_costo = 0;
    this.venta.total = 0;
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

    if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
      this.venta.id_proyecto =
        +this.route.snapshot.queryParamMap.get('id_proyecto')!;
    }

    if (this.route.snapshot.queryParamMap.get('cotizacion')) {
      this.venta.cotizacion = 1;
      this.venta.estado = 'Pendiente';
      this.venta.tipo = 'cotizacion'; // Identificador para cotización
    }

    // if (this.route.snapshot.paramMap.get('id')!) {
    //   const endpoint = this.venta.cotizacion == 1 ? 'cotizacion/' : 'venta/';
    //   const isCotizacion = this.venta.cotizacion == 1 ? true : false;
    //   this.apiService.read(endpoint, +this.route.snapshot.paramMap.get('id')!).subscribe(venta => {
    //     this.venta = venta;
    //     this.venta.cotizacion = isCotizacion ? 1 : 0;
    //     this.venta.cobrar_impuestos = (this.venta.iva > 0) ? true : false;
    //   }, error => {
    //     this.alertService.error(error);
    //     this.loading = false;
    //   });
    // }

    if (this.route.snapshot.paramMap.get('id')) {
      this.editar = true;
      const endpoint = this.venta.cotizacion == 1 ? 'cotizacion/' : 'venta/';
      const isCotizacion = this.venta.cotizacion == 1 ? true : false;
      this.apiService
        .read(endpoint, +this.route.snapshot.paramMap.get('id')!)
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
        });
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
            this.venta.cobrar_impuestos = this.venta.iva > 0 ? true : false;
            this.venta.fecha = this.apiService.date();
            this.venta.fecha_pago = this.apiService.date();
            this.venta.id_documento = null;
            this.venta.correlativo = null;
            this.venta.tipo_dte = null;
            this.venta.numero_control = null;
            this.venta.codigo_generacion = null;
            this.venta.sello_mh = null;
            this.venta.dte = null;
            this.venta.dte_invalidacion = null;
            this.venta.id = null;
            this.venta.detalles.forEach((detalle: any) => {
              detalle.id = null;
            });
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
        .read(
          'cotizacionVentas/',
          +this.route.snapshot.queryParamMap.get('id_venta')!
        )
        .subscribe(
          (venta) => {
            this.venta = venta;
            this.venta.cobrar_impuestos = this.venta.cobrar_impuestos;
            this.venta.retencion = this.venta.aplicar_retencion;
            this.venta.fecha = this.apiService.date();
            this.venta.fecha_pago = this.apiService.date();
            this.venta.id_documento = null;
            this.venta.correlativo = null;
            this.venta.estado = 'Pagada';
            this.venta.observaciones = '';
            this.venta.terminos_de_venta = '';
            this.venta.cotizacion = 0;
            this.venta.num_cotizacion = this.venta.id;
            this.venta.id = null;
            this.venta.detalles.forEach((detalle: any) => {
              if (detalle.codigo_combo) {
                detalle.descripcion = detalle.combo.nombre;
                detalle.detalles = detalle.combo.detalles;
              } else {
                detalle.descripcion = detalle.producto.nombre;
              }
              detalle.id = null;
            });

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

    this.venta.impuestos.forEach((impuesto: any) => {
      if (this.venta.cobrar_impuestos) {
        impuesto.monto = this.venta.sub_total * (impuesto.porcentaje / 100);
      } else {
        impuesto.monto = 0;
      }
    });

    // if (this.venta.cobrar_impuestos && this.venta.impuestos) {
    //   this.venta.impuestos.forEach((impuesto: any) => {
    //     impuesto.monto = this.venta.sub_total * (impuesto.porcentaje / 100);
    //   });
    // }

    this.venta.iva = parseFloat(
      this.sumPipe.transform(this.venta.impuestos, 'monto')
    ).toFixed(4);
    this.venta.descuento = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'descuento')
    ).toFixed(4);
    this.venta.total_costo = parseFloat(
      this.sumPipe.transform(this.venta.detalles, 'total_costo')
    ).toFixed(4);
    this.venta.total = (
      parseFloat(this.venta.sub_total) +
      parseFloat(this.venta.iva) +
      parseFloat(this.venta.cuenta_a_terceros) +
      parseFloat(this.venta.exenta) +
      parseFloat(this.venta.no_sujeta) +
      parseFloat(this.venta.iva_percibido) -
      parseFloat(this.venta.iva_retenido)
    ).toFixed(4);
  }

  // Cliente
  public setCliente(cliente: any) {
    if (!this.venta.id_cliente) {
      this.clientes.push(cliente);
    }
    this.venta.id_cliente = cliente.id;
    if (cliente.tipo_contribuyente == 'Grande') {
      this.venta.retencion = 1;
      this.sumTotal();
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
    if (this.venta.credito) {
      this.venta.estado = 'Pendiente';
      this.venta.fecha_pago = moment().add(1, 'month').format('YYYY-MM-DD');
    } else {
      this.venta.estado = 'Pagada';
      this.venta.fecha_pago = moment().format('YYYY-MM-DD');
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
    console.log(this.venta);
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

    this.apiService.store('facturacion', this.venta).subscribe(
      (venta) => {
        // Si es cotización
        // if (this.facturarCotizacion) {
        //   this.apiService.read('venta/', +this.route.snapshot.queryParamMap.get('id_venta')!).subscribe(venta => {
        //     venta.estado = 'Facturada';
        //     this.apiService.store('venta', venta).subscribe(venta => {

        //     }, error => { this.alertService.error(error); this.saving = false; });
        //   }, error => { this.alertService.error(error); this.saving = false; });
        // }

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
                .subscribe(
                  (venta) => {},
                  (error) => {
                    this.alertService.error(error);
                  }
                );
            }
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
        this.enviarDTE();
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

  toggleDiv(): void {
    this.opAvanzadas = !this.opAvanzadas; // Cambiar entre true y false
  }
  toggleDivFacturacion(): void {
    this.opAvanzadasFacturacion = !this.opAvanzadasFacturacion; // Cambiar entre true y false
  }

  // updateCustomFields() {
  //   this.activeCustomFields = this.customFields.data
  //     .filter((f: any) => this.selectedCustomFields.includes(f.id));
  // }

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
}
