import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-facturacion-compra',
  templateUrl: './facturacion-compra.component.html',
  providers: [ SumPipe ]
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
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bancos/list').subscribe(bancos => {
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
            this.apiService.read('compra/', +this.route.snapshot.queryParamMap.get('id_compra')!).subscribe(compra => {
                this.compra = compra;
                this.compra.cobrar_impuestos = (this.compra.iva > 0) ? true : false;
                this.compra.cobrar_percepcion = (this.compra.percepcion > 0) ? true : false;
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
            }, error => {this.alertService.error(error); this.loading = false;});
        }

        this.cargarDocumentos();
    }

    public sumTotal() {
        this.compra.sub_total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))).toFixed(2);
        this.compra.percepcion = this.compra.cobrar_percepcion ? this.compra.sub_total * 0.01 : 0; 
        this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0;
        this.compra.renta_retenida = this.compra.renta ? this.compra.sub_total * 0.10 : 0; 

        if(this.compra.cobrar_impuestos){
            this.compra.iva = ( this.compra.sub_total * 0.13 ).toFixed(2);
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

        public onFacturar(){
            if (confirm('¿Confirma procesar la ' + (this.compra.cotizacion == 1 ? ' orden de compra.' : 'compra.') )) {
                if(!this.compra.recibido)
                    this.compra.recibido = this.compra.total;
                this.onSubmit();
            }
        }

    // Guardar compra
        public onSubmit() {

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


}
