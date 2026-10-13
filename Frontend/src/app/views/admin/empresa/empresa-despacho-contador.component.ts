import { Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import Swal from 'sweetalert2';

interface DespachoResumen {
  id: number;
  nombre: string;
  logo?: string | null;
}

interface DespachoContadorEstado {
  invitacion_pendiente: {
    id: number;
    email_admin: string;
    expira_en: string;
    despacho: DespachoResumen | null;
  } | null;
  acceso_activo: {
    id_empresa_despacho: number;
    despacho: DespachoResumen | null;
  } | null;
}

@Component({
  selector: 'app-empresa-despacho-contador',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './empresa-despacho-contador.component.html',
  styleUrls: ['./empresa-despacho-contador.component.css'],
})
export class EmpresaDespachoContadorComponent implements OnInit {
  loading = true;
  procesando = false;
  estado: DespachoContadorEstado | null = null;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public api: ApiService,
    private alertService: AlertService,
  ) {}

  ngOnInit(): void {
    this.recargar();
  }

  get invitacion() {
    return this.estado?.invitacion_pendiente ?? null;
  }

  get accesoActivo() {
    return this.estado?.acceso_activo ?? null;
  }

  logoUrl(logo: string | null | undefined): string | null {
    if (!logo?.trim()) {
      return null;
    }
    const path = logo.trim();
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    return `${this.api.baseUrl}/img/${path}`;
  }

  formatoExpira(iso: string): string {
    try {
      return new Intl.DateTimeFormat('es-SV', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
    } catch {
      return iso;
    }
  }

  aceptarInvitacion(): void {
    const id = this.invitacion?.id;
    if (!id || this.procesando) {
      return;
    }
    this.procesando = true;
    this.api
      .store(`empresa/despacho-contador/invitacion/${id}/aceptar`, {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.procesando = false;
          this.alertService.success('Despacho autorizado', 'Tu empresa ya está en la cartera del despacho.');
          this.recargar();
        },
        error: (err) => {
          this.procesando = false;
          this.alertService.error(err);
        },
      });
  }

  rechazarInvitacion(): void {
    const id = this.invitacion?.id;
    if (!id || this.procesando) {
      return;
    }
    this.procesando = true;
    this.api
      .store(`empresa/despacho-contador/invitacion/${id}/rechazar`, {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.procesando = false;
          this.alertService.success('Listo', 'Invitación rechazada.');
          this.recargar();
        },
        error: (err) => {
          this.procesando = false;
          this.alertService.error(err);
        },
      });
  }

  revocarDespacho(): void {
    if (this.procesando || !this.accesoActivo) {
      return;
    }
    const nombre = this.accesoActivo.despacho?.nombre ?? 'el despacho';
    Swal.fire({
      title: '¿Revocar acceso?',
      html: `<p class="mb-0">El despacho <strong>${nombre}</strong> dejará de gestionar tu empresa en SmartPyme.</p>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Revocar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc3545',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.procesando = true;
      this.api
        .store('empresa/despacho-contador/revocar', {})
        .pipe(this.untilDestroyed())
        .subscribe({
          next: () => {
            this.procesando = false;
            this.alertService.success('Listo', 'Acceso del despacho revocado.');
            this.recargar();
          },
          error: (err) => {
            this.procesando = false;
            this.alertService.error(err);
          },
        });
    });
  }

  recargar(): void {
    this.loading = true;
    this.api
      .get('empresa/despacho-contador')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data: DespachoContadorEstado) => {
          this.estado = data;
          this.loading = false;
        },
        error: (err) => {
          this.loading = false;
          this.alertService.error(err);
        },
      });
  }
}
