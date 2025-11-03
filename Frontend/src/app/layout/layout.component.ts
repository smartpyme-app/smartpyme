import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SpeedDialComponent } from '../shared/speed-dial/speed-dial.component';
import { ChatDrawerComponent } from '../shared/chat/chat-drawer.component';
import { HeaderComponent } from './header/header.component';
import { FooterComponent } from './footer/footer.component';
import { Router } from '@angular/router';
import { AppConstants } from '../constants/app.constants';

@Component({
    selector: 'app-layout',
    templateUrl: './layout.component.html',
    styleUrls: ['./layout.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, SpeedDialComponent, ChatDrawerComponent, HeaderComponent, FooterComponent],
    
})
export class LayoutComponent {
  public usuario: any = {};
  public elem: any;
  public isfullscreen: boolean = false;
  public isVisible: boolean = false;
  public visibleAlertMessage: boolean = false;

  readonly ESTADOS_SUSCRIPCION = AppConstants.ESTADOS_SUSCRIPCION;

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

  if (diasRestantes > 7) {
    if (this.isAdmin()) {
      return {
        mensaje: `Tu suscripción vencerá en ${diasRestantes} días`,
        tipo: 'info',
      };
    } else {
      return {
        mensaje: `La suscripción de la empresa vencerá en ${diasRestantes} días`,
        tipo: 'info',
      };
    }
  }

  if (diasRestantes <= 7 && diasRestantes > 3) {
    if (this.isAdmin()) {
      return {
        mensaje: 'Tu suscripción está por vencer. Por favor, renueva ahora.',
        tipo: 'warning',
      };
    } else {
      return {
        mensaje: 'La suscripción de la empresa está por vencer. Contacta al administrador.',
        tipo: 'warning',
      };
    }
  }

  if (diasRestantes <= 3 && diasRestantes > 0) {
    if (this.isAdmin()) {
      return {
        mensaje: '¡Atención! Tu suscripción vencerá muy pronto.',
        tipo: 'error',
      };
    } else {
      return {
        mensaje: '¡Atención! La suscripción de la empresa vencerá muy pronto. Contacta al administrador urgentemente.',
        tipo: 'error',
      };
    }
  }

  if (diasRestantes === 0) {
    if (this.isAdmin()) {
      return {
        mensaje: 'Tu suscripción ha vencido. Renueva ahora para continuar usando el servicio.',
        tipo: 'error',
      };
    } else {
      return {
        mensaje: 'La suscripción de la empresa ha vencido. Contacta al administrador para reactivarla.',
        tipo: 'error',
      };
    }
  }

  // Si los días son negativos, significa que ya pasó el tiempo
  const diasVencidos = Math.abs(diasRestantes);
  if (diasVencidos >= 10) {
    if (this.isAdmin()) {
      return {
        mensaje: 'Tu cuenta ha sido desactivada por falta de pago.',
        tipo: 'error',
      };
    } else {
      return {
        mensaje: 'La cuenta de la empresa ha sido desactivada por falta de pago. Contacta al administrador.',
        tipo: 'error',
      };
    }
  }

  // Caso por defecto para cualquier otro escenario
  if (this.isAdmin()) {
    return {
      mensaje: 'Tu suscripción está vencida. Renueva ahora para evitar la desactivación de tu cuenta.',
      tipo: 'error',
    };
  } else {
    return {
      mensaje: 'La suscripción está vencida. Contacta al administrador para renovarla y evitar la desactivación.',
      tipo: 'error',
    };
  }
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
    
    this.apiService.getAll('empresa/isvisible-alert').subscribe(
      (response: any) => {
        console.log('Alerta desactivada correctamente:', response);
        
        if (this.usuario && this.usuario.empresa) {
          this.usuario.empresa.alerta_suscripcion = false;
          this.getAlertSuscription();
        }
      },
      (error) => {
        console.error('Error al desactivar la alerta:', error);
      }
    );
  }

  getAlertSuscription() {
      
    this.apiService.getAll('empresa/get-alert').subscribe((data: any) => {
      this.visibleAlertMessage = data.alerta_suscripcion;

    });
  }
}
