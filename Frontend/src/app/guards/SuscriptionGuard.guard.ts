import { Injectable } from '@angular/core';
import { Router, CanActivate } from '@angular/router';
import { ApiService } from '@services/api.service';

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

    // Verificar estado de suscripción
    if (userData.estado_suscripcion === 'en prueba' && userData.dias_faltantes <= 0) {
      this.router.navigate(['/paywall']);
      return false;
    }

    if (['inactivo', 'cancelado', 'pendiente'].includes(userData.estado_suscripcion)) {
      this.router.navigate(['/paywall']);
      return false;
    }

    return true;
  }
}