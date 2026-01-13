import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

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

    @ViewChild('mlote')
    public mloteTemplate!: TemplateRef<any>;

    public buscador:string = '';
    public loading:boolean = false;

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

    public updateTotal(detalle:any){
        detalle.total  = (parseFloat((detalle.cantidad ?? 0)) * parseFloat((detalle.costo ?? 0)) - parseFloat((detalle.descuento ?? 0))).toFixed(2);
        this.update.emit(this.compra);
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
            this.detalle = Object.assign({}, producto);
            this.detalle.id = null;
            
            // Verifica si el producto ya fue ingresado
            let detalleExistente = this.compra.detalles.find((x:any) => x.id_producto == this.detalle.id_producto);
            
            if(detalleExistente) {
                this.detalle = detalleExistente;
                this.detalle.cantidad += producto.cantidad;
            }
            
            // Agregar el producto directamente, el modal se abrirá cuando el usuario haga clic en el botón
            this.agregarDetalleFinal();
        }

    agregarDetalleFinal() {
        this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
        this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.costo) - parseFloat(this.detalle.descuento)).toFixed(2);
        
        // Verificar si el producto ya existe en los detalles
        let detalleExistente = this.compra.detalles.find((x:any) => x.id_producto == this.detalle.id_producto);
        if(!detalleExistente) {
            this.compra.detalles.push(this.detalle);
        }

        this.update.emit(this.compra);
        this.detalle = {};
        if (this.modalRef) { 
            this.modalRef.hide() 
        }
    }

    // Método para abrir modal de selección de lote
    public abrirModalLote(template: TemplateRef<any>, detalle: any) {
        this.detalle = detalle;
        this.crearNuevoLote = false;
        this.loteSeleccionado = null;
        this.nuevoLote = {
            numero_lote: '',
            fecha_vencimiento: null,
            fecha_fabricacion: null,
            observaciones: ''
        };
        // Cargar lotes antes de abrir el modal
        this.cargarLotesDisponibles();
        // Abrir el modal después de un pequeño delay para asegurar que los datos se carguen
        setTimeout(() => {
            this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
        }, 100);
    }

    public lotes: any[] = [];
    public loteSeleccionado: any = null;
    public nuevoLote: any = {
        numero_lote: '',
        fecha_vencimiento: null,
        fecha_fabricacion: null,
        observaciones: ''
    };
    public crearNuevoLote: boolean = false;

    cargarLotesDisponibles() {
        if (!this.detalle.id_producto || !this.compra.id_bodega) {
            this.lotes = [];
            return;
        }
        
        this.loading = true;
        // Cargar todos los lotes del producto en la bodega usando el endpoint específico
        // Este endpoint devuelve un array directo sin paginación
        this.apiService.getAll(`lotes/producto/${this.detalle.id_producto}`, {
            id_bodega: this.compra.id_bodega
        }).subscribe(lotes => {
            // El endpoint getByProducto devuelve un array directo
            this.lotes = Array.isArray(lotes) ? lotes : [];
            this.loading = false;
        }, error => {
            console.error('Error al cargar lotes:', error);
            this.alertService.error('Error al cargar los lotes del producto');
            this.loading = false;
            this.lotes = [];
        });
    }

    seleccionarLote(lote: any) {
        this.loteSeleccionado = lote;
        this.crearNuevoLote = false;
        this.detalle.lote_id = lote.id;
        this.detalle.lote = lote; // Guardar información del lote para mostrar
        this.modalRef.hide();
        
        // Actualizar el detalle en la compra
        this.update.emit(this.compra);
    }

    cambiarModoLote(crear: boolean) {
        this.crearNuevoLote = crear;
        if (crear) {
            this.loteSeleccionado = null;
            // No limpiar lote_id si ya está seleccionado, solo cuando se cambia a crear
        }
    }

    crearLote() {
        if (!this.detalle.id_producto || !this.compra.id_bodega) {
            this.alertService.error('Faltan datos para crear el lote');
            return;
        }

        if (!this.nuevoLote.numero_lote || this.nuevoLote.numero_lote.trim() === '') {
            this.alertService.error('El número de lote es requerido');
            return;
        }

        this.loading = true;
        const loteData = {
            id_producto: this.detalle.id_producto,
            id_bodega: this.compra.id_bodega,
            numero_lote: this.nuevoLote.numero_lote.trim(),
            fecha_vencimiento: this.nuevoLote.fecha_vencimiento,
            fecha_fabricacion: this.nuevoLote.fecha_fabricacion,
            stock: 0, // El stock se actualizará cuando se guarde la compra
            observaciones: this.nuevoLote.observaciones
        };

        this.apiService.store('lotes', loteData).subscribe(lote => {
            this.detalle.lote_id = lote.id;
            this.detalle.lote = lote; // Guardar información del lote para mostrar
            this.alertService.success('Lote creado', 'El lote fue creado exitosamente.');
            this.nuevoLote = {
                numero_lote: '',
                fecha_vencimiento: null,
                fecha_fabricacion: null,
                observaciones: ''
            };
            this.crearNuevoLote = false;
            this.loading = false;
            this.modalRef.hide();
            
            // Actualizar el detalle en la compra
            this.update.emit(this.compra);
            
            // Recargar los lotes para actualizar la lista
            this.cargarLotesDisponibles();
        }, error => {
            this.alertService.error(error);
            this.loading = false;
        });
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
                // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
              }
            });

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }

    public isLoteVencido(fechaVencimiento: any): boolean {
        if (!fechaVencimiento) return false;
        const fecha = new Date(fechaVencimiento);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        fecha.setHours(0, 0, 0, 0);
        return fecha < hoy;
    }

    public isLotesActivo(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) {
            return false;
        }
        
        const customConfig = typeof empresa.custom_empresa === 'string' 
            ? JSON.parse(empresa.custom_empresa) 
            : empresa.custom_empresa;
        
        return customConfig?.configuraciones?.lotes_activo === true;
    }

    public cerrarModalLote() {
        // Cerrar el modal sin hacer cambios
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }

}
