import { Component, OnInit, TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { Location } from '@angular/common';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { SumPipe }     from '@pipes/sum.pipe';
import { CrearAbonoVentaComponent } from '@shared/modals/crear-abono-venta/crear-abono-venta.component';
import { EditarAbonoComponent } from '@shared/modals/editar-abono/editar-abono.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FE_PAIS_CR, FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import { detalleTieneExoneracionCr } from '@shared/modals/fe-cr-exoneracion-detalle/fe-cr-exoneracion-detalle.util';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import { porcentajeIvaDetalle, redondearMoneda } from '@utils/impuestos-venta.util';

@Component({
    selector: 'app-venta',
    templateUrl: './venta.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, CrearAbonoVentaComponent, EditarAbonoComponent, LazyImageDirective, TooltipModule, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,

})
export class VentaComponent implements OnInit {

    public venta:any = {};
    public proyecto:any ={};
    public usuario:any = {};
    public loading = false;
    public saving = false;
    public type: string = '';

    public abonoEdit:any = {};

    modalRef!: BsModalRef;
    public filtros:any = {
        bandera: true,
    };
    public zoomImageUrl: string = '';

    public customFields:any = [];

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        public apiService:ApiService, 
        private alertService:AlertService, 
        private sumPipe:SumPipe,
        private route: ActivatedRoute, 
        private router: Router, 
        private modalService: BsModalService,
        private location: Location,
        private cdr: ChangeDetectorRef
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
        this.route.data
            .pipe(this.untilDestroyed())
            .subscribe(data => {
                this.type = data['type']; // 'venta' o 'cotizacion'
            });
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();
    }

    /** Detalle MH exportación (incoterm, recinto, etc.): solo El Salvador. */
    esFacturacionElSalvador(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_SV;
    }

    esFeCostaRica(): boolean {
        return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
    }

    readonly detalleTieneExoneracionCr = detalleTieneExoneracionCr;

    etiquetaTipoGravado(detalle: any): string {
        const t = (detalle?.tipo_gravado || 'gravada').toLowerCase();
        if (t === 'exenta') return 'Exenta';
        if (t === 'exonerada') return 'Exonerada';
        if (t === 'no_sujeta') return 'No sujeta';
        return 'Gravada';
    }

    // public loadAll(){
    //     if(this.modalRef){
    //         this.modalRef.hide();
    //     }

    //     this.venta.id = +this.route.snapshot.paramMap.get('id')!;
    //     this.loading = true;
    //     const endpoint = this.type === 'cotizacion' ? 'cotizacion/' : 'venta/';
    //     if(this.type === 'cotizacion'){
    //         this.apiService.getAll('custom-fields',this.filtros).subscribe(customFields => {
    //             this.customFields = customFields;
    //         }, error => {
    //             this.alertService.error(error);
    //         });
    //     }

    //     //this.apiService.read('venta/', this.venta.id).subscribe(venta => {
    //     this.apiService.read(endpoint, this.venta.id).subscribe(venta => {
    //     this.venta = venta;
    //     const isCotizacion = this.type === 'cotizacion' ? true : false;
    //     this.venta.cotizacion = isCotizacion ? 1 : 0;

    //     if(this.venta.id_proyecto){
    //         this.apiService.read('proyecto/',this.venta.id_proyecto).subscribe(proyecto => {
    //             this.proyecto = proyecto;
    //             this.loading = false;
    //         }, error => {this.alertService.error(error); this.loading = false;});

    //     }

    //     this.loading = false;
    //     }, error => {this.alertService.error(error); this.loading = false;});

    // }

    public loadAll() {
        if (this.modalRef) {
            this.modalRef.hide();
        }

        this.venta.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;
        const endpoint = this.type === 'cotizacion' ? 'cotizacion/' : 'venta/';

        // Cargar custom fields si es cotización
        if (this.type === 'cotizacion') {
            this.apiService.getAll('custom-fields', this.filtros)
                .pipe(this.untilDestroyed())
                .subscribe(
                    customFields => {
                        this.customFields = customFields;
                        // Continuar con la carga de la cotización después de obtener los campos
                        this.loadCotizacion();
                        this.cdr.markForCheck();
                    },
                    error => {
                        this.alertService.error(error);
                        this.loading = false;
                        this.cdr.markForCheck();
                    }
                );
        } else {
            this.loadCotizacion();
        }
    }

    private loadCotizacion() {
        this.apiService.read(this.type === 'cotizacion' ? 'cotizacion/' : 'venta/', this.venta.id)
            .pipe(this.untilDestroyed())
            .subscribe(
                venta => {
                    this.venta = venta;
                    this.venta.cotizacion = this.type === 'cotizacion' ? 1 : 0;

                    if (this.venta.id_proyecto) {
                        this.loadProyecto();
                    } else {
                        this.loading = false;
                        this.cdr.markForCheck();
                    }
                },
                error => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            );
    }

    private loadProyecto() {
        this.apiService.read('proyecto/', this.venta.id_proyecto)
            .pipe(this.untilDestroyed())
            .subscribe(
                proyecto => {
                    this.proyecto = proyecto;
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            );
    }




    public setEstado(abono:any){
        this.saving = false;
        this.cdr.markForCheck();
        this.apiService.store('venta/abono', abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
                this.loadAll();
                this.saving = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});
    }

    public imprimirRecibo(abono:any){
        window.open(this.apiService.baseUrl + '/api/venta/abono/imprimir/' + abono.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.modalRef = this.modalService.show(template);
    }

    hasCustomField(fieldId: number): boolean {

        return this.venta.detalles?.some((detalle: any) =>
            detalle.custom_fields?.some((cf: any) => cf.custom_field?.id === fieldId)
        ) || false;
    }

    getCustomFieldValue(detalle: any, fieldId: number): string {
        const customField = detalle.custom_fields?.find(
            (cf: any) => cf.custom_field?.id === fieldId
        );
        return customField ? customField.value : '';
    }

    public openModalEditAbono(template: TemplateRef<any>, abono: any) {
        this.abonoEdit = { ...abono };
        this.modalRef = this.modalService.show(template);
    }

    public onAbonoSaved() {
        this.modalRef.hide();
        this.loadAll();
    }

    public goBack() {
        this.location.back();
    }

    public getTotalConPropina(): number {
        const total = parseFloat(this.venta?.total || 0);
        const propina = parseFloat(this.venta?.propina || 0);
        return total + propina;
    }

    public hasImage(img: any): boolean {
        return !!img && img !== 'default.png' && img !== 'default.jpg' && img !== 'productos/default.jpg' && img !== 'null' && img !== 'undefined';
    }

    public zoomImage(img: any, dialog: any) {
        if (this.hasImage(img)) {
            this.zoomImageUrl = this.apiService.baseUrl + '/img/' + img;
            dialog.showModal();
        }
    }

    /** Factor IVA de la línea (1 si no hay IVA / exenta / no sujeta). */
    private factorIvaDetalle(detalle: any): number {
        const tipo = String(detalle?.tipo_gravado || 'gravada').toLowerCase();
        if (tipo !== 'gravada') {
            return 1;
        }
        const ivaVenta = parseFloat(String(this.venta?.iva ?? 0)) || 0;
        const cobrar = ivaVenta > 0 || !!this.venta?.cobrar_impuestos;
        if (!cobrar) {
            return 1;
        }
        const ivaEmpresa = this.apiService.auth_user()?.empresa?.iva;
        const pct = porcentajeIvaDetalle(
            detalle,
            ivaEmpresa,
            true,
            this.apiService.auth_user()?.empresa?.pais
        );
        return pct > 0 ? 1 + pct / 100 : 1;
    }

    public precioDetalleConIva(detalle: any): number {
        return redondearMoneda(
            (parseFloat(String(detalle?.precio ?? 0)) || 0) * this.factorIvaDetalle(detalle)
        );
    }

    public descuentoDetalleConIva(detalle: any): number {
        return redondearMoneda(
            (parseFloat(String(detalle?.descuento ?? 0)) || 0) * this.factorIvaDetalle(detalle)
        );
    }

    public totalDetalleConIva(detalle: any): number {
        return redondearMoneda(
            (parseFloat(String(detalle?.total ?? 0)) || 0) * this.factorIvaDetalle(detalle)
        );
    }

    /** Mismo Sub Total que facturación: base sin IVA después de descuento. */
    public subTotalFacturacion(): number {
        if (this.venta?.referencia_shopify) {
            return redondearMoneda(parseFloat(String(this.venta?.gravada ?? 0)) || 0);
        }
        return redondearMoneda(parseFloat(String(this.venta?.sub_total ?? 0)) || 0);
    }

}
