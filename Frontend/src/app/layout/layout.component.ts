import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SpeedDialComponent } from '../shared/speed-dial/speed-dial.component';
import { ChatDrawerComponent } from '../shared/chat/chat-drawer.component';
import { HeaderComponent } from './header/header.component';
import { FooterComponent } from './footer/footer.component';
import { SidebarComponent } from './sidebar/sidebar.component';
import { SidebarAdminComponent } from './sidebar/sidebar-admin/sidebar-admin.component';
import { NotificacionesContainerComponent } from '../shared/parts/notificaciones/notificaciones-container.component';
import { Router } from '@angular/router';
import { AppConstants } from '../constants/app.constants';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-layout',
    templateUrl: './layout.component.html',
    styleUrls: ['./layout.component.css'],
    standalone: true,
    imports: [
        CommonModule, 
        RouterModule, 
        SpeedDialComponent, 
        ChatDrawerComponent, 
        HeaderComponent, 
        FooterComponent,
        SidebarComponent,
        SidebarAdminComponent,
        NotificacionesContainerComponent
    ],
    
})
export class LayoutComponent implements OnInit {
  public usuario: any = {};
  public elem: any;
  public isfullscreen: boolean = false;
  public isVisible: boolean = false;
  public visibleAlertMessage: boolean = false;

  readonly ESTADOS_SUSCRIPCION = AppConstants.ESTADOS_SUSCRIPCION;
  readonly DIAS_PRORROGA_SUSCRIPCION = AppConstants.DIAS_PRORROGA_SUSCRIPCION;
  /** Primer día en que puede aplicarse suspensión de acceso (prórroga + 1). */
  readonly DIAS_UMBRAL_SUSPENSION_ACCESO = AppConstants.DIAS_PRORROGA_SUSCRIPCION + 1;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private router: Router
  ) {}

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.mostrarAlertaSuscripcion();

    this.getAlertSuscription();
  }

  RedirectSuscripcion() {
    this.router.navigate(['/suscripcion']);
  }

  isAdmin(): boolean {
    return (
      this.usuario.tipo === 'Administrador'
    );
  }

  /** Base para banners de suscripción pagada (no prueba). */
  private condicionBaseSuscripcionPagada(): boolean {
    return (
      this.usuario?.estado_suscripcion?.toLowerCase() !== 'en prueba' &&
      this.visibleAlertMessage &&
      this.usuario?.dias_faltantes !== null &&
      this.usuario?.dias_faltantes !== undefined &&
      this.usuario?.tiene_suscripcion
    );
  }

  /** Admin: recordatorios (día -3 y -1 en la tabla = dias_faltantes 3 y 1). */
  bannerAdminRecordatorioPrevio(): boolean {
    return (
      this.condicionBaseSuscripcionPagada() &&
      this.isAdmin() &&
      (this.usuario.dias_faltantes === 3 || this.usuario.dias_faltantes === 1)
    );
  }

  /** Admin: desde vencimiento (0) hasta último día de gracia (-3). */
  bannerAdminVencimientoOGracia(): boolean {
    const d = this.usuario.dias_faltantes;
    return (
      this.condicionBaseSuscripcionPagada() &&
      this.isAdmin() &&
      (d === 0 || (d < 0 && d >= -this.DIAS_PRORROGA_SUSCRIPCION))
    );
  }

  /** Usuario no admin: solo día de vencimiento (0) y gracia (días 1–3 de mora = -1..-3). */
  bannerUsuarioVencimientoOGracia(): boolean {
    const d = this.usuario.dias_faltantes;
    return (
      this.condicionBaseSuscripcionPagada() &&
      !this.isAdmin() &&
      (d === 0 || (d < 0 && d >= -this.DIAS_PRORROGA_SUSCRIPCION))
    );
  }

  /** Acceso de excepción (admin) vigente: permite usar la app aunque ya aplicaría suspensión por mora. */
  accesoTemporalVigente(): boolean {
    const h = this.usuario?.acceso_temporal_hasta;
    if (!h) {
      return false;
    }
    return new Date(h).getTime() > Date.now();
  }

  /** Cuenta con acceso suspendido por saldos pendientes con el sistema (p. ej. dias_faltantes <= -4 con prórroga 3). */
  bannerCuentaSuspendidaPorSaldosPendientes(): boolean {
    if (this.accesoTemporalVigente()) {
      return false;
    }
    return (
      this.condicionBaseSuscripcionPagada() &&
      this.usuario.dias_faltantes <= -this.DIAS_UMBRAL_SUSPENSION_ACCESO
    );
  }

  /** Aviso informativo: mora que ya suspendiría, pero sigue vigente un acceso temporal concedido. */
  bannerAccesoTemporalActivo(): boolean {
    return (
      this.condicionBaseSuscripcionPagada() &&
      this.accesoTemporalVigente() &&
      this.usuario.dias_faltantes <= -this.DIAS_UMBRAL_SUSPENSION_ACCESO
    );
  }

getMensajeSuscripcion(): { mensaje: string; tipo: string } {
  // Si el usuario no existe, devolver mensaje genérico
  if (!this.usuario) {
    return {
      mensaje: 'No se pudo determinar el estado de la suscripción. Por favor, contacta con soporte técnico.',
      tipo: 'error',
    };
  }

  // Normalizar el estado de suscripción para comparaciones consistentes
  const estadoSuscripcion = this.usuario.estado_suscripcion?.toLowerCase() || '';

  // Verificar primero si el usuario está en período de prueba sin importar otros valores
  if (estadoSuscripcion === 'en prueba') {
    const diasRestantesPrueba = this.usuario.dias_faltantes_prueba;

    if (diasRestantesPrueba === undefined || diasRestantesPrueba === null) {
      return {
        mensaje: 'No se pudo determinar el estado de la prueba. Por favor, contacta con soporte técnico.',
        tipo: 'error',
      };
    }

    if (diasRestantesPrueba <= 0) {
      if (this.isAdmin()) {
        return {
          mensaje: 'Tu prueba ha terminado. Por favor, renueva tu suscripción para continuar usando el servicio.',
          tipo: 'error',
        };
      } else {
        return {
          mensaje: 'El período de prueba ha terminado. Contacta al administrador para activar la suscripción.',
          tipo: 'error',
        };
      }
    }

    if (diasRestantesPrueba === 1) {
      if (this.isAdmin()) {
        return {
          mensaje: 'Tu prueba termina mañana. Activa tu suscripción para continuar usando el servicio.',
          tipo: 'warning',
        };
      } else {
        return {
          mensaje: 'El período de prueba termina mañana. Contacta al administrador para activar la suscripción.',
          tipo: 'warning',
        };
      }
    }

    if (this.isAdmin()) {
      return {
        mensaje: `Estás en período de prueba. Te quedan ${diasRestantesPrueba} días para probar el servicio.`,
        tipo: 'info',
      };
    } else {
      return {
        mensaje: `La empresa está en período de prueba. Quedan ${diasRestantesPrueba} días de prueba.`,
        tipo: 'info',
      };
    }
  }

  // Verificar si el usuario no tiene suscripción
  if (!this.usuario.hasOwnProperty('tiene_suscripcion') || !this.usuario.tiene_suscripcion) {
    if (this.isAdmin()) {
      return {
        mensaje: 'No cuentas con una suscripción activa. Haz clic aquí para activar tu suscripción y acceder a todas las funcionalidades.',
        tipo: 'error',
      };
    } else {
      return {
        mensaje: 'Esta empresa no cuenta con una suscripción activa. Por favor contacta al administrador para activar la suscripción.',
        tipo: 'error',
      };
    }
  }

  // Para suscripciones regulares, usar dias_faltantes
  const diasRestantes = this.usuario.dias_faltantes;

  if (diasRestantes === null || diasRestantes === undefined) {
    return {
      mensaje: 'No se pudo determinar el estado de la suscripción. Por favor, contacta con soporte técnico.',
      tipo: 'error',
    };
  }

  // Tabla de notificaciones: admin ve recordatorios en dias_faltantes 3 y 1; todos ven alertas desde 0 y en gracia (-1..-3).
  if (this.isAdmin() && (diasRestantes === 3 || diasRestantes === 1)) {
    return {
      mensaje:
        'Recordatorio: se acerca la fecha de renovación. Mantén al día tus saldos con el sistema para evitar interrupciones en el servicio.',
      tipo: 'warning',
    };
  }

  if (diasRestantes === 0) {
    if (this.isAdmin()) {
      return {
        mensaje:
          'Importante: hoy vence el plazo programado. Regulariza tus saldos pendientes con el sistema para continuar sin interrupciones.',
        tipo: 'error',
      };
    }
    return {
      mensaje:
        'Importante: hoy vence el plazo de la suscripción. El administrador puede regularizar los saldos pendientes con el sistema para evitar interrupciones.',
      tipo: 'error',
    };
  }

  if (diasRestantes === -1) {
    if (this.isAdmin()) {
      return {
        mensaje:
          'Tu suscripción requiere atención: hay saldos pendientes con el sistema. Regulariza tu situación para mantener el acceso.',
        tipo: 'error',
      };
    }
    return {
      mensaje:
        'La suscripción de la empresa requiere atención. Contacta al administrador para regularizar los saldos pendientes con el sistema.',
      tipo: 'error',
    };
  }

  if (diasRestantes === -2) {
    if (this.isAdmin()) {
      return {
        mensaje:
          'Importante: si persisten saldos pendientes con el sistema, mañana podría limitarse el acceso. Te invitamos a regularizar tu situación.',
        tipo: 'error',
      };
    }
    return {
      mensaje:
        'Importante: si persisten saldos pendientes con el sistema, mañana podría limitarse el acceso. Informa a tu administrador.',
      tipo: 'error',
    };
  }

  if (diasRestantes === -3) {
    if (this.isAdmin()) {
      return {
        mensaje:
          'Último día de gracia: regulariza hoy tus saldos pendientes con el sistema para mantener el acceso.',
        tipo: 'error',
      };
    }
    return {
      mensaje:
        'Último día de gracia antes de una posible suspensión del acceso. Contacta al administrador para regularizar los saldos pendientes con el sistema.',
      tipo: 'error',
    };
  }

  const diasVencidos = Math.abs(diasRestantes);
  if (diasVencidos > this.DIAS_PRORROGA_SUSCRIPCION) {
    if (this.isAdmin()) {
      return {
        mensaje:
          'Tu cuenta está suspendida por saldos pendientes con el sistema. Contacta a soporte para regularizar tu situación.',
        tipo: 'error',
      };
    }
    return {
      mensaje:
        'La cuenta de la empresa está suspendida por saldos pendientes con el sistema. Contacta al administrador.',
      tipo: 'error',
    };
  }

  if (diasRestantes > 7) {
    return {
      mensaje: this.isAdmin()
        ? `Tu suscripción vencerá en ${diasRestantes} días.`
        : `La suscripción de la empresa vencerá en ${diasRestantes} días.`,
      tipo: 'info',
    };
  }

  return {
    mensaje: this.isAdmin()
      ? 'Revisa el estado de tu suscripción en la sección de suscripción.'
      : 'Para información sobre la suscripción, contacta al administrador.',
    tipo: 'info',
  };
}

  mostrarAlertaSuscripcion() {
    const alerta = this.getMensajeSuscripcion();
    if (alerta.tipo === 'error') {
      this.alertService.error(alerta.mensaje);
    } else if (alerta.tipo === 'warning') {
      this.alertService.warning(alerta.mensaje, true);
    } else {
      this.alertService.info(alerta.mensaje, true);
    }
  }

  shouldShowRenovarButton(): boolean {
    if (this.usuario.estado_suscripcion.toLowerCase() === 'en prueba') {
      return this.usuario.dias_faltantes_prueba <= 1;
    }
    return !this.usuario.tiene_suscripcion || this.usuario.dias_faltantes <= 7;
  }

  shouldShowCancellationBanner(): boolean {
    return this.usuario &&
           this.usuario.suscripcion &&
           this.usuario.suscripcion.estado === this.ESTADOS_SUSCRIPCION.CANCELADO;
  }

  getCancellationMessage(): string {
    if (!this.shouldShowCancellationBanner()) return '';

    const fechaDesactivacion = new Date(this.usuario.suscripcion.fecha_proximo_pago);
    return `Tu suscripción ha sido cancelada. Podrás seguir utilizando el sistema hasta el ${fechaDesactivacion.toLocaleDateString()}.`;
  }

  dismissAlert() {
    this.visibleAlertMessage = false;
    
    this.apiService.getAll('empresa/isvisible-alert')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response: any) => {
          console.log('Alerta desactivada correctamente:', response);
          
          if (this.usuario && this.usuario.empresa) {
            this.usuario.empresa.alerta_suscripcion = false;
            this.getAlertSuscription();
          }
        },
        error: (error) => {
          console.error('Error al desactivar la alerta:', error);
        }
      });
  }

  getAlertSuscription() {
    this.apiService.getAll('empresa/get-alert')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data: any) => {
          this.visibleAlertMessage = data.alerta_suscripcion;
        }
      });
  }
}
