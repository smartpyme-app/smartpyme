import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { DistribucionLotesModalComponent } from '@shared/modals/distribucion-lotes/distribucion-lotes-modal.component';
import { resolverPorcentajeImpuestoCompra } from '@utils/impuestos-compra.util';
import { factorConversionDetalle, textoResumenLotesDetalle } from '@utils/lotes-venta.util';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-compra-detalles',
  templateUrl: './compra-detalles.component.html'
})
export class CompraDetallesComponent implements OnInit {

    @Input() compra: any = {};
    public detalle:any = {};
    public supervisor:any = {};

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('lotesModal')
    public lotesModal!: DistribucionLotesModalComponent;

    public buscador:string = '';
    public loading:boolean = false;
    public skipLimpiarLotes = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public updateTotal(detalle: any) {
        const cantidad = parseFloat(detalle.cantidad ?? 0);
        const costo = parseFloat(detalle.costo ?? 0);
        const descuento = parseFloat(detalle.descuento ?? 0);
        detalle.total = (cantidad * costo - descuento).toFixed(2);
        const totalLinea = parseFloat(detalle.total) || 0;
        const empresa = this.apiService.auth_user()?.empresa;
        const pctDetalle = resolverPorcentajeImpuestoCompra(
            detalle.porcentaje_impuesto,
            empresa?.iva,
            !!this.compra.cobrar_impuestos,
            empresa?.pais
        );
        if (this.compra.cobrar_impuestos && totalLinea > 0) {
            detalle.porcentaje_impuesto = pctDetalle;
            detalle.iva = parseFloat((totalLinea * (pctDetalle / 100)).toFixed(4));
        } else {
            detalle.iva = 0;
        }
        this.update.emit(this.compra);
        this.sumTotal.emit();
    }

    public onCantidadChange(detalle: any) {
        if (!this.skipLimpiarLotes && this.requiereDistribucionLotes(detalle)) {
            detalle.lotes_asignados = null;
            detalle.lote_id = null;
            detalle.lote = null;
        }
        this.updateTotal(detalle);
    }

    public modalSupervisor(detalle:any){
        this.detalle = detalle;
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
            this.modalRef.hide();
            this.delete(this.detalle);
            this.loading = false;
            this.supervisor = {};
        },error => {this.alertService.error(error); this.loading = false; });
    }

    productoSelect(producto:any):void{
        this.detalle = Object.assign({}, producto);
        this.detalle.id = null;
        this.detalle.inventario_por_lotes = !!producto.inventario_por_lotes;
        if (!this.detalle.lote_id) {
            this.detalle.lote_id = null;
            this.detalle.lote = null;
            this.detalle.lotes_asignados = null;
        }

        let detalleExistente = this.compra.detalles.find((x:any) => x.id_producto == this.detalle.id_producto);

        if(detalleExistente) {
            this.detalle = detalleExistente;
            this.detalle.cantidad += producto.cantidad;
        }

        this.agregarDetalleFinal();
    }

    agregarDetalleFinal() {
        this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
        this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.costo) - parseFloat(this.detalle.descuento ?? 0)).toFixed(2);
        this.updateTotal(this.detalle);

        let detalleExistente = this.compra.detalles.find((x: any) => x.id_producto == this.detalle.id_producto);
        if (!detalleExistente) {
            this.compra.detalles.push(this.detalle);
        }

        const detalleAgregado = detalleExistente || this.detalle;

        this.update.emit(this.compra);

        if (this.requiereDistribucionLotes(detalleAgregado)) {
            setTimeout(() => {
                this.abrirModalLote(detalleAgregado);
            }, 100);
            return;
        }

        this.detalle = {};
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }

    public abrirModalLote(detalle: any) {
        if (!this.compra.id_bodega) {
            this.alertService.warning('Bodega requerida', 'Seleccione una bodega antes de asignar lotes.');
            return;
        }
        this.detalle = detalle;
        this.lotesModal.abrir(detalle, this.compra.id_bodega);
    }

    public onLotesConfirmados(detalle: any): void {
        const factor = factorConversionDetalle(detalle);
        if (detalle.lotes_asignados?.length) {
            const totalBase = detalle.lotes_asignados.reduce(
                (s: number, p: any) => s + (parseFloat(String(p.cantidad)) || 0),
                0
            );
            detalle.cantidad = factor > 0 ? totalBase / factor : totalBase;
        }
        this.skipLimpiarLotes = true;
        this.updateTotal(detalle);
        this.skipLimpiarLotes = false;
        this.update.emit(this.compra);
        this.sumTotal.emit();
    }

    public requiereDistribucionLotes(detalle: any): boolean {
        return !!detalle?.inventario_por_lotes && this.isLotesActivo();
    }

    public textoLotesDetalle(detalle: any): string {
        return textoResumenLotesDetalle(detalle);
    }

    public delete(detalle:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                const indexAEliminar = this.compra.detalles.findIndex((item:any) => item.id_producto === detalle.id_producto);
                if (indexAEliminar !== -1) {
                    if(detalle.id) {
                        this.apiService.delete('compra/detalle/', detalle.id).subscribe(detalle => {
                            this.compra.detalles.splice(indexAEliminar, 1);
                            this.update.emit(this.compra);
                        },error => {this.alertService.error(error); this.loading = false; });
                    }else{
                        this.compra.detalles.splice(indexAEliminar, 1);
                        this.update.emit(this.compra);
                    }

                }

          } else if (result.dismiss === Swal.DismissReason.cancel) {
          }
        });

    }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }

    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

}
