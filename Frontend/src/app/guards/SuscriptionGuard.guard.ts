import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '@services/api.service';

@Injectable({
  providedIn: 'root'
})
export class SubscriptionGuard  {
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

    // Verificar si han pasado más de 10 días desde el vencimiento
    if (estadoSuscripcion === 'activo' && userData.dias_faltantes < 0 && Math.abs(userData.dias_faltantes) >= 10) {
      this.router.navigate(['/paywall']);
      return false;
    }

    // Verificar si está pendiente y sin días restantes
    if (estadoSuscripcion === 'pendiente' && userData.dias_faltantes <= 0) {
      this.router.navigate(['/paywall']);
      return false;
    }

    return true;
  }
}