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
    console.log(userData.dias_faltantes);
    
    if (!userData) {
      this.router.navigate(['/login']);
      return false;
    }

    // Verificar estado de suscripción
    if (userData.estado_suscripcion === 'en prueba' && userData.dias_faltantes <= 0) {
      this.router.navigate(['/paywall']);
      return false;
    }

    if (['inactivo', 'cancelado'].includes(userData.estado_suscripcion)) {
      this.router.navigate(['/paywall']);
      return false;
    }

    // Verificar si han pasado más de 10 días desde el vencimiento
    if (userData.dias_faltantes < 0 && Math.abs(userData.dias_faltantes) >= 10) {
      this.router.navigate(['/paywall']);
      return false;
    }


    return true;
  }
}