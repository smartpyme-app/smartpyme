import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Location } from '@angular/common';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { porcentajeIvaDetalle, redondearMoneda } from '@utils/impuestos-venta.util';

@Component({
  selector: 'app-venta',
  templateUrl: './venta.component.html'
})
export class VentaComponent implements OnInit {

    public venta:any = {};
    public proyecto:any ={};
    public usuario:any = {};
    public loading = false;
    public saving = false;
    public consolidandoShopify = false;

    public abonoEdit:any = {};

    modalRef!: BsModalRef;
    public zoomImageUrl: string = '';

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
        private location: Location
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();
    }

    public loadAll(){
        if(this.modalRef){
            this.modalRef.hide();
        }
        
        this.venta.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;

        this.apiService.read('venta/', this.venta.id).subscribe(venta => {
        this.venta = venta;

        if(this.venta.id_proyecto){
            this.apiService.read('proyecto/',this.venta.id_proyecto).subscribe(proyecto => {
                this.proyecto = proyecto;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});

        }

        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public consolidarShopify(){
        if (!this.venta?.referencia_shopify || this.consolidandoShopify) {
            return;
        }
        this.consolidandoShopify = true;
        this.apiService.store('venta/' + this.venta.id + '/shopify/consolidar', {}).subscribe((resp: any) => {
            this.consolidandoShopify = false;
            if (resp?.status === 'ignored') {
                this.alertService.warning('Shopify', resp.mensaje);
                return;
            }
            if (resp?.venta) {
                this.venta = resp.venta;
            }
            this.alertService.success('Shopify', resp?.mensaje || 'Venta consolidada');
            this.loadAll();
        }, error => {
            this.consolidandoShopify = false;
            this.alertService.error(error);
        });
    }

    public setEstado(abono:any){
        this.saving = false;
        this.apiService.store('venta/abono', abono).subscribe(abono => {
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public imprimirRecibo(abono:any){
        window.open(this.apiService.baseUrl + '/api/venta/abono/imprimir/' + abono.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.modalRef = this.modalService.show(template);
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
        if (this.venta?.referencia_shopify && detalle?.precio_con_iva != null && detalle.precio_con_iva !== '') {
            return redondearMoneda(parseFloat(String(detalle.precio_con_iva)) || 0);
        }
        return redondearMoneda(
            (parseFloat(String(detalle?.precio ?? 0)) || 0) * this.factorIvaDetalle(detalle)
        );
    }

    /** Total de la línea con IVA, tal como lo cerró Shopify (gravada + exenta + IVA). */
    private totalLineaShopifyConIva(detalle: any): number {
        return redondearMoneda(
            (parseFloat(String(detalle?.gravada ?? 0)) || 0) +
            (parseFloat(String(detalle?.exenta ?? 0)) || 0) +
            (parseFloat(String(detalle?.no_sujeta ?? 0)) || 0) +
            (parseFloat(String(detalle?.iva ?? 0)) || 0)
        );
    }

    public descuentoDetalleConIva(detalle: any): number {
        if (this.venta?.referencia_shopify) {
            const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
            const descuento = redondearMoneda(
                cantidad * this.precioDetalleConIva(detalle) - this.totalLineaShopifyConIva(detalle)
            );
            return descuento > 0 ? descuento : 0;
        }
        return redondearMoneda(
            (parseFloat(String(detalle?.descuento ?? 0)) || 0) * this.factorIvaDetalle(detalle)
        );
    }

    /** Precio con IVA × cantidad − descuento con IVA (no reconstruir desde el neto `total`). */
    public totalDetalleConIva(detalle: any): number {
        if (this.venta?.referencia_shopify) {
            return this.totalLineaShopifyConIva(detalle);
        }
        const cantidad = parseFloat(String(detalle?.cantidad ?? 0)) || 0;
        return redondearMoneda(
            cantidad * this.precioDetalleConIva(detalle) - this.descuentoDetalleConIva(detalle)
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
