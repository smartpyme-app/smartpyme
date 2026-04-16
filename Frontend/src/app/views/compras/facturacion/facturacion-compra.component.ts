import { Component, ElementRef, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Subject, Observable } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

import * as moment from 'moment';

@Component({
  selector: 'app-facturacion-compra',
  templateUrl: './facturacion-compra.component.html',
  providers: [ SumPipe ],
  styles: [`
    .ajuste-tabs-json {
      overflow-x: auto;
      overflow-y: hidden;
      flex-wrap: nowrap;
    }
  `],
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
    public jsonContent: string = '';
    /** Etiqueta para el bloque de conciliación (nombre de archivo o texto pegado). */
    public jsonImportEtiqueta = 'DTE importado';
    public processingJson: boolean = false;
    /** Bloques de productos sin match, uno por cada JSON que requiera conciliación. */
    public ajusteBloques: { etiqueta: string; items: any[] }[] = [];
    public ajusteTabActivo = 0;
    public modalProductos!: BsModalRef;
    public productosEncontrados: any[] = []; // Cache de productos ya encontrados
    public buscandoProductos: boolean = false;

    // Propiedades para la búsqueda dinámica
    public searchTerm: string = '';
    public searchResults: any[] = [];
    public searchLoading: boolean = false;
    public searchProductos$ = new Subject<string>();

    // Propiedades para crear productos nuevos
    public modalCrearProducto!: BsModalRef;
    public nuevoProducto: any = {};
    public creandoProducto: boolean = false;
    public categorias: any[] = [];
    /** Mensajes de validación o error API solo dentro del modal crear producto */
    public crearProductoAlerta: string | null = null;
    public crearProductoAlertaTipo: 'danger' | 'warning' | 'info' = 'danger';

    
    modalRef!: BsModalRef;
    modalCredito!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('mcredito')
    public creditoTemplate!: TemplateRef<any>;
    
    @ViewChild('productosAjuste')
    public productosAjusteTemplate!: TemplateRef<any>;

    @ViewChild('jsonFileInput')
    public jsonFileInput?: ElementRef<HTMLInputElement>;
    

    
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

        if (this.apiService.isModuloBancos()) {
            this.apiService.getAll('banco/cuentas/list').subscribe(bancos => {
                this.bancos = bancos;
            }, error => {this.alertService.error(error);});
        } else {
            this.apiService.getAll('bancos/list').subscribe(bancos => {
                this.bancos = bancos;
            }, error => {this.alertService.error(error);});
        }

        this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => {
            this.formaPagos = formaPagos;
            if (this.apiService.isModuloBancos() && this.compra.forma_pago && this.compra.forma_pago !== 'Efectivo') {
                const formaPagoSeleccionada = formaPagos.find((fp: any) => fp.nombre === this.compra.forma_pago);
                if (formaPagoSeleccionada?.banco?.nombre_banco && !this.compra.detalle_banco) {
                    this.compra.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
                }
            }
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('impuestos').subscribe(impuestos => {
            // Filtrar solo los impuestos que aplican a compras
            this.impuestos = impuestos.filter((impuesto: any) => impuesto.aplica_compras !== false && impuesto.aplica_compras !== 0);
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
                // Filtrar solo los documentos permitidos, excluyendo notas de débito y crédito
                this.documentos = this.documentos.filter((x:any) => 
                    documentosPermitidos.includes(x.nombre) && 
                    x.nombre != 'Nota de crédito' && 
                    x.nombre != 'Nota de débito'
                );
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
        this.compra.impuestos = [];
        this.detalle = {};
        this.compra.cobrar_impuestos = (this.apiService.auth_user().empresa.cobra_iva == 'Si') ? true : false;
        this.compra.cobrar_percepcion = false;
        this.compra.id_bodega = this.apiService.auth_user().id_bodega;
        this.compra.id_usuario = this.apiService.auth_user().id;
        this.compra.id_vendedor = this.apiService.auth_user().id_empleado;
        this.compra.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.compra.id_empresa = this.apiService.auth_user().id_empresa;
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
                    // El API no envía catálogo de impuestos; si aún no hay filas, usar plantilla cargada desde /impuestos
                    if (!this.compra.impuestos || !Array.isArray(this.compra.impuestos) || this.compra.impuestos.length === 0) {
                        this.compra.impuestos = this.impuestos?.length ? this.impuestos : [];
                    }
                    const ivaNum = Number(this.compra.iva);
                    const empresaCobraIva = this.apiService.auth_user().empresa.cobra_iva == 'Si';
                    this.compra.cobrar_impuestos = ivaNum > 0
                        || (empresaCobraIva && this.compra.tipo_operacion === 'Gravada');
                    this.compra.cobrar_percepcion = Number(this.compra.percepcion) > 0;
                    // Si /impuestos respondió antes que esta lectura, los montos quedaron en 0; recalcular siempre
                    this.sumTotal();
                    this.loading = false;
                }, error => {this.alertService.error(error); this.loading = false;});
            }
        });

        // Duplicar compra

        if (this.route.snapshot.queryParamMap.get('recurrente')! && this.route.snapshot.queryParamMap.get('id_compra')!) {
            this.duplicarcompra = true;
            this.apiService.read('compra/', +this.route.snapshot.queryParamMap.get('id_compra')!).subscribe(compra => {
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
                this.compra.cobrar_impuestos = ivaNumDup > 0
                    || (empresaCobraIvaDup && this.compra.tipo_operacion === 'Gravada');
                this.compra.cobrar_percepcion = Number(this.compra.percepcion) > 0;
                this.sumTotal();
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

    public cambioMetodoDePago() {
        if (this.apiService.isModuloBancos() && this.compra.forma_pago && this.compra.forma_pago !== 'Efectivo') {
            const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.compra.forma_pago);
            if (formaPagoSeleccionada?.banco?.nombre_banco) {
                this.compra.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else {
                this.compra.detalle_banco = '';
            }
        } else if (this.compra.forma_pago === 'Efectivo') {
            this.compra.detalle_banco = '';
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

        public onFacturar(){
            if (confirm('¿Confirma procesar la ' + (this.compra.cotizacion == 1 ? ' orden de compra.' : 'compra.') )) {
                if(!this.compra.recibido)
                    this.compra.recibido = this.compra.total;
                this.onSubmit();
            }
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

                }

            },error => {this.alertService.error(error); this.saving = false; });

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


    openJsonImport(template: TemplateRef<any>) {
        this.jsonContent = '';
        this.jsonImportEtiqueta = 'DTE importado';
        setTimeout(() => {
            if (this.jsonFileInput?.nativeElement) {
                this.jsonFileInput.nativeElement.value = '';
            }
        });
        this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
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

    /** Importa un único DTE (compras: un JSON por operación). */
    async importarUnDocumentoDte(data: any, etiqueta: string) {
        this.compra.detalles = [];
        this.ajusteBloques = [];
        this.ajusteTabActivo = 0;

        this.aplicarCabeceraPrimerDocumento(data);

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
    aplicarCabeceraPrimerDocumento(jsonData: any) {
        if (jsonData.identificacion.fecEmi) {
            this.compra.fecha = jsonData.identificacion.fecEmi;
        }

        this.compra.id_usuario = this.apiService.auth_user().id;
        this.compra.id_bodega = this.apiService.auth_user().id_bodega;

        if (jsonData.identificacion.tipoDte) {
            this.compra.tipo_documento =
                this.getTipoDocumento(jsonData.identificacion.tipoDte) || 'Factura';
        }

        const documentoRow = (this.documentos || []).find(
            (x: any) =>
                x.nombre == this.compra.tipo_documento &&
                x.id_sucursal == this.compra.id_sucursal
        );
        if (
            documentoRow &&
            documentoRow.correlativo != null &&
            String(documentoRow.correlativo).trim() !== ''
        ) {
            this.compra.referencia = documentoRow.correlativo;
        } else if (jsonData.identificacion.codigoGeneracion) {
            this.compra.referencia = jsonData.identificacion.codigoGeneracion;
        }
        const codGen = jsonData.identificacion.codigoGeneracion;
        if (codGen && documentoRow) {
            const tag = `Código generación MH: ${codGen}`;
            const obs = String(this.compra.observaciones || '');
            if (!obs.includes(codGen)) {
                this.compra.observaciones = obs ? `${obs}\n${tag}` : tag;
            }
        }

        const proveedor = this.getProveedor(jsonData.emisor);
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

    get itemsAjusteTabActiva(): any[] {
        const b = this.ajusteBloques[this.ajusteTabActivo];
        return b ? b.items : [];
    }

    get totalItemsConciliacion(): number {
        return this.ajusteBloques.reduce((n, bloque) => n + bloque.items.length, 0);
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
        this.modalProductos = this.modalService.show(this.productosAjusteTemplate, {
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
        
        this.modalCrearProducto = this.modalService.show(template, { class: 'modal-lg' });
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
        this.apiService.store('producto', datosProducto).subscribe(
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
