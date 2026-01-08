import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { CompraProductoComponent } from '../compra-producto/compra-producto.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-compra-detalles',
    templateUrl: './compra-detalles.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, CompraProductoComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CompraDetallesComponent extends BaseModalComponent implements OnInit {

  @Input() compra: any = {};
  @Input() isOrdenCompra: boolean = false;
  public detalle: any = {};
  public supervisor: any = {};

  @Output() update = new EventEmitter();
  @Output() OndeletedItem = new EventEmitter();
  @Output() sumTotal = new EventEmitter();

  @ViewChild('msupervisor')
  public supervisorTemplate!: TemplateRef<any>;

  public buscador: string = '';
  public override loading: boolean = false;

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private cdr: ChangeDetectorRef
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {

  }

  openModalEdit(template: TemplateRef<any>, detalle: any) {
    this.detalle = detalle;
    this.openModal(template, { class: 'modal-md', backdrop: 'static' });
  }

  public updateTotal(detalle: any) {
    if (!detalle.cantidad) {
      detalle.cantidad = 0;
    }
    detalle.total  = (parseFloat((detalle.cantidad ?? 0)) * parseFloat((detalle.costo ?? 0)) - parseFloat((detalle.descuento ?? 0))).toFixed(2);
    detalle.fobTotal = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo) - parseFloat(detalle.descuento)).toFixed(2);
    this.cdr.markForCheck();
    this.update.emit(this.compra);
  }

  public modalSupervisor(detalle: any) {
    this.detalle = detalle;
    this.openModal(this.supervisorTemplate, { class: 'modal-xs' });
  }

  public supervisorCheck() {
    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.store('usuario-validar', this.supervisor)
        .pipe(this.untilDestroyed())
        .subscribe(supervisor => {
            this.closeModal();
            this.delete(this.detalle);
            this.loading = false;
            this.supervisor = {};
            this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
  }

  // Agregar detalle
  productoSelect(producto: any): void {
    this.detalle = Object.assign({}, producto);
    this.detalle.id = null;

    // Verifica si el producto ya fue ingresado
    let detalle = this.compra.detalles.find((x: any) => x.id_producto == this.detalle.id_producto);

    if (detalle) {
      this.detalle = detalle;
      this.detalle.cantidad += producto.cantidad;
    }
    this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
    this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.costo) - parseFloat(this.detalle.descuento)).toFixed(2);
    this.detalle.fobTotal = (this.detalle.costo * this.detalle.cantidad);

    if (!detalle)
      this.compra.detalles.push(this.detalle);

    this.cdr.markForCheck();
    this.update.emit(this.compra);
    console.log(this.compra);
    this.detalle = {};
    if (this.modalRef) { this.closeModal(); }

  }

  // Eliminar detalle
  public delete(detalle: any) {

    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        const indexAEliminar = this.compra.detalles.findIndex((item: any) => item.id_producto === detalle.id_producto);
        if (indexAEliminar !== -1) {
            if(detalle.id) {
                this.apiService.delete('compra/detalle/', detalle.id)
                    .pipe(this.untilDestroyed())
                    .subscribe(detalle => {
                        this.compra.detalles.splice(indexAEliminar, 1);
                        this.cdr.markForCheck();
                        this.update.emit(this.compra);
                    },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
            }else{
                this.compra.detalles.splice(indexAEliminar, 1);
                this.cdr.markForCheck();
                this.update.emit(this.compra);
            }

        }
        this.OndeletedItem.emit({ detalles: this.compra.detalles });
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });

  }

  public sumTotalEmit() {
    this.sumTotal.emit();
  }

}
