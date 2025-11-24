import { Component, OnInit, TemplateRef, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-facturacion-consigna',
    templateUrl: './facturacion-consigna.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LazyImageDirective],
    providers: [SumPipe],
    
})

export class FacturacionConsignaComponent extends BaseModalComponent implements OnInit {

    public venta: any= {};
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public sucursales:any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public canales:any = [];
    public supervisor:any = {};
    public override loading = false;
    public imprimir:boolean = false;

    
	constructor( 
	    public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe,
        private route: ActivatedRoute,
        private router: Router,
	) {
        super(modalManager, alertService);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

	ngOnInit() {

        this.route.params
            .pipe(this.untilDestroyed())
            .subscribe((params:any) => {
                if (params.id) {
                    this.loading = true;
                    this.apiService.read('venta/', params.id)
                        .pipe(this.untilDestroyed())
                        .subscribe(venta => {
                            this.venta = venta;
                            this.venta.cobrar_impuestos = (this.apiService.auth_user().empresa.cobra_iva == 'Si') ? true : false;
                            this.loading = false;
                            this.cargarDocumentos();
                            this.cargarDatos();
                        }, error => {this.alertService.error(error); this.loading = false;});
                }else{
                    this.venta = {};
                    this.venta.id_empresa = this.apiService.auth_user().id_empresa;
                    this.venta.id_usuario = this.apiService.auth_user().id;
                }
            });

    }

    public cargarDatos(){
        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('bancos/list')
            .pipe(this.untilDestroyed())
            .subscribe(bancos => {
                this.bancos = bancos;
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('canales/list')
            .pipe(this.untilDestroyed())
            .subscribe(canales => {
                this.canales = canales;
                this.venta.id_canal = this.canales[0].id;
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('impuestos')
            .pipe(this.untilDestroyed())
            .subscribe(impuestos => {
                this.impuestos = impuestos;
                this.venta.impuestos = this.impuestos;
                this.sumTotal();

            }, error => {this.alertService.error(error);});
    }
    
    public cargarDocumentos(){
        this.apiService.getAll('documentos/list')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
                this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.venta.id_sucursal);
                
                let documento = this.documentos.find((x:any) => x.predeterminado == 1);
                if(documento){
                    this.venta.id_documento = documento.id;
                    this.venta.correlativo = documento.correlativo;
                }else{
                    this.venta.id_documento = documentos[0].id;
                    this.venta.correlativo = documentos[0].correlativo;
                }

            }, error => {this.alertService.error(error);});
    }

    public updateTotal(detalle:any){
        if(!detalle.cantidad){
            detalle.cantidad = 0;
        }
        if(detalle.descuento_porcentaje){
            detalle.descuento = detalle.cantidad * (detalle.precio * (detalle.descuento_porcentaje / 100));
        }else{
            detalle.descuento = 0;
        }

        detalle.total_costo  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(4);
        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(4);
        this.sumTotal();
    }

    public sumTotal() {
        this.venta.sub_total = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total'))).toFixed(4);
        this.venta.iva_percibido = this.venta.percepcion ? this.venta.sub_total * 0.01 : 0; 
        this.venta.iva_retenido = this.venta.retencion ? this.venta.sub_total * 0.01 : 0; 

        this.venta.impuestos.forEach((impuesto:any) => {
            if(this.venta.cobrar_impuestos){
                impuesto.monto = this.venta.sub_total * (impuesto.porcentaje / 100);
            }else{
                impuesto.monto = 0;
            }
        });

        this.venta.iva = (parseFloat(this.sumPipe.transform(this.venta.impuestos, 'monto'))).toFixed(4);
        this.venta.descuento = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'))).toFixed(4);
        this.venta.total_costo = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'))).toFixed(4);
        this.venta.total = (parseFloat(this.venta.sub_total) + parseFloat(this.venta.iva) + parseFloat(this.venta.iva_percibido) - parseFloat(this.venta.iva_retenido)).toFixed(4);
    }

    public setDocumento(id_documento:any){
        let documento = this.documentos.find((x:any) => x.id == id_documento);
        this.venta.id_documento = documento.id;
        this.venta.correlativo = documento.correlativo;
    }
   

    // Facturar

        public openModalFacturar(template: TemplateRef<any>) {
            this.openModal(template, {class: 'modal-md', backdrop:'static'});
        }

        public onFacturar(){
            if (confirm('¿Confirma procesar la ' + (this.venta.estado == 'Pre-venta' ? ' cotización.' : 'venta.') )) {
                if(!this.venta.recibido)
                    this.venta.recibido = this.venta.total;

                if(this.venta.forma_pago == 'Wompi'){
                    this.venta.estado = 'Pendiente';
                }
                this.onSubmit();
            }
        }

    // Guardar venta
        public onSubmit() {
            this.loading = true;

            this.apiService.store('venta/facturacion/consigna', this.venta)
                .pipe(this.untilDestroyed())
                .subscribe(venta => {
                    this.loading = false;
                    this.router.navigate(['/ventas']);
                    this.alertService.success('Venta creada', 'La venta fue añadida exitosamente.');

                },error => {this.alertService.error(error); this.loading = false; });

        }

}
