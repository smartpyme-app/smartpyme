import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  inject,
  OnInit,
  TemplateRef,
  ViewChild,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { RestauranteService, Mesa, Reserva, ZonaRestaurante } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { RestauranteRealtimeService } from '@services/restaurante-realtime.service';
import { buildMesasPorZona, MesaZonaGrupo } from './mesas-por-zona';
import { MENSAJE_CONFIRMAR_CERRAR_MESA, MENSAJE_CONFIRMAR_CERRAR_MESA_FORZADO, puedeCerrarMesaRestaurante } from './restaurante-roles.util';
import {
  mensajeErrorCierreMesa,
  requiereCodigoSupervisorEnError,
  validarCodigoSupervisor,
} from './restaurante-cerrar-mesa.util';

@Component({
  standalone: false,
  selector: 'app-restaurante',
  templateUrl: './restaurante.component.html',
  styleUrls: ['./restaurante.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RestauranteComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  mesas: Mesa[] = [];
  /** Grupos preparados (no getter) para evitar O(n) en cada CD. */
  mesasPorZona: MesaZonaGrupo[] = [];
  zonas: ZonaRestaurante[] = [];
  zonasCargadas = false;
  /** Evita GET /zonas duplicado si el modal abre mientras el primer load sigue en vuelo. */
  private zonasLoadStarted = false;
  filtroZona = '';
  loading = false;
  modalRef?: BsModalRef;
  modalAbrirRef?: BsModalRef;
  modalReservarRef?: BsModalRef;
  modalMesaReservadaRef?: BsModalRef;
  modalAccionLibreRef?: BsModalRef;
  modalAccionOcupadaRef?: BsModalRef;

  mesaSeleccionada: Mesa | null = null;
  reservaSeleccionada: Reserva | null = null;
  mesaForm: Partial<Mesa> = {};
  abrirForm: { num_comensales: number; observaciones: string } = { num_comensales: 2, observaciones: '' };
  reservaForm: Partial<Reserva> = {};
  guardando = false;
  cerrandoMesa = false;
  mostrarModalSupervisorCierre = false;
  supervisorCodigoCierre = '';
  validandoSupervisorCierre = false;

  readonly coloresEstado: Record<string, string> = {
    libre: '#22c55e',
    ocupada: '#eab308',
    pendiente_pago: '#ef4444',
    reservada: '#3b82f6'
  };

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private apiService: ApiService,
    private modalService: BsModalService,
    private router: Router,
    private realtime: RestauranteRealtimeService,
  ) {}

  ngOnInit(): void {
    this.cargarMesas();
    this.cargarZonas();
    this.realtime.watch('mapa', () => this.cargarMesas());
    this.realtime.onRecover(() => this.cargarMesas());
  }

  cargarZonas(): void {
    if (this.zonasLoadStarted) {
      return;
    }
    this.zonasLoadStarted = true;
    this.restauranteService
      .getZonas({ activo: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (z) => {
          this.zonas = z || [];
          this.zonasCargadas = true;
          this.cdr.markForCheck();
        },
        error: () => {
          this.zonas = [];
          this.zonasCargadas = true;
          this.cdr.markForCheck();
        }
      });
  }

  private rebuildMesasPorZona(): void {
    this.mesasPorZona = buildMesasPorZona(this.mesas, this.filtroZona);
  }

  onFiltroZonaChange(): void {
    this.rebuildMesasPorZona();
    this.cdr.markForCheck();
  }

  limpiarFiltroZona(): void {
    this.filtroZona = '';
    this.rebuildMesasPorZona();
    this.cdr.markForCheck();
  }

  cargarMesas(): void {
    this.loading = true;
    this.cdr.markForCheck();
    this.restauranteService
      .getMesas()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (mesas) => {
          this.mesas = mesas;
          this.rebuildMesasPorZona();
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.alertService.error(err);
          this.loading = false;
          this.cdr.markForCheck();
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
      this.mesaSeleccionada = mesa;
      this.modalAccionOcupadaRef = this.modalService.show(this.templateAccionMesaOcupada!, {
        class: 'modal-sm',
        backdrop: 'static',
      });
    }
  }

  puedeCerrarMesa(): boolean {
    return puedeCerrarMesaRestaurante(this.apiService.auth_user()?.tipo);
  }

  sesionActivaId(mesa: Mesa | null): number | null {
    const id = (mesa as any)?.sesion_activa?.id;
    return id != null ? Number(id) : null;
  }

  irACuentaMesa(): void {
    const sesionId = this.sesionActivaId(this.mesaSeleccionada);
    this.modalAccionOcupadaRef?.hide();
    this.mesaSeleccionada = null;
    if (sesionId) {
      this.router.navigate(['/restaurante/cuenta', sesionId]);
    }
  }

  cerrarMesaDesdeMapa(): void {
    const sesionId = this.sesionActivaId(this.mesaSeleccionada);
    if (!sesionId || !this.puedeCerrarMesa() || this.cerrandoMesa) {
      return;
    }
    const msg = `${MENSAJE_CONFIRMAR_CERRAR_MESA}\n\n${MENSAJE_CONFIRMAR_CERRAR_MESA_FORZADO}`;
    if (!confirm(msg)) {
      return;
    }
    this.ejecutarCierreMesaDesdeMapa(sesionId);
  }

  cerrarModalSupervisorCierre(): void {
    if (this.validandoSupervisorCierre || this.cerrandoMesa) {
      return;
    }
    this.mostrarModalSupervisorCierre = false;
    this.supervisorCodigoCierre = '';
    this.cdr.markForCheck();
  }

  confirmarSupervisorCierreMesa(): void {
    const sesionId = this.sesionActivaId(this.mesaSeleccionada);
    if (!sesionId || this.validandoSupervisorCierre || this.cerrandoMesa) {
      return;
    }
    this.validandoSupervisorCierre = true;
    this.cdr.markForCheck();
    validarCodigoSupervisor(this.apiService, this.supervisorCodigoCierre)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.validandoSupervisorCierre = false;
          this.mostrarModalSupervisorCierre = false;
          const codigo = this.supervisorCodigoCierre.trim();
          this.supervisorCodigoCierre = '';
          this.ejecutarCierreMesaDesdeMapa(sesionId, codigo);
        },
        error: (err) => {
          this.alertService.error(mensajeErrorCierreMesa(err));
          this.validandoSupervisorCierre = false;
          this.cdr.markForCheck();
        },
      });
  }

  private ejecutarCierreMesaDesdeMapa(sesionId: number, codigoSupervisor?: string): void {
    if (this.cerrandoMesa) {
      return;
    }
    this.cerrandoMesa = true;
    this.cdr.markForCheck();
    const body = codigoSupervisor ? { codigo_supervisor: codigoSupervisor } : {};
    this.restauranteService
      .cerrarSesion(sesionId, body)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.cerrandoMesa = false;
          this.modalAccionOcupadaRef?.hide();
          this.mesaSeleccionada = null;
          this.alertService.success('Mesa cerrada', 'La mesa quedó libre en el mapa.');
          this.cargarMesas();
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.cerrandoMesa = false;
          if (requiereCodigoSupervisorEnError(err)) {
            this.supervisorCodigoCierre = '';
            this.mostrarModalSupervisorCierre = true;
            this.cdr.markForCheck();
            return;
          }
          this.alertService.error(err);
          this.cdr.markForCheck();
        },
      });
  }

  cerrarModalAccionOcupada(): void {
    this.modalAccionOcupadaRef?.hide();
    this.mesaSeleccionada = null;
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

  editarMesaDesdeAccion(template: TemplateRef<any>): void {
    const mesa = this.mesaSeleccionada;
    this.modalAccionLibreRef?.hide();
    if (!mesa) return;
    this.openModalMesa(template, mesa);
  }

  crearReserva(event?: Event): void {
    event?.preventDefault();
    if (!this.reservaForm.mesa_id || !this.reservaForm.fecha_reserva || !this.reservaForm.hora_reserva) {
      this.alertService.warning('Datos requeridos', 'Complete fecha y hora de la reserva.');
      return;
    }
    this.guardando = true;
    this.cdr.markForCheck();
    this.restauranteService
      .crearReserva(this.reservaForm)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.modalReservarRef?.hide();
          this.alertService.success('Reserva creada', 'La mesa quedó reservada.');
          this.cargarMesas();
          this.guardando = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.alertService.error(err);
          this.guardando = false;
          this.cdr.markForCheck();
        }
      });
  }

  convertirReservaEnSesion(): void {
    if (!this.reservaSeleccionada) return;
    this.guardando = true;
    this.cdr.markForCheck();
    this.restauranteService
      .convertirReservaEnSesion(this.reservaSeleccionada.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (sesion) => {
          this.modalMesaReservadaRef?.hide();
          this.alertService.success('Cliente llegó', 'La mesa está abierta.');
          this.cargarMesas();
          this.guardando = false;
          this.cdr.markForCheck();
          this.router.navigate(['/restaurante/cuenta', sesion.id]);
        },
        error: (err) => {
          this.alertService.error(err);
          this.guardando = false;
          this.cdr.markForCheck();
        }
      });
  }

  cancelarReserva(): void {
    if (!this.reservaSeleccionada) return;
    this.guardando = true;
    this.cdr.markForCheck();
    this.restauranteService
      .cancelarReserva(this.reservaSeleccionada.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.modalMesaReservadaRef?.hide();
          this.alertService.success('Reserva cancelada', 'La mesa quedó libre.');
          this.cargarMesas();
          this.guardando = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.alertService.error(err);
          this.guardando = false;
          this.cdr.markForCheck();
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
    this.cdr.markForCheck();
    this.restauranteService
      .abrirSesion(this.mesaSeleccionada.id, this.abrirForm)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (sesion) => {
          this.modalAbrirRef?.hide();
          this.alertService.success('Mesa abierta', 'La sesión se ha iniciado correctamente.');
          this.cargarMesas();
          this.guardando = false;
          this.cdr.markForCheck();
          this.router.navigate(['/restaurante/cuenta', sesion.id]);
        },
        error: (err) => {
          this.alertService.error(err);
          this.guardando = false;
          this.cdr.markForCheck();
        }
      });
  }

  openModalMesa(template: TemplateRef<any>, mesa?: Mesa): void {
    this.modalAccionLibreRef?.hide();
    this.modalAccionOcupadaRef?.hide();
    this.modalAbrirRef?.hide();
    this.modalReservarRef?.hide();
    this.modalMesaReservadaRef?.hide();
    this.modalRef?.hide();

    this.mesaSeleccionada = mesa || null;
    this.mesaForm = mesa
      ? {
          numero: mesa.numero,
          capacidad: mesa.capacidad,
          zona_id: mesa.zona_id ?? mesa.zona_restaurante?.id ?? null,
          orden: mesa.orden ?? 0,
          activo: mesa.activo,
        }
      : { numero: '', capacidad: 4, zona_id: null, orden: 0, activo: true };
    // Evitar HTTP duplicado: zonas ya cargadas en ngOnInit.
    if (!this.zonasCargadas) {
      this.cargarZonas();
    }
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
      ignoreBackdropClick: true,
    });
    this.cdr.markForCheck();
  }

  guardarMesa(event?: Event): void {
    event?.preventDefault();
    if (!this.mesaForm.numero?.trim()) {
      this.alertService.warning('Número requerido', 'Ingrese el número de mesa.');
      return;
    }
    this.guardando = true;
    this.cdr.markForCheck();
    const obs = this.mesaSeleccionada
      ? this.restauranteService.actualizarMesa(this.mesaSeleccionada.id, this.mesaForm)
      : this.restauranteService.crearMesa(this.mesaForm);
    obs.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.modalRef?.hide();
        this.alertService.modal = false;
        this.alertService.success('Mesa guardada', 'Los cambios se han guardado correctamente.');
        this.cargarMesas();
        this.guardando = false;
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
        this.cdr.markForCheck();
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
  @ViewChild('templateAccionMesaOcupada') templateAccionMesaOcupada!: TemplateRef<any>;
  @ViewChild('templateReservar') templateReservar!: TemplateRef<any>;
  @ViewChild('templateMesaReservada') templateMesaReservada!: TemplateRef<any>;
}
