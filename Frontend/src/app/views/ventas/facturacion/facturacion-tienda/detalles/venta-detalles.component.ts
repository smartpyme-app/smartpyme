import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-venta-detalles',
  templateUrl: './venta-detalles.component.html'
})
export class VentaDetallesComponent implements OnInit {

    @Input() venta: any = {};
    public detalle:any = {};
    public supervisor:any = {};

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public updateTotal(detalle:any){
        if(detalle.descuento_porcentaje){
            detalle.descuento = detalle.cantidad * (detalle.precio * (detalle.descuento_porcentaje / 100));
        }else{
            detalle.descuento = 0;
        }

        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(2);
        this.update.emit(this.venta);
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

    // Agregar detalle
        productoSelect(producto:any):void{

            if (producto.stock < producto.cantidad) {
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
                        }).catch((error) => {
                          Swal.fire('Error', error, 'error');
                        });

                  }else{
                      alert('No hay códigos configurados');
                  }
                }else{
                    this.addDetalle(producto);
                }
            }else{
                this.addDetalle(producto);
            }
        }

        public addDetalle(producto:any){
            this.detalle = Object.assign({}, producto);
            this.detalle.id = null;
            
            // Verifica si el producto ya fue ingresado
            let detalle = this.venta.detalles.find((x:any) => x.id_producto == this.detalle.id_producto);
            
            if(detalle) {
                this.detalle = detalle;
                this.detalle.cantidad += producto.cantidad;
            }
            this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
            this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.precio) - parseFloat(this.detalle.descuento)).toFixed(2);
            
            
            if(!detalle)
                this.venta.detalles.push(this.detalle);

            this.update.emit(this.venta);
            this.detalle = {};
            if (this.modalRef) { this.modalRef.hide() }
        }

    // Eliminar detalle
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
                    for (var i = 0; i < this.venta.detalles.length; ++i) {
                        if (this.venta.detalles[i].producto_id === detalle.producto_id ){
                            this.venta.detalles.splice(i, 1);
                            this.update.emit(this.venta);
                            // Swal.fire('Eliminado', 'El detalle ha sido eliminado.', 'success');
                        }
                    }
              } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
              }
            });

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }


}
