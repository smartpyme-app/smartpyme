import { Injectable } from '@angular/core';
import { Router, CanActivate } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AppConstants } from '../constants/app.constants';

@Injectable({
  providedIn: 'root'
})
export class SubscriptionGuard implements CanActivate {
  constructor(
    private router: Router, 
    private apiService: ApiService
  ) {}

  canActivate(): boolean {
    const userData = this.apiService.auth_user();
    
    if (!userData) {
      this.router.navigate(['/login']);
      return false;
    }

    // Asegurémonos de comparar usando minúsculas para evitar inconsistencias
    const estadoSuscripcion = userData.estado_suscripcion.toLowerCase();

    // Verificar si está en prueba y sin días restantes
    if (estadoSuscripcion === 'en prueba') {
      if (userData.dias_faltantes_prueba <= 0) {
        this.router.navigate(['/paywall']);
        return false;
      }
      return true; // Si está en prueba con días disponibles, permitir acceso
    }

    // Verificar estados que no permiten acceso
    if (['inactivo', 'cancelado', 'suspendido'].includes(estadoSuscripcion)) {
      this.router.navigate(['/paywall']);
      return false;
    }

    const accesoTemporal = this.accesoTemporalVigente(userData);

    // Tras el vencimiento: acceso solo en período de gracia (días 1..N); desde el día N+1 con saldos pendientes se redirige al paywall,
    // salvo acceso temporal concedido por administración (sin cambiar fecha de pago).
    if (estadoSuscripcion === 'activo' && userData.dias_faltantes < 0 && Math.abs(userData.dias_faltantes) > AppConstants.DIAS_PRORROGA_SUSCRIPCION) {
      if (!accesoTemporal) {
        this.router.navigate(['/paywall']);
        return false;
      }
    }

    // Pendiente y fecha vencida: bloquear salvo acceso temporal vigente
    if (estadoSuscripcion === 'pendiente' && userData.dias_faltantes <= 0) {
      if (!accesoTemporal) {
        this.router.navigate(['/paywall']);
        return false;
      }
    }

    return true;
  }

  private accesoTemporalVigente(userData: any): boolean {
    if (!userData?.acceso_temporal_hasta) {
      return false;
    }
    return new Date(userData.acceso_temporal_hasta).getTime() > Date.now();
  }
}