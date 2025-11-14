import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule, CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { DevolucionCompraDetallesComponent } from './detalles/devolucion-compra-detalles.component';

@Component({
    selector: 'app-devolucion-compra-nueva',
    templateUrl: './devolucion-compra-nueva.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, DevolucionCompraDetallesComponent, CurrencyPipe],
    providers: [SumPipe],
    
})

export class DevolucionCompraNuevaComponent extends BaseModalComponent implements OnInit {

    public compra: any= {};
    public devolucion: any= {};
    public detalle: any = {};
    public documentos:any = [];
    public supervisor:any = {};
    public override loading = false;
    public override saving = false;
    public imprimir:boolean = true;

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
    ) {
        super(modalManager, alertService);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {

        const id = +this.route.snapshot.queryParamMap.get('id_compra')!;
        if(id == 0){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.compra.cliente = {};
            this.apiService.read('compra/', id).subscribe(compra => {
                this.compra = compra;
                this.devolucion.detalles = compra.detalles;
                this.devolucion.id_proveedor = compra.id_proveedor;
                this.devolucion.fecha = this.apiService.date();
                this.devolucion.id_compra = id;
                this.devolucion.tipo = 'devolucion';
                this.devolucion.observaciones = '';

                this.devolucion.cobrar_impuestos = this.compra.iva > 0 ? true : false;
                this.devolucion.cobrar_percepcion = this.compra.percepcion > 0 ? true : false;
                this.devolucion.retencion = this.compra.iva_retenido > 0 ? true : false;

                let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
                if (corte) {
                    this.devolucion.id_caja = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
                    this.devolucion.id_corte = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
                }
                this.devolucion.id_usuario = this.apiService.auth_user().id;
                this.devolucion.id_sucursal = compra.id_sucursal;
                this.devolucion.id_bodega = compra.id_bodega;
                this.devolucion.id_empresa = compra.id_empresa;
                this.sumTotal();
                this.cargarDocumentos();
                this.loading = false;
            }, error => {this.alertService.error(error);this.loading = false;});
        }

    }

    cargarDocumentos(){
        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
            this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.compra.id_sucursal);

            if (this.route.snapshot.queryParamMap.get('tipo_documento')! == 'nota_debito') {
                let documento = this.documentos.find((x:any) => x.nombre == 'Nota de débito');
                if(documento){
                    this.devolucion.tipo_documento = documento.nombre;
                }
            }
            if (this.route.snapshot.queryParamMap.get('tipo_documento')! == 'nota_credito') {

                let documento = this.documentos.find((x:any) => x.nombre == 'Nota de crédito');
                if(documento){
                    this.devolucion.tipo_documento = documento.nombre;
                }
            }
        }, error => {this.alertService.error(error);});
    }

    cargarDatosIniciales(){
        this.cargarDocumentos();
        this.devolucion = {};
        this.devolucion.fecha = this.apiService.date();
        this.devolucion.tipo = 'devolucion';
        this.devolucion.cliente = {};
        this.devolucion.detalles = [];
        this.devolucion.canal = 'Tienda';
        this.devolucion.descuento = 0;
        this.detalle = {};

        let corte = JSON.parse(sessionStorage.getItem('worder_corte')!);
        if (corte) {
            this.devolucion.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
            this.devolucion.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id_caja;
            this.devolucion.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        }

        this.devolucion.id_usuario = this.apiService.auth_user().id;
        this.devolucion.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.devolucion.id_bodega = this.apiService.auth_user().id_bodega;
        // this.sumTotal();
        this.imprimir = true;
    }

    public sumTotal() {
        this.devolucion.sub_total = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total'))).toFixed(2);
        this.devolucion.iva_percibido = this.devolucion.cobrar_percepcion ? this.devolucion.sub_total * 0.01 : 0;
        this.devolucion.iva_retenido = this.devolucion.retencion ? this.devolucion.sub_total * 0.01 : 0;
        this.devolucion.renta_retenida = this.devolucion.renta ? this.devolucion.sub_total * 0.10 : 0;

        if(this.devolucion.cobrar_impuestos){
            this.devolucion.iva = ( this.devolucion.sub_total * 0.13 ).toFixed(2);
        }else{
            this.devolucion.iva = 0;
        }

        this.devolucion.descuento = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'descuento'))).toFixed(2);
        this.devolucion.total_costo = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total_costo'))).toFixed(2);
        this.devolucion.total = (parseFloat(this.devolucion.sub_total) + parseFloat(this.devolucion.iva) + parseFloat(this.devolucion.iva_percibido) - parseFloat(this.devolucion.iva_retenido) - parseFloat(this.devolucion.renta_retenida)).toFixed(2);
    }


    updateCompra(devolucion:any) {
        this.devolucion = devolucion;
        this.sumTotal();
    }

    // Devolución
        openModalDevolucion(template: TemplateRef<any>) {
            this.devolucion.tipo = 'Cambio de producto';
            this.openModal(template, {class: 'modal-sm'});
        }

        public onDevolucion() {

            this.saving = true;
            this.apiService.store('devolucion-compra', this.devolucion).subscribe(devolucion => {
                this.saving = false;
                if(devolucion.tipo_documento == 'Factura' || devolucion.tipo_documento == 'Credito Fiscal' || devolucion.tipo_documento == 'Ticket'){
                    this.imprimirDocDevolucion(devolucion);
                }
                this.router.navigate(['/devoluciones/compras']);
                this.alertService.success('Devolucion de compra creada', 'La devolución de compra fue guardado exitosamente.');
            },error => {this.alertService.error(error); this.saving = false; });
        }


    public imprimirDocDevolucion(devolucion:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + devolucion.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }


}
