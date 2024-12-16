import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-venta-detalles',
  templateUrl: './venta-detalles.component.html'
})
export class VentaDetallesComponent implements OnInit {

  @Input() venta: any = {};
  @Input() usuarios: any = {};
  @Input() customFields: any = {};  // Agregar input
  @Input() selectedCustomFields: number[] = [];
  @Input() cotizacion: number = 0;
  @Input() mode: "create" | "edit" | "show" = "create";
  public usuario: any = {};
  public detalle: any = {};
  public composicion: any = {};
  public supervisor: any = {};

  @Output() update = new EventEmitter();
  @Output() sumTotal = new EventEmitter();
  modalRef!: BsModalRef;

  @ViewChild('msupervisor')
  public supervisorTemplate!: TemplateRef<any>;

  public buscador: string = '';
  public loading: boolean = false;

  constructor(
    public apiService: ApiService, private alertService: AlertService,
    private modalService: BsModalService
  ) { }

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
  }

  openModalEdit(template: TemplateRef<any>, detalle: any) {
    this.detalle = detalle;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  public updateTotal(detalle: any) {
    if (!detalle.cantidad) {
      detalle.cantidad = 0;
    }
    if (detalle.descuento_porcentaje) {
      detalle.descuento = detalle.cantidad * (detalle.precio * (detalle.descuento_porcentaje / 100));
    } else {
      detalle.descuento = 0;
    }

    detalle.total_costo = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(4);
    detalle.total = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(4);
    detalle.gravada = detalle.total;
    this.update.emit(this.venta);
  }

  public modalSupervisor(detalle: any) {
    this.detalle = detalle;
    this.modalRef = this.modalService.show(this.supervisorTemplate, { class: 'modal-xs' });
  }

  public openModalCompuesto(template: TemplateRef<any>, composicion: any) {
    this.composicion = composicion;
    console.log(this.composicion);
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
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

    if (producto.tipo != 'Servicio' && (producto.stock < producto.cantidad)) {
      if (this.apiService.auth_user().empresa.vender_sin_stock == 0) {


        if (this.apiService.auth_user().codigo_autorizacion) {

          Swal.fire({
            title: 'Ingrese la clave de autorización',
            input: 'password',
            inputAttributes: {
              autocapitalize: 'off',
              autocorrect: 'off'
            },
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            preConfirm: (clave) => {
              // Aquí puedes realizar alguna validación de la clave ingresada
              // Devuelve una promesa que se resolverá o rechazará según la validación
              return new Promise((resolve: any, reject: any) => {
                if (clave == this.apiService.auth_user().codigo_autorizacion) {
                  resolve();
                } else {
                  reject('Clave incorrecta');
                }
              });
            },
            allowOutsideClick: () => !Swal.isLoading()
          }).then((result) => {
            if (result.isConfirmed) {
              this.addDetalle(producto);
            }
          }).catch((error) => {
            Swal.fire('Error', error, 'error');
          });

        } else {
          alert('No hay códigos configurados');
        }
      } else {
        this.addDetalle(producto);
      }
    } else {
      this.addDetalle(producto);
    }
  }

  public addDetalle(producto: any) {
    this.detalle = Object.assign({}, producto);
    this.detalle.id = null;

    // Verifica si el producto ya fue ingresado
    let detalle = null;
    if (this.apiService.auth_user().empresa.agrupar_detalles_venta) {
      detalle = this.venta.detalles.find((x: any) => x.id_producto == this.detalle.id_producto)
    }

    if (detalle) {
      this.detalle = detalle;
      this.detalle.cantidad += producto.cantidad;
    }

    this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);

    if (!this.detalle.exenta) {
      this.detalle.exenta = 0;
    }
    if (!this.detalle.no_sujeta) {
      this.detalle.no_sujeta = 0;
    }


    if (!this.detalle.cuenta_a_terceros) {
      this.detalle.cuenta_a_terceros = 0;
    }

    if (!this.detalle.total) {
      this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.precio) - parseFloat(this.detalle.descuento)).toFixed(4);
    }

    if (!this.detalle.gravada) {
      this.detalle.gravada = this.detalle.total;
    }

    if (!this.detalle.iva) {
      this.detalle.iva = this.detalle.total * (this.apiService.auth_user().empresa.iva / 100);
    }

    if (!this.detalle.id_vendedor) {
      this.detalle.id_vendedor = this.venta.id_vendedor;
    }

    if (!detalle)
      this.venta.detalles.push(this.detalle);

    this.update.emit(this.venta);
    this.detalle = {};
    if (this.modalRef) { this.modalRef.hide() }
    console.log(this.venta);
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
        let indexAEliminar: any;

        if (detalle.id_paquete) {
          indexAEliminar = this.venta.detalles.findIndex((item: any) => item.id_paquete === detalle.id_paquete);
        } else {
          indexAEliminar = this.venta.detalles.findIndex((item: any) => item.id_producto === detalle.id_producto);
        }
        if (indexAEliminar !== -1) {
          if (detalle.id) {
            console.log('venta', this.venta);
            const endpoint = this.venta.cotizacion == 1 ? 'cotizacion-venta-detalle' : 'venta-detalle';

            this.apiService.delete(endpoint + '/', detalle.id).subscribe(detalle => { 
              this.venta.detalles.splice(indexAEliminar, 1);
              this.update.emit(this.venta);
            }, error => { this.alertService.error(error); this.loading = false; });
          } else {
            this.venta.detalles.splice(indexAEliminar, 1);
            this.update.emit(this.venta);
          }

        }
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });

  }

  public sumTotalEmit() {
    this.sumTotal.emit();
  }

  cambiarOpcion(composicion: any, opcion: any) {
    let aux = Object.assign({}, composicion);

    console.log(composicion);
    console.log(opcion);

    composicion.id_compuesto = opcion.id_producto;
    composicion.nombre_compuesto = opcion.nombre_producto;

    opcion.id_producto = aux.id_compuesto;
    opcion.nombre_producto = aux.nombre_compuesto;

    console.log(composicion);
    console.log(opcion);

  }


}
