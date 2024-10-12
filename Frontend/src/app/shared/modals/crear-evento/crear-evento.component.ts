import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import { CrearProductoComponent } from '../crear-producto/crear-producto.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-crear-evento',
  templateUrl: './crear-evento.component.html'
})
export class CrearEventoComponent implements OnInit {

  @Input() evento: any = {};
  public productos: any = [];
  public clientes: any = [];
  public usuarios: any = [];
  public detalle: any = {};
  public productoSeleccionado: any = undefined;
  @Output() update = new EventEmitter();
  public loading = false;
  public saving: boolean = false;

  modalRef?: BsModalRef;
  @ViewChild("createProductModal") createProductModal!: any;
  @ViewChild("eventosEnConflictoModal") eventosEnConflictoModal!: any;
  @ViewChild("conflictedEventModal") conflictedEventModal!: any;
  conflictedEvent: any;
  modalCreateProductRef?: BsModalRef;
  conflictEvents: any = [];
  eventosConflictoModalRef?: BsModalRef;
  constructor(
    private apiService: ApiService, private alertService: AlertService,
    private modalService: BsModalService
  ) { }

  ngOnInit() {
    console.log(this.evento);

    this.apiService.getAll('usuarios/list').subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('productos/list').subscribe(productos => {
      this.productos = productos;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('clientes/list').subscribe(clientes => {
      this.clientes = clientes;
    }, error => { this.alertService.error(error); });
  }

  setTipo() {
    if (this.evento.tipo = 'Confirmado') {
      this.evento.tipo = 'Sin confirmar';
    } else {
      this.evento.tipo = 'Confirmado';
    }
    console.log(this.evento);
  }

  setTime() {
    let fecha = moment(this.evento.inicio);

    if (this.evento.duracion == '15 minutos') {
      this.evento.fin = fecha.add(15, 'minutes').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '30 minutos') {
      this.evento.fin = fecha.add(30, 'minutes').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '1 hora') {
      this.evento.fin = fecha.add(1, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '2 horas') {
      this.evento.fin = fecha.add(2, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '3 horas') {
      this.evento.fin = fecha.add(3, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '5 horas') {
      this.evento.fin = fecha.add(5, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '8 horas') {
      this.evento.fin = fecha.add(8, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '12 horas') {
      this.evento.fin = fecha.add(12, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
  }

  setFrecuenciaFin() {
    let fecha = moment(this.evento.inicio);

    if (!this.evento.veces) {
      this.evento.veces = 1;
    }

    if (this.evento.frecuencia == "DAILY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'day').format('YYYY-MM-DD');
    }
    if (this.evento.frecuencia == "WEEKLY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'week').format('YYYY-MM-DD');
    }
    if (this.evento.frecuencia == "MONTHLY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'month').format('YYYY-MM-DD');
    }
    if (this.evento.frecuencia == "YEARLY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'year').format('YYYY-MM-DD');
    }
  }

  // Cliente
  public setCliente(cliente: any) {
    if (!this.evento.id_cliente) {
      this.clientes.push(cliente);
    }
    this.evento.id_cliente = cliente.id;
  }

  public onSubmit() {
    this.saving = true;
    this.apiService.store('evento', this.evento).subscribe(evento => {
      if (!this.evento.id) {
        this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
      } else {
        this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
      }
      this.update.emit();
      this.saving = false;
      this.modalRef?.hide();
    }, error => {
      if (error.error.errorType == "event_conflict") {
        this.conflictEvents = error.error.conflicts;
      }
      else {
        this.alertService.error(error); this.saving = false;
      }
    });
  }

  ObSubmitConflicted() {
    this.onSubmit();
    this.eventosConflictoModalRef?.hide();

  }
  public agregarDetalle() {

    if (this.productoSeleccionado?.id) {
      this.detalle.nombre_producto = this.productoSeleccionado.nombre;
      this.detalle.id_producto = this.productoSeleccionado.id;
      this.detalle.cantidad = 1;
      this.detalle.id_evento = this.evento.id;
      this.detalle.costo = this.productoSeleccionado.costo;
      this.detalle.precio = this.productoSeleccionado.precio;

      let detalle = Object.assign({}, this.detalle);
      if (!this.evento.productos) this.evento.productos = [];

      this.evento.productos.unshift(detalle);
      this.detalle = {};
    }
    this.productoSeleccionado = null;

  }

  public eliminarDetalle(index: number) {
    this.evento.productos.splice(index, 1);
  }
  crearProducto() {
    this.modalCreateProductRef = this.modalService.show(this.createProductModal, { backdrop: 'static' });
  }
  onProductoCreated(producto: any) {
    this.productos.unshift(producto);
    this.productoSeleccionado = producto;
    this.agregarDetalle();
  }
  public openModal(template: TemplateRef<any>, evento: any) {
    this.conflictedEvent = evento;

    this.alertService.modal = true;
    this.eventosConflictoModalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
  }

  public delete(evento: any) {

    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.delete('evento/', evento.id).subscribe(data => {
          this.onSubmit();
        }, error => { this.alertService.error(error); }); 4
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });

  }
  public setEstado(evento: any, estado: any) {
    this.conflictedEvent = evento;
    this.conflictedEvent.tipo = estado;

    this.apiService.store('evento', this.conflictedEvent).subscribe(evento => {
      this.alertService.success('Cita actualizada', 'La cita fue actualida exitosamente.');
      this.ObSubmitConflicted();
      this.conflictedEvent = null;
    }, error => {
      this.alertService.error(error); this.saving = false;
    });
  }




}
