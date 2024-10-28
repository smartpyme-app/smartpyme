import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, OnChanges, SimpleChanges } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-producto-combo-detalles',
  templateUrl: './combo-detalles.component.html'
})
export class ComboDetallesComponent implements OnInit, OnChanges {

  @Input() producto: any = {};
  @Input() mode: 'create' | 'edit' | 'show' = 'create';
  public detalle: any = {};
  public supervisor: any = {};


  @Output() update = new EventEmitter();
  @Output() sumTotal = new EventEmitter();

  modalRef!: BsModalRef;

  @ViewChild('msupervisor')
  public supervisorTemplate!: TemplateRef<any>;

  public buscador: string = '';
  public loading: boolean = false;

  constructor(
    private apiService: ApiService, private alertService: AlertService,
    private modalService: BsModalService
  ) { }

  ngOnInit() {
  }
  ngOnChanges(changes: SimpleChanges): void {
    if (changes['mode']) {

    }
  }

  openModalEdit(template: TemplateRef<any>, detalle: any) {
    this.detalle = detalle;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  public updateTotal(detalle: any) {
    if (!detalle.cantidad) {
      detalle.cantidad = 0;
    }

    detalle.total = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo) - parseFloat(detalle.descuento || 0)).toFixed(2);
    this.update.emit(this.producto);
  }

  public modalSupervisor(detalle: any) {
    this.detalle = detalle;
    this.modalRef = this.modalService.show(this.supervisorTemplate, { class: 'modal-xs' });
  }

  public supervisorCheck() {
    this.loading = true;
    this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
      this.modalRef.hide();
      this.delete(this.detalle);
      this.loading = false;
      this.supervisor = {};
    }, error => { this.alertService.error(error); this.loading = false; });
  }

  // Agregar detalle
  productoSelect(producto: any): void {
    this.detalle = Object.assign({}, producto);
    this.detalle.id = null;

    // Verifica si el producto ya fue ingresado
    let detalle = this.producto.detalles.find((x: any) => x.id_producto == this.detalle.id_producto);

    if (detalle) {
      this.detalle = detalle;
      this.detalle.cantidad += producto.cantidad;
    }
    this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
    this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.costo) - parseFloat(this.detalle.descuento)).toFixed(2);


    if (!detalle)
      this.producto.detalles.push(this.detalle);

    this.update.emit(this.producto);
    this.detalle = {};
    if (this.modalRef) { this.modalRef.hide() }

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
        const indexAEliminar = this.producto.detalles.findIndex((item: any) => item.id_producto === detalle.id_producto);
        if (indexAEliminar !== -1) {
          this.producto.detalles.splice(indexAEliminar, 1);
          // Ejecutar la función pasada por el padre
          this.sumTotal.emit();
        }
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });

  }

  public sumTotalEmit() {
    this.sumTotal.emit();
  }


}
