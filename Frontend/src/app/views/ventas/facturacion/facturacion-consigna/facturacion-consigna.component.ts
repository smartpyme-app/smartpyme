import { Component, OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { TranslatePipe } from '@ngx-translate/core';
import { DisplayNombreImpuestoPipe } from '@pipes/display-nombre-impuesto.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import {
    acumularMontosImpuestosVenta,
    esImpuestoIva,
    hidratarImpuestosProductosEnDetalles,
} from '@utils/impuestos-venta.util';
import { resolverCanalVentaDefault } from '@utils/canal-venta.util';
import { calcularCambioEfectivo } from '@utils/cambio-efectivo.util';
import { FE_PAIS_CR, FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-facturacion-consigna',
    templateUrl: './facturacion-consigna.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LazyImageDirective, TranslatePipe, DisplayNombreImpuestoPipe, CurrencyPipe],
    providers: [SumPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class FacturacionConsignaComponent extends BaseModalComponent implements OnInit {

    public venta: any= {};
    public usuarios:any = [];
    public documentos:any = [];
    public formaPagos:any = [];
    public bodegas: any = [];
    public impuestos:any = [];
    public bancos:any = [];
    public canales:any = [];
    public supervisor:any = {};
    public override loading = false;
    public imprimir:boolean = false;

    
    private cdr = inject(ChangeDetectorRef);

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
                            hidratarImpuestosProductosEnDetalles(
                                this.venta.detalles,
                                this.apiService.auth_user()?.empresa?.iva
                            );
                            this.venta.cobrar_impuestos = parseFloat(this.venta.iva || 0) > 0;
                            this.venta.cotizacion = this.venta.cotizacion ?? 0;
                            this.syncVentaCreditoConsignaFlagsFromEstado();
                            this.limpiarCamposEfectivoLiquidacion();
                            this.normalizarCostosDetalle();
                            this.loading = false;
                            this.cargarDatos();
                            this.sumTotal();
                            this.cdr.markForCheck();
                        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
                }else{
                    this.venta = {};
                    this.venta.id_empresa = this.apiService.auth_user().id_empresa;
                    this.venta.id_usuario = this.apiService.auth_user().id;
                }
            });

    }

    public cargarDatos(){
        this.apiService.getAll('bodegas/list')
            .pipe(this.untilDestroyed())
            .subscribe(bodegas => {
                this.bodegas = bodegas ?? [];
                if (!this.venta.id_bodega) {
                    this.venta.id_bodega = this.apiService.auth_user().id_bodega;
                }
                this.setBodega();
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        if (this.apiService.isModuloBancos()) {
            this.apiService.getAll('banco/cuentas/list')
                .pipe(this.untilDestroyed())
                .subscribe(bancos => {
                    this.bancos = bancos;
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        } else {
            this.apiService.getAll('bancos/list')
                .pipe(this.untilDestroyed())
                .subscribe(bancos => {
                    this.bancos = bancos;
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        }

        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
                this.formaPagos = formaPagos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('canales/list')
            .pipe(this.untilDestroyed())
            .subscribe(canales => {
                this.canales = canales;
                if (!this.venta.id_canal) {
                    this.venta.id_canal = resolverCanalVentaDefault(
                        canales,
                        this.apiService.auth_user()?.id_canal
                    );
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('impuestos')
            .pipe(this.untilDestroyed())
            .subscribe(impuestos => {
                this.impuestos = impuestos;
                this.venta.impuestos = this.impuestos;
                this.sumTotal();
                this.cdr.markForCheck();

            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }
    
    public setBodega(): void {
        const bodega = this.bodegas.find((b: any) => Number(b.id) === Number(this.venta.id_bodega));
        if (bodega?.id_sucursal != null) {
            this.venta.id_sucursal = bodega.id_sucursal;
        }
        if (this.venta.id_sucursal) {
            this.cargarDocumentos();
        }
    }

    public cargarDocumentos(){
        this.apiService.getAll('documentos/list')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
                const idSucursal = this.venta.id_sucursal;
                this.documentos = this.documentos.filter((x:any) => Number(x.id_sucursal) === Number(idSucursal));
                
                if (!this.venta.id_documento && this.documentos.length) {
                    const documento = this.documentos.find((x: any) => x.predeterminado == 1) ?? this.documentos[0];
                    this.venta.id_documento = documento.id;
                    this.venta.correlativo = documento.correlativo;
                }
                this.cdr.markForCheck();

            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    private normalizarCostosDetalle(): void {
        if (!Array.isArray(this.venta?.detalles)) {
            return;
        }
        for (const detalle of this.venta.detalles) {
            const costo = parseFloat(detalle.costo) || parseFloat(detalle.costo_producto) || 0;
            detalle.costo = costo;
        }
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

        const costoUnit = parseFloat(detalle.costo) || parseFloat(detalle.costo_producto) || 0;
        detalle.costo = costoUnit;
        detalle.total_costo = (parseFloat(detalle.cantidad) * costoUnit).toFixed(4);
        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(4);
        this.sumTotal();
    }

    public sumTotal() {
        if (!Array.isArray(this.venta.detalles)) {
            this.venta.detalles = [];
        }
        if (!Array.isArray(this.venta.impuestos)) {
            this.venta.impuestos = [];
        }

        this.venta.sub_total = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total'))).toFixed(4);
        this.venta.iva_percibido = this.venta.percepcion ? this.venta.sub_total * 0.01 : 0; 
        this.venta.iva_retenido = this.venta.retencion ? this.venta.sub_total * 0.01 : 0; 

        const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
        acumularMontosImpuestosVenta(
            this.venta.impuestos,
            this.venta.detalles,
            !!this.venta.cobrar_impuestos,
            empresaIva
        );
        const montoSoloIva = this.venta.impuestos
            .filter((impuesto:any) => esImpuestoIva(impuesto, empresaIva))
            .reduce((suma:number, impuesto:any) => suma + (parseFloat(impuesto.monto) || 0), 0);
        this.venta.iva = parseFloat(montoSoloIva.toFixed(4)).toFixed(4);
        const montoTotalImpuestos = this.venta.impuestos
            .reduce((suma:number, impuesto:any) => suma + (parseFloat(impuesto.monto) || 0), 0);
        this.venta.descuento = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'))).toFixed(4);
        const sumCosto = parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'));
        this.venta.total_costo = (Number.isFinite(sumCosto) ? sumCosto : 0).toFixed(4);
        this.venta.total = (parseFloat(this.venta.sub_total) + montoTotalImpuestos + parseFloat(this.venta.iva_percibido) - parseFloat(this.venta.iva_retenido)).toFixed(4);
        this.actualizarCambioSiHayEfectivoRecibido();
    }

    /** Liquidar consigna: no reutilizar monto_pago/cambio de la remisión en BD. */
    private limpiarCamposEfectivoLiquidacion(): void {
        this.venta.monto_pago = null;
        this.venta.cambio = null;
    }

    private actualizarCambioSiHayEfectivoRecibido(): void {
        const mp = this.venta.monto_pago;
        if (mp === null || mp === undefined || mp === '') {
            this.venta.cambio = null;
            return;
        }
        this.actualizarCambioEfectivo();
    }

    public esFacturacionElSalvador(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_SV;
    }

    public esFeCostaRicaFacturacion(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
    }

    private syncVentaCreditoConsignaFlagsFromEstado(): void {
        const e = this.venta?.estado;
        this.venta.consigna = e === 'Consigna';
        this.venta.credito = e === 'Pendiente' || e === 'Consigna';
    }

    public setCredito(): void {
        if (this.venta.credito) {
            this.venta.estado = 'Pendiente';
            this.venta.fecha_pago = moment().add(1, 'month').format('YYYY-MM-DD');
        } else {
            this.venta.estado = 'Pagada';
            this.venta.fecha_pago = moment().format('YYYY-MM-DD');
            this.venta.consigna = false;
        }
        this.cdr.markForCheck();
    }

    public setConsigna(): void {
        if (this.venta.consigna) {
            this.venta.estado = 'Consigna';
        } else {
            this.setCredito();
        }
        this.cdr.markForCheck();
    }

    public onCobrarImpuestosChange(): void {
        this.sumTotal();
    }

    public onRetencionIvaManualChange(): void {
        this.sumTotal();
    }

    public actualizarCambioEfectivo(): void {
        this.venta.cambio = calcularCambioEfectivo({
            montoPago: this.venta.monto_pago,
            total: parseFloat(this.venta.total) || 0,
            propina: this.venta.propina,
            formaPago: this.venta.forma_pago,
            efectivo: this.venta.efectivo,
        });
        this.cdr.markForCheck();
    }

    public cambioMetodoDePago(): void {
        const fp = this.venta.forma_pago;
        if (fp === 'Efectivo' || fp === 'Wompi') {
            this.venta.detalle_banco = '';
            this.cdr.markForCheck();
            return;
        }
        if (this.apiService.isModuloBancos() && fp) {
            const formaPagoSeleccionada = this.formaPagos.find((f: any) => f.nombre === fp);
            this.venta.detalle_banco = formaPagoSeleccionada?.banco?.nombre_banco ?? '';
        }
        this.actualizarCambioSiHayEfectivoRecibido();
        this.cdr.markForCheck();
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
            this.normalizarCostosDetalle();
            this.sumTotal();

            this.apiService.store('venta/facturacion/consigna', this.venta)
                .pipe(this.untilDestroyed())
                .subscribe(venta => {
                    this.loading = false;
                    this.router.navigate(['/ventas']);
                    this.alertService.success('Venta creada', 'La venta fue añadida exitosamente.');

                },error => {this.alertService.error(error); this.loading = false; });

        }

}
