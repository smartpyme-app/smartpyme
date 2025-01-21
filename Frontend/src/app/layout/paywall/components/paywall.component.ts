// paywall.component.ts
import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';
import { AppConstants } from '../../../constants/app.constants';
@Component({
  selector: 'app-paywall',
  templateUrl: './paywall.component.html',
  styleUrls: ['./paywall.component.css']
})
export class PaywallComponent implements OnInit {
  readonly ESTADOS_SUSCRIPCION = AppConstants.ESTADOS_SUSCRIPCION;

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
    this.planName = plan;
    
    switch (plan) {
      case AppConstants.PLANES.EMPRENDEDOR.NOMBRE:
        this.planFeatures = AppConstants.PLANES.EMPRENDEDOR.CARACTERISTICAS;
        this.price = AppConstants.PLANES.EMPRENDEDOR.PRECIO;
        break;
      case AppConstants.PLANES.ESTANDAR.NOMBRE:
        this.planFeatures = AppConstants.PLANES.ESTANDAR.CARACTERISTICAS;
        this.price = AppConstants.PLANES.ESTANDAR.PRECIO;
        break;
      case AppConstants.PLANES.AVANZADO.NOMBRE:
        this.planFeatures = AppConstants.PLANES.AVANZADO.CARACTERISTICAS;
        this.price = AppConstants.PLANES.AVANZADO.PRECIO;
        break;
    }
  }

  getMensajeSuscripcion(): string {
    if (this.estadoSuscripcion === this.ESTADOS_SUSCRIPCION.EN_PRUEBA) {
      return 'Tu suscripción de prueba ha expirado';
    }

    if (this.estadoSuscripcion === this.ESTADOS_SUSCRIPCION.CANCELADO) {
      return 'Tu suscripción ha sido cancelada';
    }

    if (this.estadoSuscripcion === this.ESTADOS_SUSCRIPCION.INACTIVO) {
      return 'Tu suscripción ha expirado';
    }

    if (this.estadoSuscripcion === this.ESTADOS_SUSCRIPCION.PENDIENTE) {
      return 'Tu suscripción necesita ser renovada';
    }

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
