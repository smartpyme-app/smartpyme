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
    public usuario:any = {};
    public detalle:any = {};
    public composicion:any = {};
    public supervisor:any = {};

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;
    
    @ViewChild('mloteVenta')
    public mloteVenta!: TemplateRef<any>;

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

    /** Aplica gravada/exenta/no_sujeta según tipo_gravado del detalle. IVA por línea = diferencia que hace cuadrar (2 decimales). Respeta cobrar_impuestos. */
    private aplicarTipoGravado(detalle: any) {
        const total = parseFloat(detalle.total) || 0;
        detalle.gravada = 0;
        detalle.exenta = 0;
        detalle.no_sujeta = 0;
        const tipo = (detalle.tipo_gravado || 'gravada').toLowerCase();
        if (tipo === 'gravada') {
            detalle.gravada = total;
            const pct = (detalle.porcentaje_impuesto != null && detalle.porcentaje_impuesto !== '')
                ? Number(detalle.porcentaje_impuesto)
                : (this.apiService.auth_user().empresa?.iva ?? 0);
            detalle.iva = parseFloat((total * (pct / 100)).toFixed(4));
        } else if (tipo === 'exenta') {
            detalle.exenta = total;
            detalle.iva = 0;
        } else {
            detalle.no_sujeta = total;
            detalle.iva = 0;
        }
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

        detalle.sub_total = Number((parseFloat(detalle.cantidad) * parseFloat(detalle.precio)).toFixed(4));
        detalle.total_costo  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(4);
        detalle.total  = (parseFloat(detalle.sub_total) - parseFloat(detalle.descuento)).toFixed(4);
        this.aplicarTipoGravado(detalle);
        this.update.emit(this.venta);
        this.sumTotal.emit();
    }

    public onTipoGravadoChange(detalle: any) {
        this.aplicarTipoGravado(detalle);
        this.update.emit(this.venta);
        this.sumTotal.emit();
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
            
            if(!this.detalle.tipo_gravado){
                this.detalle.tipo_gravado = 'gravada';
            }
            if(!this.detalle.exenta){
                this.detalle.exenta = 0;
            }
            if(!this.detalle.no_sujeta){
                this.detalle.no_sujeta = 0;
            }

            if(!this.detalle.cuenta_a_terceros){
                this.detalle.cuenta_a_terceros = 0;
            }

            this.detalle.sub_total = Number((parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.precio)).toFixed(4));
            if(!this.detalle.total || detalle){
                this.detalle.total = (parseFloat(this.detalle.sub_total) - parseFloat(this.detalle.descuento || 0)).toFixed(4);
            }

            this.aplicarTipoGravado(this.detalle);

            if(!this.detalle.id_vendedor){
                this.detalle.id_vendedor = this.venta.id_vendedor;
            }

            // Si el producto tiene inventario por lotes, verificar si necesita selección manual
            if (producto.inventario_por_lotes) {
                const metodologia = this.getLotesMetodologia();
                if (metodologia === 'Manual') {
                    // Si es manual, abrir modal para seleccionar lote
                    this.detalle.inventario_por_lotes = true;
                    this.detalle.lote_id = null;
                    if (!detalle) {
                        this.venta.detalles.push(this.detalle);
                    }
                    this.update.emit(this.venta);
                    // Abrir modal automáticamente para seleccionar lote
                    setTimeout(() => {
                        this.abrirModalLoteVenta(this.mloteVenta, this.detalle);
                    }, 100);
                    return;
                } else {
                    // Si es automático, el backend se encargará de seleccionar el lote
                    this.detalle.inventario_por_lotes = true;
                    this.detalle.lote_id = null; // Se asignará automáticamente en el backend
                }
            } else {
                this.detalle.inventario_por_lotes = false;
                this.detalle.lote_id = null;
            }
            
            if(!detalle)
                this.venta.detalles.push(this.detalle);

            this.update.emit(this.venta);
            this.detalle = {};
            if (this.modalRef) { this.modalRef.hide() }
            console.log(this.venta);
        }

    // Métodos para gestión de lotes en ventas
    public lotes: any[] = [];
    public loteSeleccionado: any = null;
    public detalleConLote: any = null;

    getLotesMetodologia(): string {
        const empresa = this.apiService.auth_user()?.empresa;
        if (empresa?.custom_empresa?.configuraciones?.lotes_metodologia) {
            return empresa.custom_empresa.configuraciones.lotes_metodologia;
        }
        return 'FIFO'; // Por defecto
    }

    abrirModalLoteVenta(template: TemplateRef<any>, detalle: any) {
        this.detalleConLote = detalle;
        this.cargarLotesDisponiblesVenta();
        this.modalRef = this.modalService.show(template, {class: 'modal-lg'});
    }

    cargarLotesDisponiblesVenta() {
        if (!this.detalleConLote?.id_producto || !this.venta.id_bodega) return;
        
        this.loading = true;
        this.apiService.getAll('lotes/disponibles', {
            id_producto: this.detalleConLote.id_producto,
            id_bodega: this.venta.id_bodega,
            cantidad: this.detalleConLote.cantidad
        }).subscribe(lotes => {
            this.lotes = lotes;
            this.loading = false;
        }, error => {
            this.alertService.error(error);
            this.loading = false;
        });
    }

    seleccionarLoteVenta(lote: any) {
        this.detalleConLote.lote_id = lote.id;
        this.loteSeleccionado = lote;
        this.modalRef.hide();
        this.update.emit(this.venta);
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
