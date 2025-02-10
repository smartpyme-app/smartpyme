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
export class LayoutComponent {
    public usuario: any = {};
    public elem: any;
    public isfullscreen: boolean = false;
    public isVisible: boolean = false;

    readonly ESTADOS_SUSCRIPCION = AppConstants.ESTADOS_SUSCRIPCION;

    constructor(
        public apiService: ApiService, 
        public alertService: AlertService, 
        private router: Router
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.mostrarAlertaSuscripcion();
    }

    RedirectSuscripcion() {
        this.router.navigate(['/suscripcion']);
    }

    getMensajeSuscripcion(): { mensaje: string, tipo: string } {
        // Verificar si el usuario existe y tiene la propiedad tiene_suscripcion
        if (!this.usuario || !this.usuario.hasOwnProperty('tiene_suscripcion') || !this.usuario.tiene_suscripcion) {
            return {
                mensaje: 'No cuentas con una suscripción activa. Haz clic aquí para activar tu suscripción y acceder a todas las funcionalidades.',
                tipo: 'error'
            };
        }

        const diasRestantes = this.usuario.dias_faltantes;
        const diasRestantesPrueba = this.usuario.dias_faltantes_prueba;

        // Manejar período de prueba
        if (this.usuario.estado_suscripcion.toLowerCase() === 'en prueba') {
            if (diasRestantesPrueba <= 0) {
                return {
                    mensaje: 'Tu prueba ha terminado. Por favor, renueva tu suscripción para continuar usando el servicio.',
                    tipo: 'error'
                };
            }
            
            if (diasRestantesPrueba === 1) {
                return {
                    mensaje: 'Tu prueba termina mañana. Activa tu suscripción para continuar usando el servicio.',
                    tipo: 'warning'
                };
            }
        
            return {
                mensaje: `Estás en período de prueba. Te quedan ${diasRestantesPrueba} días para probar el servicio.`,
                tipo: 'info'
            };
        }

        // Manejar suscripción normal
        if (diasRestantes === null || diasRestantes === undefined) {
            return {
                mensaje: 'No se pudo determinar el estado de tu suscripción. Por favor, contacta con soporte técnico.',
                tipo: 'error'
            };
        }

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

        // Caso por defecto para cualquier otro escenario
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
        if (this.usuario.estado_suscripcion.toLowerCase() === 'en prueba') {
            return this.usuario.dias_faltantes_prueba <= 1;
        }
        return !this.usuario.tiene_suscripcion || this.usuario.dias_faltantes <= 7;
    }
}