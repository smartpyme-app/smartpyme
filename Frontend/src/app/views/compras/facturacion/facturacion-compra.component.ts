import { Component, ElementRef,OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
// ScrollingModule removido temporalmente - se puede agregar cuando se implemente virtual scrolling completo
// import { ScrollingModule } from '@angular/cdk/scrolling';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { SharedDataService } from '@services/shared-data.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { Subject, Observable } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { NgSelectModule } from '@ng-select/ng-select';
import { PipesModule } from '@pipes/pipes.module';
import { CrearProveedorComponent } from '@shared/modals/crear-proveedor/crear-proveedor.component';
import { CrearProyectoComponent } from '@shared/modals/crear-proyecto/crear-proyecto.component';
import { CompraDetallesComponent } from './detalles/compra-detalles.component';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ProveedorSearchService } from '@workers/proveedor-search.service';
import { firstValueFrom } from 'rxjs';

import * as moment from 'moment';
import { DetalleComprasComponent } from '@views/reportes/compras/detalle/detalle-compras.component';
import Swal from 'sweetalert2';
import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import {
    esTipoFacturaElectronicaCompraCr,
    NOMBRE_DOCUMENTO_CR,
} from '@views/ventas/documentos/documento-nombre-options';
import { BsModalRef } from 'ngx-bootstrap/modal';

@Component({
    selector: 'app-facturacion-compra',
    templateUrl: './facturacion-compra.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PipesModule, CrearProveedorComponent, CrearProyectoComponent, CompraDetallesComponent],
    providers: [SumPipe],
  styles: [`
    .ajuste-tabs-json {
      overflow-x: auto;
      overflow-y: hidden;
      flex-wrap: nowrap;
    }
  `],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class FacturacionCompraComponent extends BaseModalComponent implements OnInit {

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
    public override loading = false;
    public override saving = false;
    public duplicarcompra = false;
    public facturarCotizacion = false;
    public imprimir:boolean = false;
    public comprainternacional = false;
    public showAuthModal = false;
    public jsonContent: string = '';
    /** Etiqueta para el bloque de conciliación (nombre de archivo o texto pegado). */
    public jsonImportEtiqueta = 'DTE importado';
    public processingJson: boolean = false;
    /** Bloques de productos sin match, uno por cada JSON que requiera conciliación. */
    public ajusteBloques: { etiqueta: string; items: any[] }[] = [];
    public ajusteTabActivo = 0;
    public modalProductos!: BsModalRef;
    public productosNoEncontrados: any[] = [];
    public productosEncontrados: any[] = [];
    public buscandoProductos: boolean = false;
    public contabilidadHabilitada: boolean = false;

    // Propiedades para la búsqueda dinámica
    public searchTerm: string = '';
    public searchResults: any[] = [];
    public searchLoading: boolean = false;
    public searchProductos$ = new Subject<string>();

    cotizacion: any = {};
    // Propiedades para crear productos nuevos
    public modalCrearProducto!: any; // BsModalRef
    public nuevoProducto: any = {};
    public creandoProducto: boolean = false;
    public categorias: any[] = [];
    /** Mensajes de validación o error API solo dentro del modal crear producto */
    public crearProductoAlerta: string | null = null;
    public crearProductoAlertaTipo: 'danger' | 'warning' | 'info' = 'danger';

    modalCredito!: any; // BsModalRef

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('mcredito')
    public creditoTemplate!: TemplateRef<any>;

    @ViewChild('productosAjuste')
    public productosAjusteTemplate!: TemplateRef<any>;

    @ViewChild('jsonFileInput')
    public jsonFileInput?: ElementRef<HTMLInputElement>;


    private cdr = inject(ChangeDetectorRef);

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe,
        private route: ActivatedRoute,
        private router: Router,
        private sharedDataService: SharedDataService,
        private funcionalidadesService: FuncionalidadesService,
        private proveedorSearchService: ProveedorSearchService
    ) {
        super(modalManager, alertService);
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
            }),
            this.untilDestroyed()
        ).subscribe(results => {
            this.searchResults = results || [];
            this.searchLoading = false;
            this.cdr.markForCheck();
        });

    }

    ngOnInit() {

        this.cargarDatosIniciales();
        this.verificarAccesoContabilidad();

        // Cargar datos compartidos usando SharedDataService
        this.sharedDataService.getSucursales()
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (sucursales) => {
              this.sucursales = sucursales;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });

        this.apiService.getAll('bodegas/list')
          .pipe(this.untilDestroyed())
          .subscribe(bodegas => {
            this.bodegas = bodegas;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.sharedDataService.getUsuarios()
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (usuarios) => {
              this.usuarios = usuarios;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });

      this.apiService.getAll('banco/cuentas/list')
          .pipe(this.untilDestroyed())
          .subscribe(bancos => {
          this.bancos = bancos;
          this.cdr.markForCheck();
      }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.sharedDataService.getFormasDePago()
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (formaPagos) => {
              this.formaPagos = formaPagos;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });

        this.apiService.getAll('impuestos')
          .pipe(this.untilDestroyed())
          .subscribe(impuestos => {
            // Filtrar solo los impuestos que aplican a compras
            this.impuestos = impuestos.filter((impuesto: any) => impuesto.aplica_compras !== false && impuesto.aplica_compras !== 0);
            this.compra.impuestos = this.impuestos;
            this.sumTotal();
            this.cdr.markForCheck();

        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.sharedDataService.getProveedores()
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (proveedores) => {
              this.proveedores = proveedores;
              this.loading = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.loading = false;
              this.cdr.markForCheck();
            }
          });

        this.sharedDataService.getProyectos()
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (proyectos) => {
              this.proyectos = proyectos;
              this.loading = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.loading = false;
              this.cdr.markForCheck();
            }
          });
    }

    public cargarDocumentos(){
      // Lista de documentos permitidos para compras
      const documentosPermitidos = [
        'Factura',
        'Crédito fiscal',
        'Ticket',
        'Recibo',
        'Sujeto excluido',
        'Recibo',
        'Factura de exportación'
      ];
      if (resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR) {
        documentosPermitidos.push(
          NOMBRE_DOCUMENTO_CR.factura,
          NOMBRE_DOCUMENTO_CR.tiquete,
          NOMBRE_DOCUMENTO_CR.fecCompra,
          'Compra electrónica',
        );
      }

        this.sharedDataService.getDocumentos()
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (documentos) => {
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
                // Filtrar solo los documentos permitidos, excluyendo notas de débito y crédito
                this.documentos = this.documentos.filter((x:any) =>
                  documentosPermitidos.includes(x.nombre) &&
                  x.nombre != 'Nota de crédito' &&
                  x.nombre != 'Nota de débito' &&
                  x.nombre != NOMBRE_DOCUMENTO_CR.notaCredito &&
                  x.nombre != NOMBRE_DOCUMENTO_CR.notaDebito
                );              }
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });
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
        this.compra.tipo_documento =
            resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR
                ? NOMBRE_DOCUMENTO_CR.factura
                : 'Factura';
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
        this.compra.impuestos = [];
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

        this.route.params
          .pipe(this.untilDestroyed())
          .subscribe((params:any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('compra/', params.id)
                  .pipe(this.untilDestroyed())
                  .subscribe(compra => {
                    this.compra = compra;
                    // El API no envía catálogo de impuestos; si aún no hay filas, usar plantilla cargada desde /impuestos
                    if (!this.compra.impuestos || !Array.isArray(this.compra.impuestos) || this.compra.impuestos.length === 0) {
                        this.compra.impuestos = this.impuestos?.length ? this.impuestos : [];
                    }
                    const ivaNum = Number(this.compra.iva);
                    const empresaCobraIva = this.apiService.auth_user().empresa.cobra_iva == 'Si';
                    this.compra.cobrar_impuestos = ivaNum > 0 || (empresaCobraIva && this.compra.tipo_operacion === 'Gravada');
                    this.compra.cobrar_percepcion = (this.compra.percepcion > 0) ? true : false;
                    this.syncCompraCreditoConsignaFlagsFromEstado();
                    // Si /impuestos respondió antes que esta lectura, los montos quedaron en 0; recalcular siempre
                    this.sumTotal();
                    this.loading = false;
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
            }
        });

        // Duplicar compra
        if (this.route.snapshot.queryParamMap.get('recurrente')! && this.route.snapshot.queryParamMap.get('id_compra')!) {
            this.duplicarcompra = true;
            this.apiService.read('compra/', +this.route.snapshot.queryParamMap.get('id_compra')!)
              .pipe(this.untilDestroyed())
              .subscribe(compra => {
                this.compra = compra;
                // Asegurar que impuestos existe y es un array, y usar los impuestos filtrados
                if (!this.compra.impuestos || !Array.isArray(this.compra.impuestos) || this.compra.impuestos.length === 0) {
                    this.compra.impuestos = this.impuestos?.length ? this.impuestos : [];
                } else {
                    // Filtrar los impuestos para mantener solo los que aplican a compras
                    this.compra.impuestos = this.compra.impuestos.filter((impuesto: any) =>
                        impuesto.aplica_compras !== false && impuesto.aplica_compras !== 0
                    );
                }
                this.compra.fecha = this.apiService.date();
                this.compra.fecha_pago = this.apiService.date();
                const ivaNumDup = Number(this.compra.iva);
                const empresaCobraIvaDup = this.apiService.auth_user().empresa.cobra_iva == 'Si';
                this.compra.cobrar_impuestos = ivaNumDup > 0 || (empresaCobraIvaDup && this.compra.tipo_operacion === 'Gravada');
                this.compra.cobrar_percepcion = (this.compra.percepcion > 0) ? true : false;
                this.syncCompraCreditoConsignaFlagsFromEstado();                this.sumTotal();
                this.compra.id = null;
                this.compra.tipo_documento = null;
                this.compra.referencia = null;
                this.compra.detalles.forEach((detalle:any) => {
                    detalle.id = null;
                });
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
        }

        if (this.route.snapshot.queryParamMap.get('id_proyecto')!) {
            this.compra.id_proyecto = +this.route.snapshot.queryParamMap.get('id_proyecto')!;
        }

        // Facturar cotizacion
        if (this.route.snapshot.queryParamMap.get('facturar_cotizacion')! && this.route.snapshot.queryParamMap.get('id_compra')!) {
            this.facturarCotizacion = true;
            this.apiService.read('compra/', +this.route.snapshot.queryParamMap.get('id_compra')!).subscribe(compra => {
                this.compra = compra;
                if (!this.compra.impuestos || !Array.isArray(this.compra.impuestos) || this.compra.impuestos.length === 0) {
                    this.compra.impuestos = this.impuestos?.length ? this.impuestos : [];
                }
                const ivaNumFc = Number(this.compra.iva);
                const empresaCobraIvaFc = this.apiService.auth_user().empresa.cobra_iva == 'Si';
                this.compra.cobrar_impuestos = ivaNumFc > 0
                    || (empresaCobraIvaFc && this.compra.tipo_operacion === 'Gravada');
                this.compra.cobrar_percepcion = Number(this.compra.percepcion) > 0;
                this.compra.fecha = this.apiService.date();
                this.compra.fecha_pago = this.apiService.date();
                this.compra.tipo_documento = null;
                this.compra.referencia = null;
                this.compra.estado = 'Pagada';
                this.compra.cotizacion = 0;
                this.compra.num_orden_compra = this.compra.id;
                this.compra.id = null;
                this.compra.detalles.forEach((detalle:any) => {
                    detalle.id = null;
                });
                this.sumTotal();
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        this.cargarDocumentos();
    }

  verificarAccesoContabilidad() {
    this.funcionalidadesService.verificarAcceso('contabilidad')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (acceso) => {
          this.contabilidadHabilitada = acceso;
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al verificar acceso a contabilidad:', error);
          this.contabilidadHabilitada = false;
          this.cdr.markForCheck();
        }
      });
  }

    public sumTotal() {
        // Asegurar que detalles e impuestos existen
        if (!this.compra.detalles || !Array.isArray(this.compra.detalles)) {
            this.compra.detalles = [];
        }
        if (!this.compra.impuestos || !Array.isArray(this.compra.impuestos)) {
            this.compra.impuestos = [];
        }

        this.compra.sub_total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))).toFixed(2);
        this.compra.percepcion = this.compra.cobrar_percepcion ? this.compra.sub_total * 0.01 : 0;
        this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0;
        this.compra.renta_retenida = this.compra.renta ? this.compra.sub_total * 0.10 : 0;

        // IVA por tasa: cada impuesto recibe solo el IVA de los detalles con ese porcentaje (igual que en ventas)
        const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
        const pctIgual = (a: number, b: number) => Math.abs(Number(a) - Number(b)) < 0.01;
        const porcentajesImpuestos = (this.compra.impuestos || []).map((i: any) => Number(i.porcentaje));

        this.compra.impuestos.forEach((impuesto: any) => {
            if (this.compra.cobrar_impuestos) {
                const pctImp = Number(impuesto.porcentaje);
                const monto = this.compra.detalles
                    .filter((d: any) => {
                        const pctDetalle = (d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '')
                            ? Number(d.porcentaje_impuesto) : empresaIva;
                        return pctIgual(pctImp, pctDetalle);
                    })
                    .reduce((sum: number, d: any) => {
                        const ivaLinea = (d.iva != null && d.iva !== '' && parseFloat(d.iva) >= 0)
                            ? parseFloat(d.iva) : parseFloat(d.total || 0) * (pctImp / 100);
                        return sum + ivaLinea;
                    }, 0);
                impuesto.monto = parseFloat(Number(monto).toFixed(4));
            } else {
                impuesto.monto = 0;
            }
        });

        // Detalles cuyo % no coincide con ningún impuesto: asignar su IVA al impuesto de la empresa o al primero
        if (this.compra.cobrar_impuestos && this.compra.detalles.length && this.compra.impuestos.length) {
            const ivaSinAsignar = this.compra.detalles
                .filter((d: any) => {
                    const pctDetalle = (d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '')
                        ? Number(d.porcentaje_impuesto) : empresaIva;
                    return !porcentajesImpuestos.some((p: number) => pctIgual(p, pctDetalle));
                })
                .reduce((sum: number, d: any) => {
                    const ivaLinea = (d.iva != null && d.iva !== '' && parseFloat(d.iva) >= 0)
                        ? parseFloat(d.iva) : parseFloat(d.total || 0) * (((d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '') ? Number(d.porcentaje_impuesto) : empresaIva) / 100);
                    return sum + ivaLinea;
                }, 0);
            if (ivaSinAsignar > 0) {
                const impuestoDestino = this.compra.impuestos.find((i: any) => pctIgual(Number(i.porcentaje), empresaIva))
                    || this.compra.impuestos[0];
                impuestoDestino.monto = parseFloat((parseFloat(impuestoDestino.monto) + ivaSinAsignar).toFixed(4));
            }
        }

        this.compra.iva = parseFloat(
            this.sumPipe.transform(this.compra.impuestos, 'monto')
        ).toFixed(2);

        // Asegurar que cada detalle tenga iva calculado (para persistir y coincidir con impuestos por tasa)
        if (this.compra.cobrar_impuestos && this.compra.detalles.length) {
            this.compra.detalles.forEach((d: any) => {
                const totalLinea = parseFloat(d.total || 0);
                const pct = (d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '') ? Number(d.porcentaje_impuesto) : empresaIva;
                d.iva = parseFloat((totalLinea * (pct / 100)).toFixed(4));
            });
        }

        this.compra.descuento = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'descuento'))).toFixed(2);
        this.compra.total_costo = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total_costo'))).toFixed(2);
        this.compra.total = (parseFloat(this.compra.sub_total) + parseFloat(this.compra.iva) + parseFloat(this.compra.percepcion) - parseFloat(this.compra.iva_retenido) - parseFloat(this.compra.renta_retenida)).toFixed(2);

        if (this.compra.cobrar_impuestos) {
            this.compra.tipo_operacion = 'Gravada';
        } else {
            this.compra.tipo_operacion = 'No Gravada';
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

    /** Alinea switches UI con `estado` al cargar (credito/consigna no vienen del API). */
    private syncCompraCreditoConsignaFlagsFromEstado(): void {
        if (!this.compra) return;
        const e = this.compra.estado;
        this.compra.consigna = e === 'Consigna';
        this.compra.credito = e === 'Pendiente' || e === 'Consigna';
    }

    public cambioMetodoDePago() {
        const fp = this.compra.forma_pago;
        if (fp === 'Efectivo' || fp === 'Wompi') {
            this.compra.detalle_banco = '';
            this.cdr.markForCheck();
            return;
        }
        if (this.apiService.isModuloBancos() && fp) {
            const formaPagoSeleccionada = this.formaPagos.find((f: any) => f.nombre === fp);
            if (formaPagoSeleccionada?.banco?.nombre_banco) {
                this.compra.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else {
                this.compra.detalle_banco = '';
            }
        }
        this.cdr.markForCheck();
    }

    public setBodega(){
        this.compra.id_sucursal = this.bodegas.find((item:any) => item.id == this.compra.id_bodega).id_sucursal;
    }

    public updatecompra(compra:any) {
        this.compra = compra;
        this.syncCompraCreditoConsignaFlagsFromEstado();
        this.sumTotal();
    }

    public selectTipoDocumento(){
        if(this.compra.tipo_documento == 'Sujeto excluido' || esTipoFacturaElectronicaCompraCr(this.compra.tipo_documento)){
            let documento = this.documentos.find((x:any) => x.nombre == this.compra.tipo_documento);
            if (documento) {
                this.compra.referencia = documento.correlativo;
            }
        }
    }

    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            super.openModal(template, {class: 'modal-md', backdrop:'static'});
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
        public onSubmit() {
            // Validar que productos con lotes tengan lote_id
            if (this.compra.detalles && this.compra.detalles.length > 0) {
                const lotesActivo = this.apiService.isLotesActivo();
                if (lotesActivo) {
                    for (let detalle of this.compra.detalles) {
                        if (detalle.inventario_por_lotes && !detalle.lote_id) {
                            this.alertService.error(`El producto "${detalle.nombre_producto}" requiere seleccionar o crear un lote antes de guardar la compra.`);
                            this.saving = false;
                            return;
                        }
                    }
                }
            }

            this.saving = true;
            if(this.duplicarcompra){
                this.compra.recurrente = false;
            }
            this.apiService.store('compra/facturacion', this.compra).subscribe(compra => {
                this.saving = false;

                if(this.compra.cotizacion == 1){
                    this.router.navigate(['/ordenes-de-compras']);
                    this.alertService.success('Orden de compra creada', 'La orden de compra fue añadida exitosamente.');
                }else{
                    this.router.navigate(['/compras']);
                    this.alertService.success('Compra creada', 'La compra fue añadida exitosamente.');
                }

                // Si es cotización
                if(this.facturarCotizacion){
                    this.apiService.read('compra/', +this.route.snapshot.queryParamMap.get('id_compra')!).subscribe(compra => {
                        compra.estado = 'Aceptada';
                        this.apiService.store('compra', compra).subscribe(compra => {

                        },error => {this.alertService.error(error); this.saving = false; });
                    },error => {this.alertService.error(error); this.saving = false; });

          // Generar partida contable solo para compras aprobadas
          if (this.apiService.auth_user().empresa.generar_partidas == 'Auto') {
            this.apiService.store('contabilidad/partida/compra', compra)
              .pipe(this.untilDestroyed())
              .subscribe(
              compra => { this.cdr.markForCheck(); },
              error => { this.alertService.error(error); this.cdr.markForCheck(); }
            );
          }
        }
      },
      error => {
        this.saving = false;

        // Error 403 = primera solicitud de autorización (abre modal)
        if (error.status === 403 && error.error?.requires_authorization) {
          // El interceptor ya abrió el modal
          // No hacer nada más aquí
          this.cdr.markForCheck();
          return;
        }

        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );
  }

    //Limpiar

    public limpiar(){
        super.openModal(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor)
          .pipe(this.untilDestroyed())
          .subscribe(supervisor => {
            this.closeModal();
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

  openAuthModal() {
    this.showAuthModal = true;
  }

  closeAuthModal() {
    this.showAuthModal = false;
  }

  onAuthorizationRequested(event: any) {
    if (event.shouldProceedWithSubmit) {
      // Agregar el id_authorization a la compra para que no requiera autorización de nuevo
      this.compra.id_authorization = event.authorization.id;

      // Ejecutar el submit automáticamente
      this.onSubmit();
    }
  }

  openJsonImport(template: TemplateRef<any>) {
    this.jsonContent = '';
    this.jsonImportEtiqueta = 'DTE importado';
    setTimeout(() => {
      if (this.jsonFileInput?.nativeElement) {
        this.jsonFileInput.nativeElement.value = '';
      }
    });
    this.modalRef = this.modalManager.openModal(template, { class: 'modal-lg' });
  }

    /** Un solo archivo: carga el contenido en jsonContent y guarda etiqueta para conciliación. */
    handleFileInput(event: Event) {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) {
            return;
        }
        this.jsonImportEtiqueta = file.name;
        const reader = new FileReader();
        reader.onload = (e: ProgressEvent<FileReader>) => {
            this.jsonContent = String(e.target?.result ?? '');
        };
        reader.readAsText(file);
    }

    tieneFuentesJsonImport(): boolean {
        return !!this.jsonContent.trim();
    }

    async processJsonData() {
        this.processingJson = true;

        try {
            const texto = this.jsonContent.trim();
            if (!texto) {
                this.alertService.warning('Sin datos', 'Seleccione un archivo JSON o pegue el contenido.');
                return;
            }

            let data: any;
            try {
                data = JSON.parse(texto);
            } catch (e) {
                this.alertService.error('JSON inválido: revise el formato del archivo o del texto pegado.');
                return;
            }
            if (!data?.identificacion) {
                this.alertService.error(
                    'Formato incorrecto: el JSON no contiene la estructura esperada de DTE (identificacion).'
                );
                return;
            }

            const etiqueta =
                this.jsonImportEtiqueta && this.jsonImportEtiqueta !== 'DTE importado'
                    ? this.jsonImportEtiqueta
                    : 'Contenido pegado';

            await this.importarUnDocumentoDte(data, etiqueta);

            this.modalRef?.hide();
            this.jsonContent = '';
            if (this.jsonFileInput?.nativeElement) {
                this.jsonFileInput.nativeElement.value = '';
            }
            this.jsonImportEtiqueta = 'DTE importado';
            if (!this.ajusteBloques.length) {
                this.alertService.success('Datos importados', 'El documento JSON se importó correctamente.');
            }
        } catch (error) {
            this.alertService.error('Error al procesar JSON: ' + error);
        } finally {
            this.processingJson = false;
        }
    }

  getTipoDocumento(tipoDte: string) {
    const cr = resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
    const tiposDte: Record<string, string> = {
      '01': cr ? NOMBRE_DOCUMENTO_CR.factura : 'Factura',
      '03': 'Crédito fiscal',
      '05': cr ? NOMBRE_DOCUMENTO_CR.notaDebito : 'Nota de débito',
      '06': cr ? NOMBRE_DOCUMENTO_CR.notaCredito : 'Nota de crédito',
      '07': 'Comprobante de retención',
      '11': 'Factura de exportación',
      '14': 'Sujeto excluido',
      '08': cr ? NOMBRE_DOCUMENTO_CR.fecCompra : 'Compra electrónica',
    };
    return tiposDte[tipoDte] || (cr ? NOMBRE_DOCUMENTO_CR.factura : 'Factura');
  }
    /** Importa un único DTE (compras: un JSON por operación). */
    async importarUnDocumentoDte(data: any, etiqueta: string) {
        this.compra.detalles = [];
        this.ajusteBloques = [];
        this.ajusteTabActivo = 0;

        await this.aplicarCabeceraPrimerDocumento(data);

        const cuerpo = data.cuerpoDocumento;
        if (cuerpo && cuerpo.length > 0) {
            const res = await this.resolverLineasDte(cuerpo);
            this.compra.detalles.push(...res.detalles);
            if (res.noEncontrados.length > 0) {
                this.ajusteBloques.push({ etiqueta, items: res.noEncontrados });
            }
        }

        if (this.ajusteBloques.length > 0) {
            await this.cargarSugerenciasTodosLosBloques();
            this.mostrarModalAjusteProductos();
        } else {
            this.sumTotal();
        }
    }

    /** Cabecera, proveedor y totales iniciales (primer documento). */
    async aplicarCabeceraPrimerDocumento(jsonData: any) {
        if (jsonData.identificacion.fecEmi) {
            this.compra.fecha = jsonData.identificacion.fecEmi;
        }

        this.compra.id_usuario = this.apiService.auth_user().id;
        this.compra.id_bodega = this.apiService.auth_user().id_bodega;

        if (jsonData.identificacion.tipoDte) {
            const defFact =
                resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR
                    ? NOMBRE_DOCUMENTO_CR.factura
                    : 'Factura';
            this.compra.tipo_documento =
                this.getTipoDocumento(jsonData.identificacion.tipoDte) || defFact;
        }

        const documentoRow = (this.documentos || []).find(
            (x: any) =>
                x.nombre == this.compra.tipo_documento &&
                x.id_sucursal == this.compra.id_sucursal
        );
        const codGen = jsonData.identificacion?.codigoGeneracion;
        if (codGen) {
            this.compra.referencia = codGen;
        } else if (
            documentoRow &&
            documentoRow.correlativo != null &&
            String(documentoRow.correlativo).trim() !== ''
        ) {
            this.compra.referencia = documentoRow.correlativo;
        }

        const proveedor = await this.getProveedor(jsonData.emisor);
        if (proveedor && proveedor.id) {
            this.compra.id_proveedor = proveedor.id;
        } else {
            console.log('No se pudo asignar proveedor. Proveedor encontrado:', proveedor);
        }

        if (jsonData.resumen) {
            this.compra.sub_total =
                jsonData.resumen.subTotal || jsonData.resumen.subTotalVentas || 0;
            this.compra.total =
                jsonData.resumen.totalPagar || jsonData.resumen.montoTotalOperacion || 0;

            if (jsonData.resumen.tributos) {
                const iva = jsonData.resumen.tributos.find((t: any) => t.codigo === '20');
                if (iva) {
                    this.compra.iva = iva.valor;
                    this.compra.cobrar_impuestos = true;
                }
            }

            const percepcion = parseFloat(jsonData.resumen.ivaPerci1) || 0;
            if (percepcion > 0) {
                this.compra.percepcion = percepcion;
                this.compra.cobrar_percepcion = true;
                const sello =
                    jsonData.selloRecibido ||
                    jsonData.sello ||
                    (jsonData.documento && jsonData.documento.selloRecibido);
                if (sello) {
                    this.compra.sello_mh = sello;
                }
            }
        }
    }

      async getProveedor(proveedor: any): Promise<any> {
        try {
          // Usar Web Worker para búsqueda no bloqueante
          const proveedorEncontrado: any = await firstValueFrom(
            this.proveedorSearchService.searchProveedor(proveedor, this.proveedores)
          );

          if (proveedorEncontrado) {
            return proveedorEncontrado;
          } else {
            return null;
          }
        } catch (error) {
          return null;
        }
      }

  // Métodos mantenidos para compatibilidad, pero ahora usan Web Workers internamente
  async searchNit(nit: string): Promise<any> {
    try {
      return await firstValueFrom(
        this.proveedorSearchService.searchNit(nit, this.proveedores)
      );
    } catch (error) {
      return null;
    }
  }

  async searchNombre(nombre: string): Promise<any> {
    try {
      return await firstValueFrom(
        this.proveedorSearchService.searchNombre(nombre, this.proveedores)
      );
    } catch (error) {
      return null;
    }
  }

  async searchNrc(nrc: string): Promise<any> {
    try {
      return await firstValueFrom(
        this.proveedorSearchService.searchNrc(nrc, this.proveedores)
      );
    } catch (error) {
      return null;
    }
  }

  async searchDui(dui: string): Promise<any> {
    try {
      return await firstValueFrom(
        this.proveedorSearchService.searchDui(dui, this.proveedores)
      );
    } catch (error) {
      return null;
    }
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
    /**
     * Resuelve líneas de un solo cuerpoDocumento (sin vaciar compra.detalles).
     */
    async resolverLineasDte(cuerpoDocumento: any[]): Promise<{
        detalles: any[];
        noEncontrados: any[];
        todosEncontrados: boolean;
    }> {
        const detalles: any[] = [];
        const noEncontrados: any[] = [];
        this.buscandoProductos = true;

        const idEmpresa = this.apiService.auth_user().id_empresa;
        const items = cuerpoDocumento.map((item: any) => ({
            numItem: item.numItem,
            codigo: item.codigo,
            descripcion: item.descripcion,
        }));

        let todosEncontrados = true;

        try {
            const res: any = await this.apiService
                .store('productos/resolver-importacion-dte', {
                    id_empresa: idEmpresa,
                    items,
                })
                .toPromise();

            const resultados = Array.isArray(res?.resultados) ? res.resultados : [];

            for (let i = 0; i < cuerpoDocumento.length; i++) {
                const item = cuerpoDocumento[i];
                const productoEncontrado =
                    i < resultados.length ? resultados[i]?.producto ?? null : null;

                if (productoEncontrado) {
                    detalles.push(this.crearDetalleDesdeItem(item, productoEncontrado));
                    this.productosEncontrados.push(productoEncontrado);
                } else {
                    noEncontrados.push({
                        numItem: item.numItem,
                        codigo: item.codigo,
                        descripcion: item.descripcion,
                        cantidad: item.cantidad,
                        precioUni: item.precioUni,
                        productoSeleccionado: null,
                        sugerencias: [],
                    });
                    todosEncontrados = false;
                }
            }
        } catch (error) {
            console.error('Error resolviendo productos del JSON:', error);
            todosEncontrados = false;
            this.alertService.error(
                'No se pudieron resolver productos de un documento. Revise su conexión o intente de nuevo.'
            );
            for (const item of cuerpoDocumento) {
                noEncontrados.push({
                    numItem: item.numItem,
                    codigo: item.codigo,
                    descripcion: item.descripcion,
                    cantidad: item.cantidad,
                    precioUni: item.precioUni,
                    productoSeleccionado: null,
                    sugerencias: [],
                });
            }
        } finally {
            this.buscandoProductos = false;
        }

        return { detalles, noEncontrados, todosEncontrados };
    }

    async cargarSugerenciasTodosLosBloques() {
        for (const bloque of this.ajusteBloques) {
            await this.cargarSugerenciasParaItems(bloque.items);
        }
    }

    async cargarSugerenciasParaItems(items: any[]) {
        const consultas: { termino: string; palabras: string[] }[] = [];
        const indicesValidos: number[] = [];

        items.forEach((item, index) => {
            const descripcion = (item.descripcion || '').trim();
            const palabras = descripcion
                .toLowerCase()
                .split(' ')
                .filter((p: string) => p.length > 2);
            let terminoBusqueda = palabras.join(' ');
            if (terminoBusqueda.length < 2 && descripcion.length >= 2) {
                terminoBusqueda = descripcion;
            }
            if (terminoBusqueda.length >= 2) {
                consultas.push({ termino: terminoBusqueda, palabras });
                indicesValidos.push(index);
            }
        });

        items.forEach((item) => {
            item.sugerencias = [];
        });

        if (consultas.length === 0) {
            return;
        }

        try {
            const res: any = await this.apiService
                .store('productos/buscar-sugerencias-lote', {
                    id_empresa: this.apiService.auth_user().id_empresa,
                    consultas,
                    limite: 10,
                })
                .toPromise();

            const resultados = Array.isArray(res?.resultados) ? res.resultados : [];
            indicesValidos.forEach((idxOriginal, i) => {
                items[idxOriginal].sugerencias = resultados[i] || [];
            });
        } catch (error) {
            console.error('Error cargando sugerencias en lote:', error);
        }
    }

  async buscarProductoOptimizado(codigo: string, descripcion: string): Promise<any> {
    // Primero verificar cache local
    const enCache = this.productosEncontrados.find((p: any) =>
      p.cod_proveed_prod === codigo ||
      (p.nombre && descripcion && p.nombre.toLowerCase().includes(descripcion.toLowerCase()))
    );

    if (enCache) return enCache;

    try {
      // Búsqueda por código de proveedor
      if (codigo) {
        const porCodigo = await this.buscarPorCodigoProveedor(codigo)
          .pipe(this.untilDestroyed())
          .toPromise();
        if (porCodigo && porCodigo.length > 0) {
          return porCodigo[0];
        }
      }

      // Búsqueda por nombre si no se encontró por código
      if (descripcion) {
        const porNombre = await this.buscarPorNombre(descripcion)
          .pipe(this.untilDestroyed())
          .toPromise();
        if (porNombre && porNombre.length > 0) {
          return porNombre[0];
        }
      }

      return null;
    } catch (error) {
      return null;
    }
  }

  async cargarSugerenciasProductos() {
    for (const item of this.productosNoEncontrados) {
      try {
        // Búsqueda amplia para sugerencias
        const sugerencias = await this.buscarSugerencias(item.descripcion)
          .pipe(this.untilDestroyed())
          .toPromise();
        item.sugerencias = sugerencias || [];
      } catch (error) {
        item.sugerencias = [];
      }
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

    get itemsAjusteTabActiva(): any[] {
        const b = this.ajusteBloques[this.ajusteTabActivo];
        return b ? b.items : [];
    }

    get totalItemsConciliacion(): number {
        return this.ajusteBloques.reduce((n, bloque) => n + bloque.items.length, 0);
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
    })
      .pipe(this.untilDestroyed())
      .toPromise();
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

        for (const bloque of this.ajusteBloques) {
            for (const itemNoEncontrado of bloque.items) {
                if (
                    itemNoEncontrado.productoSeleccionado &&
                    itemNoEncontrado.productoSeleccionado.id
                ) {
                    const detalle = this.crearDetalleDesdeItem(
                        itemNoEncontrado,
                        itemNoEncontrado.productoSeleccionado
                    );
                    this.compra.detalles.push(detalle);
                    this.productosEncontrados.push(itemNoEncontrado.productoSeleccionado);
                } else {
                    todosAsignados = false;
                }
            }
        }

        if (!todosAsignados) {
            this.alertService.error(
                'Por favor, seleccione un producto para todos los items pendientes en cada documento.'
            );
            return;
        }

        this.modalProductos.hide();
        this.ajusteBloques = [];
        this.ajusteTabActivo = 0;
        this.sumTotal();

        this.alertService.success('Productos asignados', 'Los productos han sido asignados correctamente.');
    }

    mostrarModalAjusteProductos() {
        this.ajusteTabActivo = 0;
        this.modalProductos = this.modalManager.openModal(this.productosAjusteTemplate, {
            class: 'modal-xl',
            backdrop: 'static',
        });
    }

    cancelarAjusteProductos() {
        this.modalProductos.hide();
        this.ajusteBloques = [];
        this.ajusteTabActivo = 0;

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

    abrirAjusteManual() {
        this.modalProductos.hide();
        this.ajusteBloques = [];
        this.ajusteTabActivo = 0;

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

    getProductosAsignados(): number {
        let n = 0;
        for (const bloque of this.ajusteBloques) {
            n += bloque.items.filter(
                (item) => item.productoSeleccionado && item.productoSeleccionado.id
            ).length;
        }
        return n;
    }

    getProductosPendientes(): number {
        let n = 0;
        for (const bloque of this.ajusteBloques) {
            n += bloque.items.filter(
                (item) => !item.productoSeleccionado || !item.productoSeleccionado.id
            ).length;
        }
        return n;
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

    // Método para activar la búsqueda desde el template
    onSearchProducts(term: string) {
        this.searchProductos$.next(term);
    }

    // Método para actualizar mapJsonToCompra con la nueva lógica
    mapJsonToCompra(jsonData: any) {
        if (jsonData.identificacion.fecEmi) {
            this.compra.fecha = jsonData.identificacion.fecEmi;
        }

        this.compra.id_usuario = this.apiService.auth_user().id;
        this.compra.id_bodega = this.apiService.auth_user().id_bodega;

        if (jsonData.identificacion.tipoDte) {
            const defFact =
                resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR
                    ? NOMBRE_DOCUMENTO_CR.factura
                    : 'Factura';
            this.compra.tipo_documento = this.getTipoDocumento(jsonData.identificacion.tipoDte) || defFact;
        }

        // Ahora se asigna el  código de generación como numero de referencia
        if (jsonData.identificacion.codigoGeneracion) {
            this.compra.referencia = jsonData.identificacion.codigoGeneracion;
        }

        this.getProveedor(jsonData.emisor).then((proveedor: any) => {
            if(proveedor && proveedor.id){
                this.compra.id_proveedor = proveedor.id;
                //console.log('Proveedor asignado:', proveedor.nombre_empresa || proveedor.nombre, 'ID:', proveedor.id);
            } else {
                console.log('No se pudo asignar proveedor. Proveedor encontrado:', proveedor);
            }
        });

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

            // Percepción (DTE.resumen.ivaPerci1): si trae percepción, asignar monto y sello a la compra
            const percepcion = parseFloat(jsonData.resumen.ivaPerci1) || 0;
            if (percepcion > 0) {
                this.compra.percepcion = percepcion;
                this.compra.cobrar_percepcion = true;
                // Agregar el sello a la compra cuando el DTE tiene percepción
                const sello = jsonData.selloRecibido || jsonData.sello || (jsonData.documento && jsonData.documento.selloRecibido);
                if (sello) {
                    this.compra.sello_mh = sello;
                }
            }
        }

        // Procesar productos del cuerpoDocumento de forma optimizada
        if (jsonData.cuerpoDocumento && jsonData.cuerpoDocumento.length > 0) {
            this.procesarProductosDTE(jsonData.cuerpoDocumento);
        }
    }

    // Métodos para crear productos nuevos
    openModalCrearProducto(template: TemplateRef<any>) {
        this.crearProductoAlerta = null;
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

        this.modalCrearProducto = this.modalManager.openModal(template, { class: 'modal-lg' });
    }

    cerrarModalCrearProducto() {
        this.crearProductoAlerta = null;
        this.modalCrearProducto?.hide();
    }

    cargarCategorias() {
        this.apiService.getAll('categorias/list').subscribe(
            (categorias) => {
                this.categorias = categorias;
            },
            (error) => {
                console.error('Error cargando categorías:', error);
                this.crearProductoAlertaTipo = 'danger';
                this.crearProductoAlerta =
                    'No se pudieron cargar las categorías. Cierre el modal e intente de nuevo, o recargue la página.';
            }
        );
    }

    private mensajeErrorApiProducto(error: any): string {
        if (error?.error?.error) {
            return String(error.error.error);
        }
        if (error?.error?.message) {
            return String(error.error.message);
        }
        if (typeof error?.error === 'string') {
            return error.error;
        }
        if (error?.message) {
            return String(error.message);
        }
        return 'No se pudo crear el producto. Intente de nuevo.';
    }

    crearProducto() {
        this.crearProductoAlerta = null;

        const errores: string[] = [];

        if (!this.nuevoProducto.nombre || this.nuevoProducto.nombre.trim() === '') {
            errores.push('Nombre');
        }

        if (!this.nuevoProducto.tipo || this.nuevoProducto.tipo === '') {
            errores.push('Tipo');
        }

        if (!this.apiService.isSupervisorLimitado()) {
            if (!this.nuevoProducto.costo || this.nuevoProducto.costo <= 0) {
                errores.push('Costo');
            }
        }

        if (!this.nuevoProducto.id_categoria || this.nuevoProducto.id_categoria === '') {
            errores.push('Categoría');
        }

        if (errores.length > 0) {
            this.crearProductoAlertaTipo = 'warning';
            this.crearProductoAlerta = `Complete los campos obligatorios: ${errores.join(', ')}.`;
            return;
        }

        this.creandoProducto = true;

        const precioNum = parseFloat(this.nuevoProducto.precio) || 0;
        const costoNum = this.apiService.isSupervisorLimitado()
            ? precioNum
            : parseFloat(this.nuevoProducto.costo);

        // Preparar datos para el backend
        const datosProducto = {
            nombre: this.nuevoProducto.nombre,
            tipo: this.nuevoProducto.tipo,
            codigo: this.nuevoProducto.codigo || null,
            cod_proveed_prod: this.nuevoProducto.cod_proveed_prod || null,
            costo: costoNum,
            precio: precioNum || costoNum,
            marca: this.nuevoProducto.marca || null,
            stock: this.nuevoProducto.stock || 0,
            descripcion: this.nuevoProducto.descripcion || null,
            id_empresa: this.apiService.auth_user().id_empresa,
            id_usuario: this.apiService.auth_user().id,
            id_categoria: parseInt(this.nuevoProducto.id_categoria),
            medida: 'Unidad' // Medida por defecto
        };

        // Crear el producto en el backend usando la ruta correcta
        this.apiService.store('producto', datosProducto)
          .pipe(this.untilDestroyed())
          .subscribe(
            (productoCreado: any) => {
                this.crearProductoAlerta = null;
                this.modalCrearProducto.hide();
                this.alertService.success(
                    'Producto creado',
                    'El producto ha sido creado exitosamente. Estará disponible para asignar en la consolidación.'
                );
                this.creandoProducto = false;
            },
            (error) => {
                this.crearProductoAlertaTipo = 'danger';
                this.crearProductoAlerta = this.mensajeErrorApiProducto(error);
                this.creandoProducto = false;
            }
        );
    }

}
