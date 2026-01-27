import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TiendaVentaBuscadorComponent } from '../buscador/tienda-venta-buscador.component';
import { TiendaVentaProductoComponent } from '../productos/tienda-venta-producto.component';
import { TiendaVentaPaquetesComponent } from '../paquetes/tienda-venta-paquetes.component';
import { TiendaVentaCitasComponent } from '../citas/tienda-venta-citas.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import Swal from 'sweetalert2';
import { LazyImageDirective } from '../../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-venta-detalles',
    templateUrl: './venta-detalles.component.html',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        TiendaVentaBuscadorComponent,
        TiendaVentaProductoComponent,
        TiendaVentaPaquetesComponent,
        TiendaVentaCitasComponent,
        LazyImageDirective
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VentaDetallesComponent extends BaseModalComponent implements OnInit {

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
    this.usuario = this.apiService.auth_user();
  }

  openModalEdit(template: TemplateRef<any>, detalle: any) {
    this.detalle = detalle;
    this.openModal(template, { class: 'modal-md', backdrop: 'static' });
  }

  public updateTotal(detalle:any){
    if(!detalle.cantidad){
      detalle.cantidad = 0;
    }
    if(detalle.descuento_porcentaje){
      detalle.descuento = Number((detalle.cantidad * (detalle.precio * (detalle.descuento_porcentaje / 100))).toFixed(4));
    }else if(detalle.descuento_monto){
      detalle.descuento = Number((detalle.cantidad * detalle.descuento_monto).toFixed(4));
    }else{
      detalle.descuento = 0;
    }

    detalle.total_costo = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(4);
    detalle.total = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(4);
    detalle.gravada = detalle.total;
    this.cdr.markForCheck();

    // Recalcular IVA cuando cambia la cantidad, precio o descuento
    if(detalle.iva !== undefined && detalle.iva !== null){
      detalle.iva = parseFloat(detalle.total) * (this.apiService.auth_user().empresa.iva / 100);
    }

    this.update.emit(this.venta);
  }

  public modalSupervisor(detalle: any) {
    this.detalle = detalle;
    this.openModal(this.supervisorTemplate, { class: 'modal-xs' });
  }

  public openModalCompuesto(template: TemplateRef<any>, composicion: any) {
    this.composicion = composicion;
    console.log(this.composicion);
    this.openModal(template, { class: 'modal-md', backdrop: 'static' });
  }

  public supervisorCheck() {
    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.store('usuario-validar', this.supervisor)
        .pipe(this.untilDestroyed())
        .subscribe(supervisor => {
      if (this.modalRef) {
        this.closeModal();
      }
      this.delete(this.detalle);
      this.loading = false;
      this.supervisor = {};
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
  }

  // Agregar detalle
  productoSelect(producto: any): void {

            // Validar stock solo para productos (no servicios)
            if (producto.tipo != 'Servicio' && producto.stock !== null && producto.stock !== undefined) {
                // Verificar si hay suficiente stock para la cantidad solicitada
                const stockDisponible = parseFloat(producto.stock) || 0;
                const cantidadRequerida = parseFloat(producto.cantidad) || 1;

                if (stockDisponible < cantidadRequerida) {
                    // Si la empresa no permite vender sin stock
                    if (this.apiService.auth_user().empresa.vender_sin_stock == 0) {

                      if (this.apiService.auth_user().codigo_autorizacion) {

                        Swal.fire({
                              title: 'Stock insuficiente',
                              html: `El producto <strong>${producto.nombre || producto.descripcion}</strong> tiene stock disponible: <strong>${stockDisponible}</strong><br>Se requiere: <strong>${cantidadRequerida}</strong><br><br>Ingrese la clave de autorización para vender sin Stock`,
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
                                return new Promise((resolve:any, reject:any) => {
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
                            }).catch((error: any) => {
                              Swal.fire('Error', error, 'error');
                            });

                      }else{
                          Swal.fire({
                            title: 'Stock insuficiente',
                            html: `El producto <strong>${producto.nombre || producto.descripcion}</strong> tiene stock disponible: <strong>${stockDisponible}</strong><br>Se requiere: <strong>${cantidadRequerida}</strong><br><br>No hay códigos de autorización configurados. No se puede vender sin stock.`,
                            icon: 'warning',
                            confirmButtonText: 'Aceptar'
                          });
                          return;
                      }
                    }else{
                        // Si la empresa permite vender sin stock, mostrar advertencia pero permitir continuar
                        Swal.fire({
                          title: 'Advertencia de stock',
                          html: `El producto <strong>${producto.nombre || producto.descripcion}</strong> tiene stock disponible: <strong>${stockDisponible}</strong><br>Se requiere: <strong>${cantidadRequerida}</strong><br><br>La venta continuará ya que está permitido vender sin stock.`,
                          icon: 'warning',
                          showCancelButton: true,
                          confirmButtonText: 'Continuar',
                          cancelButtonText: 'Cancelar'
                        }).then((result) => {
                          if (result.isConfirmed) {
                            this.addDetalle(producto);
                          }
                        });
                        return;
                    }
                }
            }

            // Si pasa todas las validaciones o es un servicio, agregar el detalle
            this.addDetalle(producto);
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

    if(!this.detalle.total || detalle){
      this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.precio) - parseFloat(this.detalle.descuento)).toFixed(4);
    }

    if (!this.detalle.gravada) {
      this.detalle.gravada = this.detalle.total;
    }

            // Recalcular IVA siempre que se actualice el total (especialmente cuando se agrega más cantidad)
            if(!this.detalle.iva || detalle){
                this.detalle.iva = this.detalle.total * (this.apiService.auth_user().empresa.iva / 100);
            }

    if (!this.detalle.id_vendedor) {
      this.detalle.id_vendedor = this.venta.id_vendedor;
    }

    if (!detalle)
      this.venta.detalles.push(this.detalle);

    this.cdr.markForCheck();
    this.update.emit(this.venta);
    this.detalle = {};
    if (this.modalRef) {
      this.closeModal();
    }
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

            this.apiService.delete(endpoint + '/', detalle.id)
                .pipe(this.untilDestroyed())
                .subscribe(detalle => {
              this.venta.detalles.splice(indexAEliminar, 1);
              this.cdr.markForCheck();
              this.update.emit(this.venta);
            }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
          } else {
            this.venta.detalles.splice(indexAEliminar, 1);
            this.cdr.markForCheck();
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

  getColumnCount(): number {
    let count = 5; // Base columns (Product, Quantity, Price, Discount, Total, Actions)
    if (this.usuario.empresa.vendedor_detalle_venta) count++;
    count += this.selectedCustomFields.length;
    return count;
  }

}
