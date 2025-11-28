import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-venta-detalles-v2',
  templateUrl: './venta-detalles-v2.component.html'
})
export class VentaDetallesV2Component implements OnInit {

    @Input() venta: any = {};
    @Input() usuarios: any = {};
    public usuario:any = {};
    public detalle:any = {};
    public composicion:any = {};
    public supervisor:any = {};

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    /**
     * Calcula el precio sin IVA a partir de un precio con IVA incluido
     */
    private calcularPrecioSinIva(precioConIva: number, porcentajeIva: number): number {
        if (!porcentajeIva || porcentajeIva === 0) {
            return precioConIva;
        }
        return precioConIva / (1 + porcentajeIva / 100);
    }

    /**
     * Calcula el IVA a partir de un precio con IVA incluido
     */
    private calcularIvaDesdePrecioConIva(precioConIva: number, porcentajeIva: number): number {
        if (!porcentajeIva || porcentajeIva === 0) {
            return 0;
        }
        return precioConIva - this.calcularPrecioSinIva(precioConIva, porcentajeIva);
    }

    /**
     * Obtiene el porcentaje de IVA de la empresa
     */
    private obtenerPorcentajeIvaTotal(): number {
        if (!this.venta.cobrar_impuestos) {
            return 0;
        }
        // En v2, usar el IVA de la empresa directamente
        return this.apiService.auth_user()?.empresa?.iva || 0;
    }

    public updateTotal(detalle:any){
        if(!detalle.cantidad){
            detalle.cantidad = 0;
        }

        // En v2, el precio ya incluye IVA, así que los descuentos se aplican sobre el precio con IVA
        const porcentajeIvaTotal = this.obtenerPorcentajeIvaTotal();
        
        if(detalle.descuento_porcentaje){
            // Descuento porcentual sobre precio con IVA incluido
            detalle.descuento = Number((detalle.cantidad * (detalle.precio * (detalle.descuento_porcentaje / 100))).toFixed(4));
        }else if(detalle.descuento_monto){
            // Descuento monto sobre precio con IVA incluido
            detalle.descuento = Number((detalle.cantidad * detalle.descuento_monto).toFixed(4));
        }else{
            detalle.descuento = 0;
        }

        detalle.total_costo  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(4);
        
        // El total es precio con IVA * cantidad - descuento
        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(4);
        
        // La gravada es el precio sin IVA (desglosado)
        if (this.venta.cobrar_impuestos && porcentajeIvaTotal > 0) {
            const precioSinIva = this.calcularPrecioSinIva(parseFloat(detalle.total), porcentajeIvaTotal);
            detalle.gravada = precioSinIva.toFixed(4);
        } else {
            detalle.gravada = detalle.total;
        }
        
        this.update.emit(this.venta);
    }

    public modalSupervisor(detalle:any){
        this.detalle = detalle;
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public openModalCompuesto(template: TemplateRef<any>, composicion:any){
        this.composicion = composicion;
        console.log(this.composicion);
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
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
                            }).catch((error) => {
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

        public addDetalle(producto:any){
            this.detalle = Object.assign({}, producto);
            this.detalle.id = null;
            
            // Verifica si el producto ya fue ingresado
            let detalle = null;
            if(this.apiService.auth_user().empresa.agrupar_detalles_venta){
                detalle = this.venta.detalles.find((x:any) => x.id_producto == this.detalle.id_producto)
            }
                
            if(detalle) {
                this.detalle = detalle;
                this.detalle.cantidad += producto.cantidad;
            }

            this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
            
            if(!this.detalle.exenta){
                this.detalle.exenta = 0;
            }
            if(!this.detalle.no_sujeta){
                this.detalle.no_sujeta = 0;
            }

            if(!this.detalle.cuenta_a_terceros){
                this.detalle.cuenta_a_terceros = 0;
            }

            // En v2, el precio ya incluye IVA, así que el total es precio * cantidad - descuento
            if(!this.detalle.total || detalle){
                this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.precio) - parseFloat(this.detalle.descuento || 0)).toFixed(4);
            }

            // Calcular gravada (precio sin IVA)
            const porcentajeIvaTotal = this.obtenerPorcentajeIvaTotal();
            if (this.venta.cobrar_impuestos && porcentajeIvaTotal > 0) {
                const precioSinIva = this.calcularPrecioSinIva(parseFloat(this.detalle.total), porcentajeIvaTotal);
                this.detalle.gravada = precioSinIva.toFixed(4);
            } else {
                if(!this.detalle.gravada){
                    this.detalle.gravada = this.detalle.total;
                }
            }

            if(!this.detalle.id_vendedor){
                this.detalle.id_vendedor = this.venta.id_vendedor;
            }            
            
            if(!detalle)
                this.venta.detalles.push(this.detalle);

            this.update.emit(this.venta);
            this.detalle = {};
            if (this.modalRef) { this.modalRef.hide() }
            console.log(this.venta);
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
                let indexAEliminar:any;
                
                    if(detalle.id_paquete){
                        indexAEliminar = this.venta.detalles.findIndex((item:any) => item.id_paquete === detalle.id_paquete);
                    }else{
                        indexAEliminar = this.venta.detalles.findIndex((item:any) => item.id_producto === detalle.id_producto);
                    }
                    if (indexAEliminar !== -1) {
                        if(detalle.id) {
                            this.apiService.delete('venta/detalle/', detalle.id).subscribe(detalle => {
                                this.venta.detalles.splice(indexAEliminar, 1);
                                this.update.emit(this.venta);
                            },error => {this.alertService.error(error); this.loading = false; });
                        }else{
                            this.venta.detalles.splice(indexAEliminar, 1);
                            this.update.emit(this.venta);
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

    cambiarOpcion(composicion:any, opcion:any){
        let aux = Object.assign({}, composicion);

        console.log(composicion);
        console.log(opcion);

        composicion.id_compuesto = opcion.id_producto;
        composicion.nombre_compuesto     = opcion.nombre_producto;

        opcion.id_producto  = aux.id_compuesto;
        opcion.nombre_producto      = aux.nombre_compuesto;

        console.log(composicion);
        console.log(opcion);

    }


}

