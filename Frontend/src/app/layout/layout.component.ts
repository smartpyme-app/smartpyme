import { Component } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';
import { AppConstants } from '../constants/app.constants';
@Component({
  selector: 'app-layout',
  templateUrl: './layout.component.html',
  styleUrls: ['./layout.component.css']
})

export class LayoutComponent  {
    public usuario: any = {};
    public elem: any;
    public isfullscreen: boolean = false;
    public isVisible: boolean = false;

    readonly ESTADOS_SUSCRIPCION = AppConstants.ESTADOS_SUSCRIPCION;

    constructor(public apiService: ApiService, public alertService: AlertService, private router: Router) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.mostrarAlertaSuscripcion();
    }

    RedirectSuscripcion() {
        this.router.navigate(['/suscripcion']);
    }

    getMensajeSuscripcion(): { mensaje: string, tipo: string } {
        const diasRestantes = this.usuario.dias_faltantes;

        if (diasRestantes > 7) {
            return {
            mensaje: `Tu suscripción vencerá en ${diasRestantes} días`,
            tipo: 'info'
            };
        }
        
        if (diasRestantes <= 7 && diasRestantes > 3) {
            return {
            mensaje: 'Tu suscripción está por vencer. Por favor, renueva ahora.',
            tipo: 'warning'
            };
        }
        
        if (diasRestantes <= 3 && diasRestantes > 0) {
            return {
            mensaje: '¡Atención! Tu suscripción vencerá muy pronto.',
            tipo: 'error'
            };
        }
        
        if (diasRestantes === 0) {
            return {
            mensaje: 'Tu suscripción ha vencido. Renueva ahora para continuar usando el servicio.',
            tipo: 'error'
            };
        }

        // Si los días son negativos, significa que ya pasó el tiempo
        const diasVencidos = Math.abs(diasRestantes);
        if (diasVencidos >= 10) {
            return {
            mensaje: 'Tu cuenta ha sido desactivada por falta de pago.',
            tipo: 'error'
            };
        }
        
        return {
            mensaje: 'Tu suscripción está vencida. Renueva ahora para evitar la desactivación de tu cuenta.',
            tipo: 'error'
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
        return this.usuario.dias_faltantes <= 7;
      }

}
