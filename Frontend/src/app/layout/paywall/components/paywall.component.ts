// paywall.component.ts
import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';
@Component({
  selector: 'app-paywall',
  templateUrl: './paywall.component.html',
  styleUrls: ['./paywall.component.css']
})
export class PaywallComponent implements OnInit {
  readonly ESTADOS_SUSCRIPCION = {
    ACTIVO: 'activo',
    INACTIVO: 'inactivo',
    CANCELADO: 'cancelado',
    PENDIENTE: 'pendiente',
    RENOVADO: 'renovado',
    EN_PRUEBA: 'en prueba'
  };

  readonly PLAN_URLS: { [key: string]: string } = {
    'Emprendedor': 'https://pay.n1co.shop/pl/WEwwXTOpy',
    'Estándar': 'https://pay.n1co.shop/pl/yX99lF1Dl',
    'Avanzado': 'https://pay.n1co.shop/pl/vbj8Rh0y1',
    'Pro': 'https://pay.n1co.shop/pl/vbj8Rh0y1'
  };

  planName: string = '';
  price: number = 0;
  currencySymbol: string = '$';
  planFeatures: string[] = [];
  loading: boolean = false;
  estadoSuscripcion: string = '';
  diasFaltantes: number = 0;
  
  constructor(
    private http: HttpClient,
    private apiService: ApiService,
    private router: Router
  ) {}

  ngOnInit() {
    this.loadUserData();
  }

  loadUserData() {
    const userData = this.apiService.auth_user();
    if (userData) {
      this.planName = userData.plan;
      this.estadoSuscripcion = userData.estado_suscripcion;
      this.diasFaltantes = userData.dias_faltantes;
      this.setPlanFeatures(userData.plan);
    }
  }

  setPlanFeatures(plan: string) {
    this.planName = plan; // Asegura que esto esté presente
    
    switch (plan) {
      case 'Emprendedor':
        this.planFeatures = [
          'Funciones básicas',
          'Soporte por correo',
          'Límite de usuarios básico'
        ];
        this.price = 19.99;
        break;
      case 'Estándar':
        this.planFeatures = [
          'Todas las funciones básicas',
          'Soporte prioritario',
          'Más usuarios permitidos',
          'Reportes avanzados'
        ];
        this.price = 28.25;
        break;
      case 'Avanzado':
        this.planFeatures = [
          'Todas las funciones estándar',
          'Acceso a API',
          'Soporte 24/7'
        ];
        this.price = 56.50;
        break;
    }
  }

  getMensajeSuscripcion(): string {
    if (this.estadoSuscripcion === this.ESTADOS_SUSCRIPCION.EN_PRUEBA) {
      return 'Tu suscripción de prueba ha expirado';
    }
    // if (this.diasFaltantes > 0) {
    //   return `Tu suscripción expirará en ${this.diasFaltantes} días`;
    // }
    return 'Tu suscripción ha expirado';
  }

  getTipoAlerta(): string {
    if (this.diasFaltantes <= 5 && this.diasFaltantes > 0) {
      return 'alert-warning';
    }
    if (this.estadoSuscripcion === this.ESTADOS_SUSCRIPCION.EN_PRUEBA) {
      return 'alert-info';
    }
    return 'alert-danger';
  }

  renewSubscription() {
    const paymentUrl = this.PLAN_URLS[this.planName];
    if (paymentUrl) {
      window.location.href = paymentUrl;
    }
  }

  logout() {
    // this.apiService.logout();
    this.router.navigate(['/login']);
  }
}
