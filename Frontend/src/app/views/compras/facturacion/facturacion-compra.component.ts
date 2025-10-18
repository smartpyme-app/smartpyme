import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Subject, Observable } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

import * as moment from 'moment';
import { DetalleComprasComponent } from '@views/reportes/compras/detalle/detalle-compras.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-facturacion-compra',
  templateUrl: './facturacion-compra.component.html',
  providers: [SumPipe]
})

export class FacturacionCompraComponent implements OnInit {

    public compra: any= {};
    public detalle: any = {};
    public proveedores:any = [];
    public proyectos:any = [];
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public sucursales:any = [];
    public bodegas:any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public supervisor:any = {};
    public loading = false;
    public saving = false;
    public duplicarcompra = false;
    public facturarCotizacion = false;
    public imprimir:boolean = false;
    public comprainternacional = false;
    public showAuthModal = false;
    public jsonContent: string = '';
    public processingJson: boolean = false;
    public productosNoEncontrados: any[] = [];
    public modalProductos!: BsModalRef;
    public productosEncontrados: any[] = []; // Cache de productos ya encontrados
    public buscandoProductos: boolean = false;

    // Propiedades para la búsqueda dinámica
    public searchTerm: string = '';
    public searchResults: any[] = [];
    public searchLoading: boolean = false;
    public searchProductos$ = new Subject<string>();

    cotizacion: any = {};
    // Propiedades para crear productos nuevos
    public modalCrearProducto!: BsModalRef;
    public nuevoProducto: any = {};
    public creandoProducto: boolean = false;
    public categorias: any[] = [];


    modalRef!: BsModalRef;
    modalCredito!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('mcredito')
    public creditoTemplate!: TemplateRef<any>;

    @ViewChild('productosAjuste')
    public productosAjusteTemplate!: TemplateRef<any>;



    constructor(
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };

        // Configurar búsqueda dinámica
        this.searchProductos$.pipe(
            debounceTime(300), // Esperar 300ms después de que el usuario deje de escribir
            distinctUntilChanged(), // Solo buscar si el término cambió
            switchMap(term => {
                if (!term || term.length < 2) {
                    return of([]);
                }
                this.searchLoading = true;
                this.searchTerm = term;

                return this.apiService.store('productos/buscar-modal', {
                    termino: term,
                    id_empresa: this.apiService.auth_user().id_empresa,
                    limite: 15
                }).pipe(
                    catchError(error => {
                        console.error('Error en búsqueda:', error);
                        return of([]);
                    })
                );
            })
        ).subscribe(results => {
            this.searchResults = results || [];
            this.searchLoading = false;
        });

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
        }, error => {this.alertService.error(error);});

      this.apiService.getAll('banco/cuentas/list').subscribe(bancos => {
          this.bancos = bancos;
      }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => {
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error);});

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
        // console.log(this.compra);
    }

    public updatecompra(compra:any) {
        this.compra = compra;
        this.sumTotal();
    }

    public selectTipoDocumento(){
        if(this.compra.tipo_documento == 'Sujeto excluido'){
            let documento = this.documentos.find((x:any) => x.nombre == this.compra.tipo_documento);
            // console.log(documento);
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
  openJsonImport(template: TemplateRef<any>) {
    this.jsonContent = '';
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  /**
   * Maneja la selección de archivo JSON
   */
  handleFileInput(event: any) {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e: any) => {
        this.jsonContent = e.target.result;
      };
      reader.readAsText(file);
    }
  }

  processJsonData() {
    this.processingJson = true;

    try {
      // Parsear el JSON
      const jsonData = JSON.parse(this.jsonContent);

      // Mapear los datos del JSON al modelo de Compra
      this.mapJsonToCompra(jsonData);

      // Cerrar el modal y mostrar mensaje de éxito
      this.modalRef?.hide();
      this.alertService.success(
        'Datos importados',
        'Los datos del JSON han sido importados exitosamente.'
      );
    } catch (error) {
      this.alertService.error('Error al procesar el JSON: ' + error);
    } finally {
      this.processingJson = false;
    }
  }

  getTipoDocumento(tipoDte: string) {
    const tiposDte = {
      '01': 'Factura',
      '03': 'Crédito fiscal',
      '05': 'Nota de débito',
      '06': 'Nota de crédito',
      '07': 'Comprobante de retención',
      '11': 'Factura de exportación',
      '14': 'Sujeto excluido'
    };
    return tiposDte[tipoDte as keyof typeof tiposDte] || 'Factura';
  }

      getProveedor(proveedor: any) {
        //console.log('Buscando proveedor del DTE:', proveedor);
        let proveedorEncontrado = null;

        // 1. Buscar primero por NIT (prioridad más alta)
        if (proveedor.nit) {
          proveedorEncontrado = this.searchNit(proveedor.nit);
          if (proveedorEncontrado) {
            console.log('Proveedor encontrado por NIT:', proveedorEncontrado.nombre_empresa || proveedorEncontrado.nombre);
            return proveedorEncontrado;
          }
        }

        // 2. Buscar por NRC si no se encontró por NIT
        if (proveedor.nrc && !proveedorEncontrado) {
          proveedorEncontrado = this.searchNrc(proveedor.nrc);
          if (proveedorEncontrado) {
            console.log('Proveedor encontrado por NRC:', proveedorEncontrado.nombre_empresa || proveedorEncontrado.nombre);
            return proveedorEncontrado;
          }
        }

        // 3. Buscar por DUI si no se encontró por NIT ni NRC
        if (proveedor.dui && !proveedorEncontrado) {
          proveedorEncontrado = this.searchDui(proveedor.dui);
          if (proveedorEncontrado) {
            console.log('Proveedor encontrado por DUI:', proveedorEncontrado.nombre_empresa || proveedorEncontrado.nombre);
            return proveedorEncontrado;
          }
        }

        // 4. Como último recurso, buscar por nombre
        if (!proveedorEncontrado && proveedor.nombre) {
          proveedorEncontrado = this.searchNombre(proveedor.nombre);
          if (proveedorEncontrado) {
            console.log('Proveedor encontrado por nombre:', proveedorEncontrado.nombre_empresa || proveedorEncontrado.nombre);
            return proveedorEncontrado;
          }
        }

        // Si no encuentra por ningún método, NO se selecciona nada
        if (!proveedorEncontrado) {
          console.log('No se encontró proveedor con los datos:', {
            nit: proveedor.nit,
            nrc: proveedor.nrc,
            dui: proveedor.dui,
            nombre: proveedor.nombre
          });
        }

        return proveedorEncontrado;
      }

  searchNit(nit: string) {
    // console.log('Buscando proveedor por NIT:', nit);
    // console.log('Lista de proveedores:', this.proveedores);

    let proveedor = this.proveedores.find((proveedor: any) => {
      // console.log('Comparando NIT:', proveedor.nit, 'con:', nit);
      return proveedor.nit === nit || proveedor.nit == nit;
    });

    // console.log('Proveedor encontrado por NIT:', proveedor);
    return proveedor;
  }

      searchNombre(nombre: string) {
        // console.log('Buscando proveedor por nombre:', nombre);

        let proveedor = this.proveedores.find((proveedor: any) => {
          //console.log('Comparando nombres:', proveedor.nombre_empresa, 'con:', nombre);
          return proveedor.nombre_empresa === nombre ||
                 proveedor.nombre_empresa == nombre ||
                 proveedor.nombre === nombre ||
                 proveedor.nombre == nombre;
        });

        // console.log('Proveedor encontrado por nombre:', proveedor);
        return proveedor;
      }

      searchNrc(nrc: string) {
        // console.log('Buscando proveedor por NRC:', nrc);
        // console.log('Lista de proveedores:', this.proveedores);

        let proveedor = this.proveedores.find((proveedor: any) => {
          // console.log('Comparando NRC:', proveedor.ncr, 'con:', nrc);
          return proveedor.ncr === nrc || proveedor.ncr == nrc;
        });

        // console.log('Proveedor encontrado por NRC:', proveedor);
        return proveedor;
      }

      searchDui(dui: string) {
        // console.log('Buscando proveedor por DUI:', dui);
        // console.log('Lista de proveedores:', this.proveedores);

        let proveedor = this.proveedores.find((proveedor: any) => {
          // console.log('Comparando DUI:', proveedor.dui, 'con:', dui);
          return proveedor.dui === dui || proveedor.dui == dui;
        });

        // console.log('Proveedor encontrado por DUI:', proveedor);
        return proveedor;
      }

  async procesarProductosDTE(cuerpoDocumento: any[]) {
    this.compra.detalles = [];
    this.productosNoEncontrados = [];
    this.buscandoProductos = true;

    let todosEncontrados = true;

    // Procesar cada item de forma secuencial para evitar sobrecarga
    for (const item of cuerpoDocumento) {
      try {
        const productoEncontrado = await this.buscarProductoOptimizado(item.codigo, item.descripcion);

        if (productoEncontrado) {
          // Producto encontrado, agregar al detalle
          const detalle = this.crearDetalleDesdeItem(item, productoEncontrado);
          this.compra.detalles.push(detalle);
          // Guardar en cache
          this.productosEncontrados.push(productoEncontrado);
        } else {
          // Producto no encontrado
          this.productosNoEncontrados.push({
            numItem: item.numItem,
            codigo: item.codigo,
            descripcion: item.descripcion,
            cantidad: item.cantidad,
            precioUni: item.precioUni,
            productoSeleccionado: null,
            sugerencias: [] // Para almacenar sugerencias de búsqueda
          });
          todosEncontrados = false;
        }
      } catch (error) {
        console.error('Error buscando producto:', error);
        todosEncontrados = false;
      }
    }

    this.buscandoProductos = false;

    if (!todosEncontrados) {
      // Cargar sugerencias para productos no encontrados
      await this.cargarSugerenciasProductos();
      this.mostrarModalAjusteProductos();
    } else {
      this.sumTotal();
      this.alertService.success('Productos importados', 'Todos los productos fueron reconocidos automáticamente.');
    }
  }

  async buscarProductoOptimizado(codigo: string, descripcion: string): Promise<any> {
    // Primero verificar cache local
    const enCache = this.productosEncontrados.find(p =>
      p.cod_proveed_prod === codigo ||
      (p.nombre && descripcion && p.nombre.toLowerCase().includes(descripcion.toLowerCase()))
    );

    if (enCache) return enCache;

    try {
      // Búsqueda por código de proveedor
      if (codigo) {
        const porCodigo = await this.buscarPorCodigoProveedor(codigo).toPromise();
        if (porCodigo && porCodigo.length > 0) {
          return porCodigo[0];
        }
      }

      // Búsqueda por nombre si no se encontró por código
      if (descripcion) {
        const porNombre = await this.buscarPorNombre(descripcion).toPromise();
        if (porNombre && porNombre.length > 0) {
          return porNombre[0];
        }
      }

      return null;
    } catch (error) {
      console.error('Error en búsqueda optimizada:', error);
      return null;
    }
  }

  buscarPorCodigoProveedor(codigo: string) {
    return this.apiService.store('productos/buscar-por-codigo-proveedor', {
      cod_proveed_prod: codigo,
      id_empresa: this.apiService.auth_user().id_empresa
    });
  }

  // Servicio para buscar por nombre específico
  buscarPorNombre(nombre: string) {
    return this.apiService.store('productos/buscar-por-nombre', {
      nombre: nombre,
      id_empresa: this.apiService.auth_user().id_empresa,
      limite: 5 // Limitar resultados para performance
    });
  }

  async cargarSugerenciasProductos() {
    for (const item of this.productosNoEncontrados) {
      try {
        // Búsqueda amplia para sugerencias
        const sugerencias = await this.buscarSugerencias(item.descripcion).toPromise();
        item.sugerencias = sugerencias || [];
      } catch (error) {
        console.error('Error cargando sugerencias para:', item.descripcion, error);
        item.sugerencias = [];
      }
    }
  }

  buscarSugerencias(termino: string) {
    // Dividir el término en palabras y buscar coincidencias parciales
    const palabras = termino.toLowerCase().split(' ').filter(p => p.length > 2);
    const terminoBusqueda = palabras.join(' ');

    return this.apiService.store('productos/buscar-sugerencias', {
      termino: terminoBusqueda,
      palabras: palabras,
      id_empresa: this.apiService.auth_user().id_empresa,
      limite: 10
    });
  }

  buscarProductosModal(termino: string) {
    if (!termino || termino.length < 2) {
      return Promise.resolve([]);
    }

    return this.apiService.store('productos/buscar-modal', {
      termino: termino,
      id_empresa: this.apiService.auth_user().id_empresa,
      limite: 20
    }).toPromise();
  }

  crearDetalleDesdeItem(item: any, producto: any) {
    // Calcular total de forma segura
    const ventaGravada = parseFloat(item.ventaGravada) || 0;
    const ventaExenta = parseFloat(item.ventaExenta) || 0;
    const ventaNoSuj = parseFloat(item.ventaNoSuj) || 0;
    const totalCalculado = ventaGravada + ventaExenta + ventaNoSuj;

    // Si no hay total del DTE, calcular basado en cantidad y precio
    const cantidad = parseFloat(item.cantidad) || 0;
    const precio = parseFloat(item.precioUni) || 0;
    const descuento = parseFloat(item.montoDescu) || 0;
    const totalFinal = totalCalculado > 0 ? totalCalculado : (cantidad * precio - descuento);

    return {
      id: null,
      id_producto: producto.id,
      nombre: producto.nombre,
      nombre_producto: producto.nombre, // Campo requerido por el template
      descripcion: producto.descripcion || item.descripcion,
      cantidad: cantidad,
      precio: precio,
      costo: producto.costo || 0, // Campo requerido por el template
      descuento: descuento,
      total: totalFinal,
      total_costo: cantidad * (producto.costo || 0),
      codigo: producto.codigo,
      marca: producto.marca,
      tipo: producto.tipo,
      img: producto.img || 'default-product.png', // Imagen por defecto
      // Para servicios, no aplicamos stock
      stock: producto.tipo === 'Servicio' ? null : (producto.stock || 0)
    };
  }

  async confirmarAjusteProductos() {
    let todosAsignados = true;

    this.productosNoEncontrados.forEach((itemNoEncontrado: any) => {
      if (itemNoEncontrado.productoSeleccionado && itemNoEncontrado.productoSeleccionado.id) {
        const detalle = this.crearDetalleDesdeItem(itemNoEncontrado, itemNoEncontrado.productoSeleccionado);
        this.compra.detalles.push(detalle);
        // Agregar al cache para futuras búsquedas
        this.productosEncontrados.push(itemNoEncontrado.productoSeleccionado);
      } else {
        todosAsignados = false;
      }
    });

    if (!todosAsignados) {
      this.alertService.error('Por favor, seleccione un producto para todos los items no encontrados.');
      return;
    }

    // Cerrar modal y recalcular
    this.modalProductos.hide();
    this.productosNoEncontrados = [];
    this.sumTotal();

    this.alertService.success('Productos asignados', 'Los productos han sido asignados correctamente.');
  }

  mostrarModalAjusteProductos() {
    this.modalProductos = this.modalService.show(this.productosAjusteTemplate, {
      class: 'modal-xl', // Modal más grande para mejor visualización
      backdrop: 'static'
    });
  }

  // Cancelar ajuste
  cancelarAjusteProductos() {
    this.modalProductos.hide();
    this.productosNoEncontrados = [];

    // Limpiar los totales ya que se cancela la importación
    this.compra.detalles = [];
    this.compra.sub_total = 0;
    this.compra.iva = 0;
    this.compra.total = 0;
    this.compra.descuento = 0;
    this.compra.percepcion = 0;
    this.compra.iva_retenido = 0;
    this.compra.renta_retenida = 0;
    this.compra.total_costo = 0;

    this.alertService.warning('Importación cancelada', 'La importación de productos ha sido cancelada.');
  }

  // Abrir ajuste manual
  abrirAjusteManual() {
    this.modalProductos.hide();
    this.productosNoEncontrados = [];

    // Limpiar los totales ya que se cancela la importación
    this.compra.detalles = [];
    this.compra.sub_total = 0;
    this.compra.iva = 0;
    this.compra.total = 0;
    this.compra.descuento = 0;
    this.compra.percepcion = 0;
    this.compra.iva_retenido = 0;
    this.compra.renta_retenida = 0;
    this.compra.total_costo = 0;

    this.alertService.info('Ajuste manual', 'Puede agregar productos manualmente usando el botón "Agregar Producto" en la parte superior.');
  }

  // Métodos helper
  getProductosAsignados(): number {
    const asignados = this.productosNoEncontrados.filter(item => {
      const tieneProducto = item.productoSeleccionado && item.productoSeleccionado.id;
      // console.log(`Item ${item.numItem}:`, {
      //     productoSeleccionado: item.productoSeleccionado,
      //     tieneId: item.productoSeleccionado?.id,
      //     asignado: tieneProducto
      // });
      return tieneProducto;
    }).length;
    // console.log('Total asignados:', asignados);
    return asignados;
  }

  getProductosPendientes(): number {
    const pendientes = this.productosNoEncontrados.filter(item => {
      const noTieneProducto = !item.productoSeleccionado || !item.productoSeleccionado.id;
      return noTieneProducto;
    }).length;
    // console.log('Total pendientes:', pendientes);
    return pendientes;
  }

  // Método para comparar productos en ng-select
  compareProductos(producto1: any, producto2: any): boolean {
    if (!producto1 || !producto2) return false;
    return producto1.id === producto2.id;
  }

  // Método para manejar la selección de productos
  onProductoSeleccionado(item: any, producto: any) {
    // console.log('Producto seleccionado:', producto);
    item.productoSeleccionado = producto;
    // Forzar detección de cambios
    setTimeout(() => {
      //console.log('Estado actualizado:', item.productoSeleccionado);
    }, 0);
  }

  // Método para actualizar mapJsonToCompra con la nueva lógica
  mapJsonToCompra(jsonData: any) {
    if (jsonData.identificacion.fecEmi) {
      this.compra.fecha = jsonData.identificacion.fecEmi;
    }

    this.compra.id_usuario = this.apiService.auth_user().id;
    this.compra.id_bodega = this.apiService.auth_user().id_bodega;

    if (jsonData.identificacion.tipoDte) {
      this.compra.tipo_documento = this.getTipoDocumento(jsonData.identificacion.tipoDte) || 'Factura';
    }

    let proveedor = this.getProveedor(jsonData.emisor);
    if(proveedor && proveedor.id){
      this.compra.id_proveedor = proveedor.id;
      //console.log('Proveedor asignado:', proveedor.nombre_empresa || proveedor.nombre, 'ID:', proveedor.id);
    } else {
      console.log('No se pudo asignar proveedor. Proveedor encontrado:', proveedor);
    }

    // Procesar totales del resumen
    if (jsonData.resumen) {
      this.compra.sub_total = jsonData.resumen.subTotal || jsonData.resumen.subTotalVentas || 0;
      this.compra.total = jsonData.resumen.totalPagar || jsonData.resumen.montoTotalOperacion || 0;

      // Procesar IVA
      if (jsonData.resumen.tributos) {
        const iva = jsonData.resumen.tributos.find((t: any) => t.codigo === '20');
        if (iva) {
          this.compra.iva = iva.valor;
          this.compra.cobrar_impuestos = true;
        }
      }
    }

    // Procesar productos del cuerpoDocumento de forma optimizada
    if (jsonData.cuerpoDocumento && jsonData.cuerpoDocumento.length > 0) {
      this.procesarProductosDTE(jsonData.cuerpoDocumento);
    }
  }

  // Método para activar la búsqueda desde el template
  onSearchProducts(term: string) {
    this.searchProductos$.next(term);
  }

    // Métodos para crear productos nuevos
    openModalCrearProducto(template: TemplateRef<any>) {
        this.nuevoProducto = {
            nombre: '',
            tipo: '',
            codigo: '',
            cod_proveed_prod: '',
            costo: 0,
            precio: 0,
            marca: '',
            stock: 0,
            descripcion: '',
            id_categoria: '',
            id_empresa: this.apiService.auth_user().id_empresa,
            id_usuario: this.apiService.auth_user().id
        };
        this.creandoProducto = false;

        // Cargar categorías si no están cargadas
        if (this.categorias.length === 0) {
            this.cargarCategorias();
        }

        this.modalCrearProducto = this.modalService.show(template, { class: 'modal-lg' });
    }

    cargarCategorias() {
        this.apiService.getAll('categorias/list').subscribe(
            categorias => {
                this.categorias = categorias;
            },
            error => {
                console.error('Error cargando categorías:', error);
                this.alertService.error('Error cargando categorías');
            }
        );
    }

    crearProducto() {
        // Debug: mostrar valores actuales
        console.log('Valores del formulario:', {
            nombre: this.nuevoProducto.nombre,
            tipo: this.nuevoProducto.tipo,
            costo: this.nuevoProducto.costo,
            id_categoria: this.nuevoProducto.id_categoria
        });

        // Validación más específica
        const errores = [];

        if (!this.nuevoProducto.nombre || this.nuevoProducto.nombre.trim() === '') {
            errores.push('Nombre');
        }

        if (!this.nuevoProducto.tipo || this.nuevoProducto.tipo === '') {
            errores.push('Tipo');
        }

        if (!this.nuevoProducto.costo || this.nuevoProducto.costo <= 0) {
            errores.push('Costo');
        }

        if (!this.nuevoProducto.id_categoria || this.nuevoProducto.id_categoria === '') {
            errores.push('Categoría');
        }

        if (errores.length > 0) {
            this.alertService.error(`Por favor, complete los campos obligatorios: ${errores.join(', ')}`);
            return;
        }

        this.creandoProducto = true;

        // Preparar datos para el backend
        const datosProducto = {
            nombre: this.nuevoProducto.nombre,
            tipo: this.nuevoProducto.tipo,
            codigo: this.nuevoProducto.codigo || null,
            cod_proveed_prod: this.nuevoProducto.cod_proveed_prod || null,
            costo: parseFloat(this.nuevoProducto.costo),
            precio: parseFloat(this.nuevoProducto.precio) || parseFloat(this.nuevoProducto.costo),
            marca: this.nuevoProducto.marca || null,
            stock: this.nuevoProducto.stock || 0,
            descripcion: this.nuevoProducto.descripcion || null,
            id_empresa: this.apiService.auth_user().id_empresa,
            id_usuario: this.apiService.auth_user().id,
            id_categoria: parseInt(this.nuevoProducto.id_categoria),
            medida: 'Unidad' // Medida por defecto
        };

        // Crear el producto en el backend usando la ruta correcta
        this.apiService.store('producto', datosProducto).subscribe(
            (productoCreado: any) => {
                // NO agregar automáticamente al DTE
                // El producto solo estará disponible para asignar en la consolidación

                // Cerrar el modal
                this.modalCrearProducto.hide();

                // Mostrar mensaje de éxito
                this.alertService.success('Producto creado', 'El producto ha sido creado exitosamente. Estará disponible para asignar en la consolidación.');

                this.creandoProducto = false;
            },
            (error) => {
                this.alertService.error('Error al crear el producto: ' + error);
                this.creandoProducto = false;
            }
        );
    }





}
