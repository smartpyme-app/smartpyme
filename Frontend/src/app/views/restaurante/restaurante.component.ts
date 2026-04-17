import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { RestauranteService, Mesa, Reserva } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

@Component({
  standalone: false,
  selector: 'app-restaurante',
  templateUrl: './restaurante.component.html',
  styleUrls: ['./restaurante.component.css']
})
export class RestauranteComponent implements OnInit {
  mesas: Mesa[] = [];
  loading = false;
  modalRef?: BsModalRef;
  modalAbrirRef?: BsModalRef;
  modalReservarRef?: BsModalRef;
  modalMesaReservadaRef?: BsModalRef;
  modalAccionLibreRef?: BsModalRef;

  mesaSeleccionada: Mesa | null = null;
  reservaSeleccionada: Reserva | null = null;
  mesaForm: Partial<Mesa> = {};
  abrirForm: { num_comensales: number; observaciones: string } = { num_comensales: 2, observaciones: '' };
  reservaForm: Partial<Reserva> = {};
  guardando = false;

  readonly coloresEstado: Record<string, string> = {
    libre: '#22c55e',
    ocupada: '#eab308',
    pendiente_pago: '#ef4444',
    reservada: '#3b82f6'
  };

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.cargarMesas();
  }

  cargarMesas(): void {
    this.loading = true;
    this.restauranteService.getMesas().subscribe({
      next: (mesas) => {
        this.mesas = mesas;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  colorMesa(mesa: Mesa): string {
    return this.coloresEstado[mesa.estado] || '#94a3b8';
  }

  onClickMesa(mesa: Mesa): void {
    if (mesa.estado === 'libre') {
      this.mesaSeleccionada = mesa;
      this.modalAccionLibreRef = this.modalService.show(this.templateAccionMesaLibre!, {
        class: 'modal-sm',
        backdrop: 'static'
      });
    } else if (mesa.estado === 'reservada') {
      this.mesaSeleccionada = mesa;
      this.reservaSeleccionada = mesa.reservas_activas?.[0] ?? null;
      this.modalMesaReservadaRef = this.modalService.show(this.templateMesaReservada!, {
        class: 'modal-md',
        backdrop: 'static'
      });
    } else if (mesa.estado === 'ocupada' || mesa.estado === 'pendiente_pago') {
      const sesionId = (mesa as any).sesion_activa?.id;
      if (sesionId) {
        this.router.navigate(['/restaurante/cuenta', sesionId]);
      }
    }
  }

  abrirMesaDesdeAccion(): void {
    this.modalAccionLibreRef?.hide();
    if (!this.mesaSeleccionada) return;
    this.abrirForm = { num_comensales: 2, observaciones: '' };
    this.modalAbrirRef = this.modalService.show(this.templateAbrirMesa!, {
      class: 'modal-md',
      backdrop: 'static'
    });
  }

  abrirReservarDesdeAccion(): void {
    this.modalAccionLibreRef?.hide();
    if (!this.mesaSeleccionada) return;
    const hoy = new Date().toISOString().slice(0, 10);
    this.reservaForm = {
      mesa_id: this.mesaSeleccionada.id,
      fecha_reserva: hoy,
      hora_reserva: '12:00',
      cliente_nombre: '',
      cliente_telefono: '',
      observaciones: ''
    };
    this.modalReservarRef = this.modalService.show(this.templateReservar!, {
      class: 'modal-md',
      backdrop: 'static'
    });
  }

  crearReserva(event?: Event): void {
    event?.preventDefault();
    if (!this.reservaForm.mesa_id || !this.reservaForm.fecha_reserva || !this.reservaForm.hora_reserva) {
      this.alertService.warning('Datos requeridos', 'Complete fecha y hora de la reserva.');
      return;
    }
    this.guardando = true;
    this.restauranteService.crearReserva(this.reservaForm).subscribe({
      next: () => {
        this.modalReservarRef?.hide();
        this.alertService.success('Reserva creada', 'La mesa quedó reservada.');
        this.cargarMesas();
        this.guardando = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
      }
    });
  }

  convertirReservaEnSesion(): void {
    if (!this.reservaSeleccionada) return;
    this.guardando = true;
    this.restauranteService.convertirReservaEnSesion(this.reservaSeleccionada.id).subscribe({
      next: (sesion) => {
        this.modalMesaReservadaRef?.hide();
        this.alertService.success('Cliente llegó', 'La mesa está abierta.');
        this.cargarMesas();
        this.guardando = false;
        this.router.navigate(['/restaurante/cuenta', sesion.id]);
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
      }
    });
  }

  cancelarReserva(): void {
    if (!this.reservaSeleccionada) return;
    this.guardando = true;
    this.restauranteService.cancelarReserva(this.reservaSeleccionada.id).subscribe({
      next: () => {
        this.modalMesaReservadaRef?.hide();
        this.alertService.success('Reserva cancelada', 'La mesa quedó libre.');
        this.cargarMesas();
        this.guardando = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
      }
    });
  }

  cerrarModalAccionLibre(): void {
    this.modalAccionLibreRef?.hide();
    this.mesaSeleccionada = null;
  }

  cerrarModalReservar(): void {
    this.modalReservarRef?.hide();
    this.mesaSeleccionada = null;
  }

  cerrarModalMesaReservada(): void {
    this.modalMesaReservadaRef?.hide();
    this.mesaSeleccionada = null;
    this.reservaSeleccionada = null;
  }

  abrirMesa(event?: Event): void {
    event?.preventDefault();
    if (!this.mesaSeleccionada) return;
    this.guardando = true;
    this.restauranteService.abrirSesion(this.mesaSeleccionada.id, this.abrirForm).subscribe({
      next: (sesion) => {
        this.modalAbrirRef?.hide();
        this.alertService.success('Mesa abierta', 'La sesión se ha iniciado correctamente.');
        this.cargarMesas();
        this.guardando = false;
        this.router.navigate(['/restaurante/cuenta', sesion.id]);
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
      }
    });
  }

  openModalMesa(template: TemplateRef<any>, mesa?: Mesa): void {
    this.mesaSeleccionada = mesa || null;
    this.mesaForm = mesa ? { ...mesa } : { numero: '', capacidad: 4, zona: '', orden: 0, activo: true };
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
  }

  guardarMesa(event?: Event): void {
    event?.preventDefault();
    if (!this.mesaForm.numero?.trim()) {
      this.alertService.warning('Número requerido', 'Ingrese el número de mesa.');
      return;
    }
    this.guardando = true;
    const obs = this.mesaSeleccionada
      ? this.restauranteService.actualizarMesa(this.mesaSeleccionada.id, this.mesaForm)
      : this.restauranteService.crearMesa(this.mesaForm);
    obs.subscribe({
      next: () => {
        this.modalRef?.hide();
        this.alertService.modal = false;
        this.alertService.success('Mesa guardada', 'Los cambios se han guardado correctamente.');
        this.cargarMesas();
        this.guardando = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
      }
    });
  }

  cerrarModal(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  cerrarModalAbrir(): void {
    this.modalAbrirRef?.hide();
    this.mesaSeleccionada = null;
  }

  @ViewChild('templateAbrirMesa') templateAbrirMesa!: TemplateRef<any>;
  @ViewChild('templateAccionMesaLibre') templateAccionMesaLibre!: TemplateRef<any>;
  @ViewChild('templateReservar') templateReservar!: TemplateRef<any>;
  @ViewChild('templateMesaReservada') templateMesaReservada!: TemplateRef<any>;
}
