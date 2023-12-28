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
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public sucursales:any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public canales:any = [];
    public supervisor:any = {};
    public loading = false;
    public saving = false;
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

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bancos').subscribe(bancos => {
            this.bancos = bancos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('canales').subscribe(canales => {
            this.canales = canales;
            this.compra.id_canal = this.canales[0].id;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago').subscribe(formaPagos => {
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
    }

    public cargarDocumentos(){
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});
    }

    public cargarDatosIniciales(){
        this.cargarDocumentos();
        this.compra = {};
        this.compra.fecha = this.apiService.date();
        this.compra.fecha_pago = this.apiService.date();
        this.compra.forma_pago = 'Efectivo';
        this.compra.tipo = 'Interna';
        this.compra.estado = 'Pagada';
        this.compra.condicion = 'Contado';
        this.compra.tipo_documento = 'Factura';
        this.compra.detalle_banco = '';
        this.compra.id_proveedor = '';
        this.compra.detalles = [];
        this.compra.descuento = 0;
        this.compra.sub_total = 0;
        this.compra.iva_percibido = 0;
        this.compra.iva_retenido = 0;
        this.compra.iva = 0;
        this.compra.total_costo = 0;
        this.compra.total = 0;
        this.detalle = {};
        this.compra.cobrar_impuestos = (this.apiService.auth_user().empresa.cobra_iva == 'Si') ? true : false;
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

        if (this.route.snapshot.queryParamMap.get('estado')!) {
            this.compra.estado = this.route.snapshot.queryParamMap.get('estado')!;
        }

        this.route.params.subscribe((params:any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('compra/', params.id).subscribe(compra => {
                    this.compra = compra;
                    this.loading = false;
                }, error => {this.alertService.error(error); this.loading = false;});
            }
        });
    }

    public sumTotal() {
        this.compra.sub_total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))).toFixed(2);
        this.compra.iva_percibido = this.compra.percepcion ? this.compra.sub_total * 0.01 : 0; 
        this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0; 

        if(this.compra.cobrar_impuestos){
            this.compra.iva = ( this.compra.sub_total * 0.13 ).toFixed(2);
        }else{
            this.compra.iva = 0;
        }

        this.compra.descuento = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'descuento'))).toFixed(2);
        this.compra.total_costo = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total_costo'))).toFixed(2);
        this.compra.total = (parseFloat(this.compra.sub_total) + parseFloat(this.compra.iva) + parseFloat(this.compra.iva_percibido) - parseFloat(this.compra.iva_retenido)).toFixed(2);
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

    public setCredito(){
        if(this.compra.credito){
            this.compra.estado = 'Pendiente';
        }else{
            this.compra.estado = 'Pagada';
        }
    }

    public setConsigna(){
        if(this.compra.consigna){
            this.compra.estado = 'Consigna';
        }else{
            this.setCredito();
        }
    }


    public updatecompra(compra:any) {
        this.compra = compra;
        this.sumTotal();
    }


    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop:'static'});
        }

        public onFacturar(){
            if (confirm('¿Confirma procesar la ' + (this.compra.estado == 'Pre-compra' ? ' orden de compra.' : 'compra.') )) {
                if(!this.compra.recibido)
                    this.compra.recibido = this.compra.total;
                this.onSubmit();
            }
        }

    // Guardar compra
        public onSubmit() {

            this.saving = true;            
            this.apiService.store('compra/facturacion', this.compra).subscribe(compra => {

                if (this.modalRef) { this.modalRef.hide() }
                this.saving = false;
                // this.cargarDatosIniciales();
                if(this.compra.estado == 'Pre-compra'){
                    this.router.navigate(['/ordenes-de-compras']);
                    this.alertService.success('Orden de compra creada', 'La orden de compra fue añadida exitosamente.');
                }else{
                    this.router.navigate(['/compras']);
                    this.alertService.success('Compra creada', 'La compra fue añadida exitosamente.');
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


}
