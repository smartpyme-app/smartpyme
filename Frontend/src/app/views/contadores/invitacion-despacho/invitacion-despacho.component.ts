import { Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import {
  ContadorInvitacionPublicaVista,
  ContadoresPortalService,
} from '@services/contadores-portal.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-invitacion-despacho',
  standalone: true,
  imports: [CommonModule, RouterModule, NotificacionesContainerComponent],
  templateUrl: './invitacion-despacho.component.html',
  styleUrls: ['./invitacion-despacho.component.css'],
})
export class InvitacionDespachoComponent implements OnInit {
  token = '';
  loading = true;
  procesando = false;
  vista: ContadorInvitacionPublicaVista | null = null;
  error = '';
  anio = new Date().getFullYear();
  completado: 'aceptada' | 'rechazada' | null = null;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private contadoresPortal: ContadoresPortalService,
    private api: ApiService,
    private alertService: AlertService,
  ) {}

  ngOnInit(): void {
    this.token = (this.route.snapshot.queryParamMap.get('token') ?? '').trim();
    if (!this.token) {
      this.loading = false;
      this.error = 'Enlace de invitación inválido.';
      return;
    }
    this.cargarVista();
  }

  get puedeResponder(): boolean {
    return !!this.vista?.vigente && this.vista.estado === 'pendiente';
  }

  get sesionActiva(): boolean {
    return !!this.api.auth_user()?.id;
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

  formatoExpira(iso?: string): string {
    if (!iso) {
      return '';
    }
    try {
      return new Intl.DateTimeFormat('es-SV', {
        dateStyle: 'long',
        timeStyle: 'short',
      }).format(new Date(iso));
    } catch {
      return iso;
    }
  }

  irLogin(): void {
    this.router.navigate(['/login'], {
      queryParams: { returnUrl: `/invitacion-despacho?token=${encodeURIComponent(this.token)}` },
    });
  }

  irCuentaDespacho(): void {
    this.router.navigate(['/mi-cuenta'], { queryParams: { tab: 'despacho-contable' } });
  }

  aceptar(): void {
    if (!this.token || !this.puedeResponder || this.procesando) {
      return;
    }
    this.procesando = true;
    this.contadoresPortal
      .aceptarInvitacionPublica(this.token)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.procesando = false;
          this.completado = 'aceptada';
          this.alertService.success('Invitación aceptada', 'El despacho ya puede gestionar tu empresa.');
        },
        error: (err) => {
          this.procesando = false;
          this.alertService.error(err);
        },
      });
  }

  rechazar(): void {
    if (!this.token || !this.puedeResponder || this.procesando) {
      return;
    }
    this.procesando = true;
    this.contadoresPortal
      .rechazarInvitacionPublica(this.token)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.procesando = false;
          this.completado = 'rechazada';
          this.alertService.success('Listo', 'Invitación rechazada.');
        },
        error: (err) => {
          this.procesando = false;
          this.alertService.error(err);
        },
      });
  }

  private cargarVista(): void {
    this.loading = true;
    this.error = '';
    this.contadoresPortal
      .vistaInvitacionPublica(this.token)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (v) => {
          this.vista = v;
          this.loading = false;
        },
        error: (err) => {
          this.loading = false;
          const raw = err?.error?.error;
          this.error = Array.isArray(raw) ? raw.join(', ') : (raw ?? 'No se pudo cargar la invitación.');
        },
      });
  }
}
