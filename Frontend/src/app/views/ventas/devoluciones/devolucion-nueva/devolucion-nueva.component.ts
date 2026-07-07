import { Component, OnInit, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
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
import { MHService } from '@services/MH.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { esElSalvadorFe as empresaEsElSalvador, debeEmitirDteEnImpresion } from '@services/facturacion-electronica/fe-pais.util';
import {
    esNombreNotaCredito,
    esNombreNotaCreditoODebito,
    esNombreNotaDebito,
} from '@views/ventas/documentos/documento-nombre-options';

@Component({
    selector: 'app-devolucion-nueva',
    templateUrl: './devolucion-nueva.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, DevolucionVentaDetallesComponent, CurrencyPipe],
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
    public emiting:boolean = false;
    public imprimir:boolean = true;
    private cdr = inject(ChangeDetectorRef);
    private readonly countryI18n = inject(CountryI18nService);

	constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe,
        private route: ActivatedRoute,
        private router: Router,
        private mhService: MHService,
    ){
        super(modalManager, alertService);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    esElSalvadorFe(): boolean {
        return empresaEsElSalvador(this.apiService.auth_user()?.empresa);
    }

	ngOnInit() {
        const id = +this.route.snapshot.queryParamMap.get('id_venta')!;
        if(id == 0){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.venta.cliente = {};
            this.devolucion = { detalles: [] };
            this.apiService.read('venta/', id)
                .pipe(this.untilDestroyed())
                .subscribe(venta => {
                this.venta = venta;
                const detalles = this.prepararDetallesDesdeVenta(venta.detalles);
                let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
                this.devolucion = {
                    detalles,
                    id_cliente: venta.id_cliente,
                    id_bodega: venta.id_bodega,
                    impuestos: venta.impuestos ?? [],
                    fecha: this.apiService.date(),
                    id_venta: id,
                    tipo: 'devolucion',
                    cuenta_a_terceros: venta.cuenta_a_terceros,
                    percepcion: parseFloat(venta.iva_percibido) > 0,
                    retencion: parseFloat(venta.iva_retenido) > 0,
                    cobrar_impuestos: parseFloat(venta.iva) > 0,
                    renta: parseFloat(venta.renta_retenida || 0) > 0,
                    id_caja: corte ? JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja : undefined,
                    id_corte: corte ? JSON.parse(sessionStorage.getItem('SP_corte')!).id : undefined,
                    id_usuario: this.apiService.auth_user().id,
                    id_sucursal: venta.id_sucursal,
                    id_empresa: venta.id_empresa,
                    enable: true,
                    sub_total: venta.sub_total,
                    iva: venta.iva,
                    iva_retenido: venta.iva_retenido,
                    iva_percibido: venta.iva_percibido,
                    renta_retenida: venta.renta_retenida || 0,
                    exenta: venta.exenta,
                    no_sujeta: venta.no_sujeta,
                    descuento: venta.descuento,
                    total_costo: venta.total_costo,
                    total: venta.total,
                };
                this.cargarDocumentos();
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error);this.loading = false; this.cdr.markForCheck(); });
        }

    }

    cargarDocumentos(){
        const idSucursal =
            this.devolucion.id_sucursal ?? this.apiService.auth_user()?.id_sucursal;
        this.apiService.getAll('documentos/list')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
            this.documentos = (documentos ?? []).filter(
                (doc: any) =>
                    doc.id_sucursal == idSucursal &&
                    esNombreNotaCreditoODebito(doc.nombre)
            );

            const tipoDoc = this.route.snapshot.queryParamMap.get('tipo_documento');
            if (tipoDoc === 'nota_debito') {
                const documento = this.documentos.find((x: any) => esNombreNotaDebito(x.nombre));
                if (documento) {
                    this.asignarDocumento(documento);
                }
            } else if (tipoDoc === 'nota_credito') {
                const documento = this.documentos.find((x: any) => esNombreNotaCredito(x.nombre));
                if (documento) {
                    this.asignarDocumento(documento);
                }
            } else if (this.documentos.length === 1) {
                this.asignarDocumento(this.documentos[0]);
            }
            this.cdr.markForCheck();

        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    private asignarDocumento(documento: { id: number; correlativo?: number | string }): void {
        this.devolucion = {
            ...this.devolucion,
            id_documento: documento.id,
            correlativo: documento.correlativo,
        };
        this.cdr.markForCheck();
    }

    private prepararDetallesDesdeVenta(detalles: any[] | null | undefined): any[] {
        return (detalles ?? []).map((detalle) => {
            const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
            const precio = parseFloat(String(detalle?.precio ?? 0)) || 0;
            const descuento = parseFloat(String(detalle?.descuento ?? 0)) || 0;
            const total =
                detalle?.total != null && detalle?.total !== ''
                    ? parseFloat(String(detalle.total))
                    : cantidad * precio - descuento;

            return {
                ...detalle,
                seleccionado: false,
                cantidad,
                precio,
                descuento,
                total: Number.isFinite(total) ? total : 0,
            };
        });
    }

    public setDocumento(id_documento:any){
        const documento = this.documentos.find((x:any) => x.id == id_documento);
        if (!documento) {
            return;
        }
        this.asignarDocumento(documento);
        this.cdr.markForCheck();
    }

    cargarDatosIniciales(){
        this.devolucion = {};
        this.devolucion.fecha = this.apiService.date();
        this.devolucion.tipo = 'devolucion';
        this.devolucion.cliente = {};
        this.devolucion.detalles = [];
        this.devolucion.canal = 'Tienda';
        this.devolucion.descuento = 0;
        this.devolucion.impuestos = [];
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
        this.cargarDocumentos();
        this.imprimir = true;
    }

    public sumTotal() {
        this.devolucion.sub_total = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total'))).toFixed(2);

        this.devolucion.exenta = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'exenta'))).toFixed(4);
        this.devolucion.no_sujeta = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'no_sujeta'))).toFixed(4);

        this.devolucion.iva_percibido = this.devolucion.percepcion ? this.devolucion.sub_total * 0.01 : 0;
        this.devolucion.iva_retenido = this.devolucion.retencion ? this.devolucion.sub_total * 0.01 : 0;
        this.devolucion.renta_retenida = this.devolucion.renta ? this.devolucion.sub_total * 0.10 : 0;

        (this.devolucion.impuestos ?? []).forEach((impuesto:any) => {
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
        this.devolucion = { ...devolucion, detalles: [...(devolucion?.detalles ?? [])] };
        this.sumTotal();
        this.cdr.markForCheck();
    }

    // Devolución
        openModalDevolucion(template: TemplateRef<any>) {
            this.openModal(template, {class: 'modal-sm'});
            this.devolucion.tipo = 'Cambio de producto';
        }

        public onDevolucion() {

            this.saving = true;
            this.cdr.markForCheck();
            this.apiService.store('devolucion/venta/facturacion', this.devolucion)
                .pipe(this.untilDestroyed())
                .subscribe(devolucion => {
                const empresa = this.apiService.auth_user()?.empresa;
                const esNotaCreditoODebito = esNombreNotaCreditoODebito(devolucion.nombre_documento);

                if (
                    empresa?.impresion_en_facturacion &&
                    debeEmitirDteEnImpresion(empresa) &&
                    esNotaCreditoODebito
                ) {
                    this.emitirDteNotaTrasProcesar(devolucion);
                    return;
                }

                this.finalizarTrasGuardarDevolucion(devolucion);
            },error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck(); });
        }

    /**
     * Misma idea que facturación con "imprimir directamente": si hay FE, al procesar la nota se firma y envía el DTE.
     */
    private emitirDteNotaTrasProcesar(devolucion: any): void {
        this.emiting = true;
        this.cdr.markForCheck();
        this.mhService.emitirDTENotaCredito(devolucion).then((d) => {
            this.alertService.success(this.countryI18n.fe('emitSuccessTitle'), this.countryI18n.fe('emitSuccessBody'));
            if (d.id_cliente) {
                this.enviarDteCorreo(d);
            }
            const tipoDte =
                d.tipo_dte ||
                d.dte?.identificacion?.tipoDte ||
                (esNombreNotaDebito(d.nombre_documento) ? '06' : '05');
            window.open(
                this.apiService.baseUrl +
                    '/api/reporte/dte/' +
                    d.id +
                    '/' +
                    tipoDte +
                    '/?token=' +
                    this.apiService.auth_token(),
                'Impresión',
                'width=400'
            );
            this.emiting = false;
            this.saving = false;
            this.router.navigate(['/devoluciones/ventas']);
            this.alertService.success(
                'Devolución de venta creada',
                'La devolución de venta fue guardada exitosamente.'
            );
            this.cdr.markForCheck();
        }).catch((error) => {
            this.emiting = false;
            this.saving = false;
            this.alertService.warning('El documento no fue emitido.', error);
            this.router.navigate(['/devoluciones/ventas']);
            this.alertService.success(
                'Devolución de venta creada',
                'La devolución de venta fue guardada exitosamente.'
            );
            this.cdr.markForCheck();
        });
    }

    private enviarDteCorreo(devolucion: any): void {
        this.apiService.store('enviarDTE', devolucion).pipe(this.untilDestroyed()).subscribe({
            next: () => {
                this.alertService.success(this.countryI18n.fe('sendSuccessTitle'), this.countryI18n.fe('sendSuccessBody'));
            },
            error: () => {
                this.alertService.error(this.countryI18n.fe('sendError'));
            }
        });
    }

    private finalizarTrasGuardarDevolucion(devolucion: any): void {
        this.saving = false;
        if (
            devolucion.tipo_documento == 'Factura' ||
            devolucion.tipo_documento == 'Credito Fiscal' ||
            devolucion.tipo_documento == 'Ticket'
        ) {
            this.imprimirDocDevolucion(devolucion);
        }
        this.router.navigate(['/devoluciones/ventas']);
        this.alertService.success(
            'Devolucion de venta creada',
            'La devolución de venta fue guardado exitosamente.'
        );
        this.cdr.markForCheck();
    }

    public imprimirDocDevolucion(devolucion:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + devolucion.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }

}
