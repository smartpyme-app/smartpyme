import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import { DetalleComprasComponent } from '@views/reportes/compras/detalle/detalle-compras.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-facturacion-compra',
  templateUrl: './facturacion-compra.component.html',
  providers: [SumPipe]
})

export class FacturacionCompraComponent implements OnInit {

  public compra: any = {};
  public detalle: any = {};
  public proveedores: any = [];
  public proyectos: any = [];
  public usuarios: any = [];
  public documentos: any = [];
  public formaPagos: any = [];
  public sucursales: any = [];
  public bodegas: any = [];
  public impuestos: any = [];
  public bancos:any = [];
  public supervisor: any = {};
  public loading = false;
  public saving = false;
  public duplicarcompra = false;
  public facturarCotizacion = false;
  public imprimir: boolean = false;
  public comprainternacional = false;
  public showAuthModal = false;


  cotizacion: any = {};
  modalRef!: BsModalRef;
  modalCredito!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('mcredito')
    public creditoTemplate!: TemplateRef<any>;


    constructor(
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

  ngOnInit() {

        this.cargarDatosIniciales();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error);});

    this.apiService.getAll('usuarios/list').subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('banco/cuentas/list').subscribe(bancos => {
        this.bancos = bancos;
    }, error => {this.alertService.error(error);});

    this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => {
      this.formaPagos = formaPagos;
    }, error => { this.alertService.error(error); });

        this.apiService.getAll('impuestos').subscribe(impuestos => {
            this.impuestos = impuestos;
            this.compra.impuestos = this.impuestos;
            this.sumTotal();

        }, error => {this.alertService.error(error);});

        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('proyectos/list').subscribe(proyectos => {
            this.proyectos = proyectos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public cargarDocumentos(){
        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
            this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.compra.id_sucursal);
            if(this.compra.cotizacion == 1){
                this.documentos = this.documentos.filter((x:any) => x.nombre == 'Orden de compra');
                let documento = this.documentos.find((x:any) => x.nombre == 'Orden de compra');
                if(documento){
                    this.compra.tipo_documento = documento.nombre;
                    this.compra.referencia = documento.correlativo;
                }
            }else{
                this.documentos = this.documentos.filter((x:any) => x.nombre != 'Cotización' && x.nombre != 'Orden de compra');
            }
        }, error => {this.alertService.error(error);});
    }

    public cargarDatosIniciales(){
        this.compra = {};
        this.compra.fecha = this.apiService.date();
        this.compra.fecha_pago = this.apiService.date();
        this.compra.forma_pago = 'Efectivo';
        this.compra.tipo = 'Interna';
        this.compra.estado = 'Pagada';
        this.compra.condicion = 'Contado';
        this.compra.tipo_clasificacion = 'Costo';
        this.compra.tipo_operacion = 'Gravada';
        this.compra.tipo_costo_gasto = 'Costo artículos producidos/comprados interno';
        this.compra.tipo_sector = this.apiService.auth_user().empresa.tipo_sector ?? null;
        this.compra.tipo_documento = 'Factura';
        this.compra.detalle_banco = '';
        this.compra.id_proveedor = '';
        this.compra.detalles = [];
        this.compra.descuento = 0;
        this.compra.sub_total = 0;
        this.compra.percepcion = 0;
        this.compra.cotizacion = 0;
        this.compra.iva_retenido = 0;
        this.compra.iva = 0;
        this.compra.total_costo = 0;
        this.compra.total = 0;
        this.compra.fob_tot = 0;
        this.detalle = {};
        this.compra.cobrar_impuestos = (this.apiService.auth_user().empresa.cobra_iva == 'Si') ? true : false;
        this.compra.cobrar_percepcion = false;
        this.compra.id_bodega = this.apiService.auth_user().id_bodega;
        this.compra.id_usuario = this.apiService.auth_user().id;
        this.compra.id_vendedor = this.apiService.auth_user().id_empleado;
        this.compra.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.compra.id_empresa = this.apiService.auth_user().id_empresa;
        this.compra.incoterms = "FOB";
        this.compra.es_retaceo = false;
        let corte = JSON.parse(sessionStorage.getItem('worder_corte')!);
        if (corte) {
            this.compra.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
            this.compra.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id_caja;
            this.compra.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        }

        if (this.route.snapshot.queryParamMap.get('cotizacion')) {
            this.compra.cotizacion = 1;
            this.compra.estado = 'Pendiente';
        }

        this.route.params.subscribe((params:any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('compra/', params.id).subscribe(compra => {
                    this.compra = compra;
                    this.compra.cobrar_impuestos = (this.compra.iva > 0) ? true : false;
                    this.compra.cobrar_percepcion = (this.compra.percepcion > 0) ? true : false;
                    this.loading = false;
                }, error => {this.alertService.error(error); this.loading = false;});
            }
        });

        // Duplicar compra

        if (this.route.snapshot.queryParamMap.get('recurrente')! && this.route.snapshot.queryParamMap.get('id_compra')!) {
            this.duplicarcompra = true;
            this.apiService.read('compra/', +this.route.snapshot.queryParamMap.get('id_compra')!).subscribe(compra => {
                this.compra = compra;
                this.compra.fecha = this.apiService.date();
                this.compra.fecha_pago = this.apiService.date();
                this.compra.cobrar_impuestos = (this.compra.iva > 0) ? true : false;
                this.compra.cobrar_percepcion = (this.compra.percepcion > 0) ? true : false;
                this.compra.id = null;
                this.compra.tipo_documento = null;
                this.compra.referencia = null;
                this.compra.detalles.forEach((detalle:any) => {
                    detalle.id = null;
                });
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
            this.compra.id_proyecto = +this.route.snapshot.queryParamMap.get('id_proyecto')!;
        }

    // Facturar cotizacion
    if (this.route.snapshot.queryParamMap.get('facturar_cotizacion')! && this.route.snapshot.queryParamMap.get('id_compra')!) {
      this.facturarCotizacion = true;
      this.apiService.read('orden-de-compra/', +this.route.snapshot.queryParamMap.get('id_compra')!).subscribe(compra => {
        this.cotizacion = Object.assign({}, {
          ...compra, cotizacion: 1,
          detalles: compra.detalles.map((_d: any) => {
            return {
              cantidad: _d.cantidad,
              costo: _d.costo,
              descuento: _d.descuento,
              id_producto: _d.id_producto,
              producto: _d.producto,
              total: _d.total,
              img: _d.img,
              cantidad_procesada: _d.cantidad_procesada,
              nombre_producto: _d.nombre_producto,
            }
          })
        });
        this.compra = compra;
        this.compra.fecha = this.apiService.date();
        this.compra.fecha_pago = this.apiService.date();
        this.compra.tipo_documento = null;
        this.compra.referencia = null;
        this.compra.estado = 'Pagada';
        this.compra.cotizacion = 0;
        this.compra.num_orden_compra = this.compra.id;
        this.compra.id = null;
        this.compra.detalles.forEach((detalle: any) => {
          detalle.id = null;
        });
      }, error => { this.alertService.error(error); this.loading = false; });
    }

        this.cargarDocumentos();
    }

  public sumTotal() {

    if (this.compra.cobrar_impuestos && (!this.compra.impuestos || this.compra.impuestos.length === 0)) {
      this.alertService.warning(
        'Configuración requerida',
        'Debe configurar los impuestos en el módulo de finanzas antes de poder incluir IVA'
      );
      this.compra.cobrar_impuestos = false;
      return;
    }
    this.compra.sub_total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))).toFixed(2);
    this.compra.percepcion = this.compra.cobrar_percepcion ? this.compra.sub_total * 0.01 : 0;
    this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0;
    this.compra.renta_retenida = this.compra.renta ? this.compra.sub_total * 0.10 : 0;

        if(this.compra.cobrar_impuestos){
            this.compra.iva = ( this.compra.sub_total * (this.apiService.auth_user().empresa.iva / 100) ).toFixed(2);
        }else{
            this.compra.iva = 0;
        }

        this.compra.descuento = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'descuento'))).toFixed(2);
        this.compra.total_costo = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total_costo'))).toFixed(2);
        this.compra.total = (parseFloat(this.compra.sub_total) + parseFloat(this.compra.iva) + parseFloat(this.compra.percepcion) - parseFloat(this.compra.iva_retenido) - parseFloat(this.compra.renta_retenida)).toFixed(2);

        // Asignar tipoOperacion según los detalles
        if (this.compra.cobrar_impuestos) {
          this.compra.tipo_operacion = 'Gravada'; // Aplica IVA
        } else {
          this.compra.tipo_operacion = 'No Gravada'; // No aplica IVA
        }

    }

    // proveedor
    public setProveedor(proveedor:any){
        if(!this.compra.id_proveedor){
            this.proveedores.push(proveedor);
        }
        this.compra.id_proveedor = proveedor.id;
        if(proveedor.tipo_contribuyente == "Grande") {
            this.compra.retencion = 1;
            this.sumTotal();
        }
    }

    // Proyecto
    public setProyecto(proyecto:any){
        if(!this.compra.id_proyecto){
            this.proyectos.push(proyecto);
        }
        this.compra.id_proyecto = proyecto.id;
    }

    public setCredito(){
        if(this.compra.credito){
            this.compra.estado = 'Pendiente';
            this.compra.fecha_pago = moment().add(1, 'month').format('YYYY-MM-DD');
        }else{
            this.compra.estado = 'Pagada';
            this.compra.fecha_pago = moment().format('YYYY-MM-DD');
        }
    }

    public setConsigna(){
        if(this.compra.consigna){
            this.compra.estado = 'Consigna';
        }else{
            this.setCredito();
        }
    }

    public setBodega(){
        this.compra.id_sucursal = this.bodegas.find((item:any) => item.id == this.compra.id_bodega).id_sucursal;
        console.log(this.compra);
    }

    public updatecompra(compra:any) {
        this.compra = compra;
        this.sumTotal();
    }

    public selectTipoDocumento(){
        if(this.compra.tipo_documento == 'Sujeto excluido'){
            let documento = this.documentos.find((x:any) => x.nombre == this.compra.tipo_documento);
            console.log(documento);
            this.compra.referencia = documento.correlativo;
        }
    }


    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop:'static'});
        }

  async onFacturar() {

    if (this.compra.cobrar_impuestos && (!this.compra.impuestos || this.compra.impuestos.length === 0)) {
      this.alertService.error(
        'Debe configurar los impuestos en el módulo de finanzas antes de poder incluir IVA'
      );
      return;
    }

    let confirm = await Swal.fire({
      title: '¿Estás seguro de procesar la compra?',
      text: 'Se procesara la compra',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, procesar',
      cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;
    if (!this.compra.recibido)
      this.compra.recibido = this.compra.total;
    this.onSubmit();
  }

  // Guardar compra
  // public onSubmit() {
  //   this.saving = true;

  //   if (this.duplicarcompra) {
  //     this.compra.recurrente = false;
  //   }

  //   this.apiService.store('compra/facturacion', this.compra).subscribe(
  //     compra => {
  //       this.saving = false;

  //       // Verificar si la compra está pendiente de autorización
  //       if (compra.estado === 'Pendiente Autorización') {
  //         this.alertService.success(
  //           'Compra pendiente de autorización',
  //           'La compra se ha creado y está esperando aprobación. Recibirá una notificación cuando sea autorizada.'
  //         );
  //         this.router.navigate(['/compras']);
  //         return;
  //       }

  //       // Flujo normal para compras aprobadas
  //       if (this.compra.cotizacion == 1) {
  //         this.router.navigate(['/ordenes-de-compras']);
  //         this.alertService.success('Orden de compra creada', 'La orden de compra fue añadida exitosamente.');
  //       } else {
  //         this.router.navigate(['/compras']);
  //         this.alertService.success('Compra creada', 'La compra fue añadida exitosamente.');

  //         // Generar partida contable solo para compras aprobadas
  //         if (this.apiService.auth_user().empresa.generar_partidas == 'Auto') {
  //           this.apiService.store('contabilidad/partida/compra', compra).subscribe(
  //             compra => {},
  //             error => { this.alertService.error(error); }
  //           );
  //         }
  //       }
  //     },
  //     error => {
  //       this.saving = false;

  //       // Error 403 = primera solicitud de autorización (abre modal)
  //       if (error.status === 403 && error.error?.requires_authorization) {
  //         // El interceptor ya abrió el modal
  //         // No hacer nada más aquí
  //         return;
  //       }

  //       this.alertService.error(error);
  //     }
  //   );
  // }

  // Guardar compra
  public onSubmit() {
    this.saving = true;

    if (this.duplicarcompra) {
      this.compra.recurrente = false;
    }

    console.log('=== COMPRA COMPONENT DEBUG ===');
    console.log('Datos de compra a enviar:', this.compra);

    this.apiService.store('compra/facturacion', this.compra).subscribe(
      compra => {
        this.saving = false;

        // Verificar si la compra está pendiente de autorización
        if (compra.estado === 'Pendiente Autorización') {
          this.alertService.success(
            'Compra pendiente de autorización',
            'La compra se ha creado y está esperando aprobación. Recibirá una notificación cuando sea autorizada.'
          );
          this.router.navigate(['/compras']);
          return;
        }

        // Flujo normal para compras aprobadas
        if (this.compra.cotizacion == 1) {
          this.router.navigate(['/ordenes-de-compras']);
          this.alertService.success('Orden de compra creada', 'La orden de compra fue añadida exitosamente.');
        } else {
          this.router.navigate(['/compras']);
          this.alertService.success('Compra creada', 'La compra fue añadida exitosamente.');

          // Generar partida contable solo para compras aprobadas
          if (this.apiService.auth_user().empresa.generar_partidas == 'Auto') {
            this.apiService.store('contabilidad/partida/compra', compra).subscribe(
              compra => {},
              error => { this.alertService.error(error); }
            );
          }
        }
      },
      error => {
        this.saving = false;

        console.log('=== ERROR RECIBIDO ===');
        console.log('Error status:', error.status);
        console.log('Error object:', error);
        console.log('Error body:', error.error);

        // Error 403 = primera solicitud de autorización (abre modal)
        if (error.status === 403 && error.error?.requires_authorization) {
          console.log('Debería abrir modal de autorización');
          // El interceptor ya abrió el modal
          // No hacer nada más aquí
          return;
        }

        this.alertService.error(error);
      }
    );
  }

    //Limpiar

    public limpiar(){
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
            this.modalRef.hide();
            this.cargarDatosIniciales();
            this.loading = false;
            this.supervisor = {};
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public isColumnEnabled(columnName: string): boolean {
        return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
        }

  toggleDiv(): void {
    this.comprainternacional = !this.comprainternacional; // Cambiar entre true y false
  }
  //   RETACEO

  public updateFOBTotal(detalle: any): void {

    // Actualiza la sumatoria total de FOB
    this.updateDistribucion(detalle);

  }

  // Esta función será llamada cuando el botón sea clicado
  public calcularFOBParaTodos(): void {

    this.compra.fob_tot = 0;
    this.compra.detalles.forEach((detalle: any) => {
      this.compra.fob_tot += parseFloat(detalle.fobTotal);
    });

    this.compra.detalles.forEach((detalle: any) => {
      this.updateFOBTotal(detalle);
    });
  }

  public updateDistribucion(detalle: any): void {

    // Calcular distribución en porcentajecls
    const distribucion = (detalle.fobTotal / this.compra.fob_tot) * 100;
    detalle.distribucion = parseFloat(distribucion.toFixed(2)); // Mantener dos decimales
    this.updateInsuranceForDetails();
    this.updateAereoForDetails();
    this.updateDaiForDetails();
    this.updateGastosForDetails();
    this.updateCIF(detalle);
    this.updateLanded(detalle);
  }


  public updateInsuranceForDetails(): void {
    // Verificamos si `compra.insurance` tiene un valor numérico
    if (this.compra && this.compra.insurance != null && this.compra.detalles) {
      this.compra.detalles.forEach((detalle: any) => {
        // Si `detalle.distribucion` tiene un valor numérico, calculamos `detalle.inland`
        if (detalle.distribucion != null) {
          detalle.insurance = parseFloat((this.compra.insurance * (detalle.distribucion / 100)).toFixed(2));
        }
      });
    }
  }

  public updateAereoForDetails(): void {
    // Verificamos si `compra.aereo` tiene un valor numérico
    if (this.compra && this.compra.aereo != null && this.compra.detalles) {
      this.compra.detalles.forEach((detalle: any) => {
        // Si `detalle.distribucion` tiene un valor numérico, calculamos `detalle.inland`
        if (detalle.distribucion != null) {
          detalle.aereo = parseFloat((this.compra.aereo * (detalle.distribucion / 100)).toFixed(2));
        }
      });
    }
  }

  public updateDaiForDetails(): void {
    // Verificamos si `compra.dai_tot` tiene un valor numérico
    if (this.compra && this.compra.dai_tot != null && this.compra.detalles) {
      this.compra.detalles.forEach((detalle: any) => {
        // Si `detalle.distribucion` tiene un valor numérico, calculamos `detalle.inland`
        if (detalle.distribucion != null) {
          detalle.dai = parseFloat((this.compra.dai_tot * (detalle.distribucion / 100)).toFixed(2));
        }
      });
    }
  }

  public updateGastosForDetails(): void {
    // Verificamos si `compra.dai_tot` tiene un valor numérico
    if (this.compra && this.compra.otro_gastos != null && this.compra.detalles) {
      this.compra.detalles.forEach((detalle: any) => {
        // Si `detalle.distribucion` tiene un valor numérico, calculamos `detalle.inland`
        if (detalle.distribucion != null) {
          detalle.gastos = parseFloat((this.compra.otro_gastos * (detalle.distribucion / 100)).toFixed(2));
        }
      });
    }
  }

  public updateCIF(detalle: any): void {
    // Calcula la suma de insurance, aereo y fobTotal
    detalle.fobTotal = parseFloat(detalle.fobTotal);
    detalle.cif = detalle.fobTotal + detalle.insurance + detalle.aereo;
    detalle.cif = parseFloat(detalle.cif).toFixed(2);
  }

  public updateLanded(detalle: any): void {
    // Calcula la suma de gastos, DAI, CIF
    detalle.cif = parseFloat(detalle.cif);

    detalle.landed = detalle.dai + detalle.gastos + detalle.cif;
    detalle.landed = parseFloat(detalle.landed).toFixed(2);
    detalle.costo_calc = detalle.landed / detalle.cantidad;
  }

  // Calcula el total de un campo específico de todos los detalles
  public calcularTotal(campo: string): number {
    // Verifica si `this.compra` y `this.compra.detalles` existen y no están vacíos
    if (!this.compra || !this.compra.detalles || this.compra.detalles.length === 0) {
      return 0;
    }

    return this.compra.detalles.reduce((total: number, detalle: any) => {
      const valor = parseFloat(detalle[campo]) || 0;
      return total + valor;
    }, 0);
  }

  openModal() {
    console.log('Abriendo modal, showAuthModal antes:', this.showAuthModal);
    this.showAuthModal = true;
    console.log('Abriendo modal, showAuthModal después:', this.showAuthModal);
  }

  closeAuthModal() {
    this.showAuthModal = false;
  }

  onAuthorizationRequested(event: any) {
    console.log('Authorization requested:', event);

    if (event.shouldProceedWithSubmit) {
      // Agregar el id_authorization a la compra para que no requiera autorización de nuevo
      this.compra.id_authorization = event.authorization.id;

      // Ejecutar el submit automáticamente
      this.onSubmit();
    }
  }
}
