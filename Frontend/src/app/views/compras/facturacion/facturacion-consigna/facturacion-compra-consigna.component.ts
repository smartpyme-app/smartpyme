import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-facturacion-compra-consigna',
  templateUrl: './facturacion-compra-consigna.component.html',
  providers: [ SumPipe ]
})

export class FacturacionCompraConsignaComponent implements OnInit {

    public compra: any= {};
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public sucursales:any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public canales:any = [];
    public supervisor:any = {};
    public loading = false;
    public imprimir:boolean = false;
    
    modalRef!: BsModalRef;
    
	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
	) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

	ngOnInit() {

        this.route.params.subscribe((params:any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('compra/', params.id).subscribe(compra => {
                    this.compra = compra;
                    this.compra.cobrar_impuestos = this.compra.iva ? true : false;
                    this.loading = false;
                    this.cargarDatos();
                }, error => {this.alertService.error(error); this.loading = false;});
            }else{
                this.compra = {};
                this.compra.id_empresa = this.apiService.auth_user().id_empresa;
                this.compra.id_usuario = this.apiService.auth_user().id;
            }
        });


    }

    public cargarDatos(){
        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bancos/list').subscribe(bancos => {
            this.bancos = bancos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => {
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error);});

    }


    public updateTotal(detalle:any){
        if(!detalle.cantidad){
            detalle.cantidad = 0;
        }
        if(detalle.descuento_porcentaje){
            detalle.descuento = detalle.cantidad * (detalle.costo * (detalle.descuento_porcentaje / 100));
        }else{
            detalle.descuento = 0;
        }

        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo) - parseFloat(detalle.descuento)).toFixed(4);
        this.sumTotal();
    }

    public sumTotal() {
        this.compra.sub_total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))).toFixed(2);
        this.compra.percepcion = this.compra.cobrar_percepcion ? this.compra.sub_total * 0.01 : 0; 
        this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0; 

        if(this.compra.cobrar_impuestos){
            this.compra.iva = ( this.compra.sub_total * 0.13 ).toFixed(2);
        }else{
            this.compra.iva = 0;
        }

        this.compra.descuento = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'descuento'))).toFixed(2);
        this.compra.total = (parseFloat(this.compra.sub_total) + parseFloat(this.compra.iva) + parseFloat(this.compra.percepcion) - parseFloat(this.compra.iva_retenido)).toFixed(2);
    }


    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop:'static'});
        }

        public onFacturar(){
            if (confirm('¿Confirma procesar la ' + (this.compra.estado == 'Pre-compra' ? ' cotización.' : 'compra.') )) {
                if(!this.compra.recibido)
                    this.compra.recibido = this.compra.total;

                if(this.compra.forma_pago == 'Wompi'){
                    this.compra.estado = 'Pendiente';
                }
                this.onSubmit();
            }
        }

    // Guardar compra
        public onSubmit() {
            this.loading = true;

            this.apiService.store('compra/facturacion/consigna', this.compra).subscribe(compra => {
                this.loading = false;
                this.router.navigate(['/compras']);
                this.alertService.success('Compra creada', 'La compra fue añadida exitosamente.');

            },error => {this.alertService.error(error); this.loading = false; });

        }


}
