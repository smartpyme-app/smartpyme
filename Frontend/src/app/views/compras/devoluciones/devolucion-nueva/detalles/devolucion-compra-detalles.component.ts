import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-devolucion-compra-detalles',
  templateUrl: './devolucion-compra-detalles.component.html'
})
export class DevolucionCompraDetallesComponent implements OnInit {

    @Input() devolucion: any = {};
    public detalle:any = {};
    public supervisor:any = {};
    public todosSeleccionados:boolean = false;

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('mlote')
    public mloteTemplate!: TemplateRef<any>;

    public buscador:string = '';
    public loading:boolean = false;
    
    public lotes: any[] = [];
    public loteSeleccionado: any = null;
    public nuevoLote: any = {
        numero_lote: '',
        fecha_vencimiento: null,
        fecha_fabricacion: null,
        observaciones: ''
    };
    public crearNuevoLote: boolean = false;

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
        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo) - parseFloat(detalle.descuento)).toFixed(2);
        detalle.total_costo  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(2);
        this.update.emit(this.devolucion);
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
                
                    indexAEliminar = this.devolucion.detalles.findIndex((item:any) => item.id_producto === detalle.id_producto);
                    if (indexAEliminar !== -1) {
                        this.devolucion.detalles.splice(indexAEliminar, 1);
                        this.update.emit(this.devolucion);
                    }
              } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
              }
            });

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }

    seleccionarTodos(event: any) {
        this.todosSeleccionados = event.target.checked;
        this.devolucion.detalles.forEach((detalle: any) => {
            detalle.seleccionado = this.todosSeleccionados;
        });
    }
    actualizarSeleccion() {
        this.todosSeleccionados = this.devolucion.detalles.every(
            (detalle: any) => detalle.seleccionado
        );
    }

    haySeleccionados(): boolean {
        return this.devolucion.detalles.some(
            (detalle: any) => detalle.seleccionado
        );
    }

    eliminarSeleccionados(event?: any) {

        if (event) {
            event.preventDefault(); // Prevenir cualquier acción por defecto
        }
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Se eliminarán todos los productos seleccionados',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.devolucion.detalles = this.devolucion.detalles.filter(
                    (detalle: any) => !detalle.seleccionado
                );
                this.todosSeleccionados = false;
                this.update.emit(this.devolucion);
                this.sumTotal.emit();
            }
        });
    }

    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

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
        this.cargarLotesDisponibles();
        setTimeout(() => {
            this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
        }, 100);
    }

    cargarLotesDisponibles() {
        if (!this.detalle.id_producto || !this.devolucion.id_bodega) {
            this.lotes = [];
            return;
        }
        
        this.loading = true;
        this.apiService.getAll(`lotes/producto/${this.detalle.id_producto}`, {
            id_bodega: this.devolucion.id_bodega
        }).subscribe(lotes => {
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
        this.detalle.lote = lote;
        this.modalRef.hide();
        this.update.emit(this.devolucion);
    }

    cambiarModoLote(crear: boolean) {
        this.crearNuevoLote = crear;
        if (crear) {
            this.loteSeleccionado = null;
        }
    }

    crearLote() {
        if (!this.detalle.id_producto || !this.devolucion.id_bodega) {
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
            id_bodega: this.devolucion.id_bodega,
            numero_lote: this.nuevoLote.numero_lote.trim(),
            fecha_vencimiento: this.nuevoLote.fecha_vencimiento,
            fecha_fabricacion: this.nuevoLote.fecha_fabricacion,
            stock: 0,
            observaciones: this.nuevoLote.observaciones
        };

        this.apiService.store('lotes', loteData).subscribe(lote => {
            this.detalle.lote_id = lote.id;
            this.detalle.lote = lote;
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
            this.update.emit(this.devolucion);
            this.cargarLotesDisponibles();
        }, error => {
            this.alertService.error(error);
            this.loading = false;
        });
    }

    public cerrarModalLote() {
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }

    public isLoteVencido(fechaVencimiento: any): boolean {
        if (!fechaVencimiento) return false;
        const fecha = new Date(fechaVencimiento);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        fecha.setHours(0, 0, 0, 0);
        return fecha < hoy;
    }

}
