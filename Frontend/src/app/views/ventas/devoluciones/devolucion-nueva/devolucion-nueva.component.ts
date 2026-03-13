import { Component, OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { DevolucionVentaDetallesComponent } from './detalles/devolucion-venta-detalles.component';

@Component({
    selector: 'app-devolucion-nueva',
    templateUrl: './devolucion-nueva.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, DevolucionVentaDetallesComponent, CurrencyPipe],
    providers: [SumPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class DevolucionVentaNuevaComponent extends BaseModalComponent implements OnInit {

    public venta: any= {};
    public devolucion: any= {};
    public detalle: any = {};
    public documentos:any = [];
    public supervisor:any = {};
    public override loading:boolean = false;
    public override saving:boolean = false;
    public imprimir:boolean = true;
    private cdr = inject(ChangeDetectorRef);

	constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe,
        private route: ActivatedRoute,
        private router: Router
    ){
        super(modalManager, alertService);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

	ngOnInit() {
        const id = +this.route.snapshot.queryParamMap.get('id_venta')!;
        if(id == 0){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.venta.cliente = {};
            this.apiService.read('venta/', id)
                .pipe(this.untilDestroyed())
                .subscribe(venta => {
                this.venta = venta;
                this.devolucion.detalles = venta.detalles;
                this.devolucion.id_cliente = venta.id_cliente;
                this.devolucion.id_bodega = venta.id_bodega;
                this.devolucion.impuestos = venta.impuestos;
                this.devolucion.fecha = this.apiService.date();
                this.devolucion.id_venta = id;
                this.devolucion.tipo = 'devolucion';
                this.devolucion.cuenta_a_terceros = this.venta.cuenta_a_terceros;

                this.devolucion.percepcion = parseFloat(this.venta.iva_percibido) > 0 ? true : false;
                this.devolucion.retencion = parseFloat(this.venta.iva_retenido) > 0 ? true : false;
                this.devolucion.cobrar_impuestos = parseFloat(this.venta.iva) > 0 ? true : false;
                this.devolucion.renta = parseFloat(this.venta.renta_retenida || 0) > 0 ? true : false;

                let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
                if (corte) {
                    this.devolucion.id_caja = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
                    this.devolucion.id_corte = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
                }
                this.devolucion.id_usuario = this.apiService.auth_user().id;
                this.devolucion.id_bodega = this.venta.id_bodega;
                this.devolucion.id_sucursal = this.venta.id_sucursal;
                this.devolucion.id_empresa = this.venta.id_empresa;
                this.devolucion.enable = true;
                this.devolucion.sub_total = this.venta.sub_total;
                this.devolucion.iva = this.venta.iva;
                this.devolucion.iva_retenido = this.venta.iva_retenido;
                this.devolucion.iva_percibido = this.venta.iva_percibido;
                this.devolucion.renta_retenida = this.venta.renta_retenida || 0;
                this.devolucion.cuenta_a_terceros = this.venta.cuenta_a_terceros;
                this.devolucion.exenta = this.venta.exenta;
                this.devolucion.no_sujeta = this.venta.no_sujeta;
                this.devolucion.descuento = this.venta.descuento;
                this.devolucion.total_costo = this.venta.total_costo;
                this.devolucion.total = this.venta.total;
                // this.sumTotal();
                this.cargarDocumentos();
                this.loading = false;
                this.cdr.markForCheck();
                // console.log(this.devolucion);
            }, error => {this.alertService.error(error);this.loading = false; this.cdr.markForCheck(); });
        }

    }

    cargarDocumentos(){
        this.apiService.getAll('documentos/list')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
            this.documentos = documentos;
            this.documentos = this.documentos.filter((doc:any) => doc.id_sucursal == this.devolucion.id_sucursal &&
                  doc.nombre === 'Nota de débito' || doc.nombre === 'Nota de crédito'
                );

            if (this.route.snapshot.queryParamMap.get('tipo_documento')! == 'nota_debito') {
                let documento = this.documentos.find((x:any) => x.nombre == 'Nota de débito');
                if(documento){
                    this.devolucion.id_documento = documento.id;
                    this.devolucion.correlativo = documento.correlativo;
                }
            }
            if (this.route.snapshot.queryParamMap.get('tipo_documento')! == 'nota_credito') {
                let documento = this.documentos.find((x:any) => x.nombre == 'Nota de crédito');
                if(documento){
                    this.devolucion.id_documento = documento.id;
                    this.devolucion.correlativo = documento.correlativo;
                }
            }
            this.cdr.markForCheck();

        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    public setDocumento(id_documento:any){
        let documento = this.documentos.find((x:any) => x.id == id_documento);
        this.devolucion.id_documento = documento.id;
        this.devolucion.correlativo = documento.correlativo;
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

        let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
        if (corte) {
            this.devolucion.fecha = JSON.parse(sessionStorage.getItem('SP_corte')!).fecha;
            this.devolucion.caja_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
            this.devolucion.corte_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
        }

        this.devolucion.id_usuario = this.apiService.auth_user().id;
        this.devolucion.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.devolucion.id_bodega = this.apiService.auth_user().id_bodega;
        this.devolucion.id_empresa = this.apiService.auth_user().id_empresa;
        this.devolucion.enable = true;
        // this.sumTotal();
        this.imprimir = true;
    }

    public sumTotal() {
        this.devolucion.sub_total = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total'))).toFixed(2);

        this.devolucion.exenta = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'exenta'))).toFixed(4);
        this.devolucion.no_sujeta = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'no_sujeta'))).toFixed(4);

        this.devolucion.iva_percibido = this.devolucion.percepcion ? this.devolucion.sub_total * 0.01 : 0;
        this.devolucion.iva_retenido = this.devolucion.retencion ? this.devolucion.sub_total * 0.01 : 0;
        this.devolucion.renta_retenida = this.devolucion.renta ? this.devolucion.sub_total * 0.10 : 0;

        this.devolucion.impuestos.forEach((impuesto:any) => {
            if(this.devolucion.cobrar_impuestos){
                impuesto.monto = this.devolucion.sub_total * (impuesto.porcentaje / 100);
            }else{
                impuesto.monto = 0;
            }
        });

        this.devolucion.iva = (parseFloat(this.sumPipe.transform(this.devolucion.impuestos, 'monto'))).toFixed(2);
        this.devolucion.descuento = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'descuento'))).toFixed(2);
        this.devolucion.total_costo = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total_costo'))).toFixed(2);
        this.devolucion.total = (parseFloat(this.devolucion.sub_total) + parseFloat(this.devolucion.iva) + parseFloat(this.devolucion.cuenta_a_terceros) + parseFloat(this.devolucion.exenta) + parseFloat(this.devolucion.no_sujeta) + parseFloat(this.devolucion.iva_percibido) - parseFloat(this.devolucion.iva_retenido) - parseFloat(this.devolucion.renta_retenida)).toFixed(2);
        //console.log(this.devolucion);
    }

    updateDevolucion(devolucion:any) {
        this.devolucion = devolucion;
        this.sumTotal();
    }

    // Devolución
        openModalDevolucion(template: TemplateRef<any>) {
            this.openModal(template, {class: 'modal-sm'});
            this.devolucion.tipo = 'Cambio de producto';
        }

        public onDevolucion() {

            this.saving = true;
            this.apiService.store('devolucion/venta/facturacion', this.devolucion)
                .pipe(this.untilDestroyed())
                .subscribe(devolucion => {
                this.saving = false;
                if(devolucion.tipo_documento == 'Factura' || devolucion.tipo_documento == 'Credito Fiscal' || devolucion.tipo_documento == 'Ticket'){
                    this.imprimirDocDevolucion(devolucion);
                }
                this.router.navigate(['/devoluciones/ventas']);
                this.alertService.success('Devolucion de venta creada', 'La devolución de venta fue guardado exitosamente.');
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck(); });
        }

    public imprimirDocDevolucion(devolucion:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + devolucion.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }

}
