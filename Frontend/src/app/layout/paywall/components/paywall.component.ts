import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';
import { AppConstants } from '../../../constants/app.constants';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

@Component({
  selector: 'app-paywall',
  templateUrl: './paywall.component.html',
  styleUrls: ['./paywall.component.css']
})
export class PaywallComponent implements OnInit {
  readonly ESTADOS_SUSCRIPCION = AppConstants.ESTADOS_SUSCRIPCION;
  readonly PLANES = AppConstants.PLANES;

  planName: string = '';
  price: number = 0;
  planFeatures: string[] = [];
  loading: boolean = false;
  estadoSuscripcion: string = '';
  diasFaltantes: number = 0;
  
  showCardForm: boolean = false;
  paymentData = {
    cardNumber: '',
    cardHolder: '',
    expirationMonth: '',
    expirationYear: '',
    cvv: ''
  };

  billingInfo = {
    countryCode: 'SV',
    stateCode: 'SS',
    zipCode: '1101'
  };

  mostrar3DSModal = false;
  urlAutenticacion!: SafeResourceUrl;

  constructor(
    private http: HttpClient,
    private apiService: ApiService,
    private router: Router,
    private n1coPaymentService: N1coPaymentService,
    private alertService: AlertService,
    private sanitizer: DomSanitizer
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
    const planData = Object.values(this.PLANES).find(p => p.NOMBRE === plan);
    if (planData) {
      this.planFeatures = planData.CARACTERISTICAS;
      this.price = planData.PRECIO;
    }
  }

  // setPlanFeatures(plan: string) {
  //   this.planName = plan;
    
  //   switch (plan) {
  //     case AppConstants.PLANES.EMPRENDEDOR.NOMBRE:
  //       this.planFeatures = AppConstants.PLANES.EMPRENDEDOR.CARACTERISTICAS;
  //       this.price = AppConstants.PLANES.EMPRENDEDOR.PRECIO;
  //       break;
  //     case AppConstants.PLANES.ESTANDAR.NOMBRE:
  //       this.planFeatures = AppConstants.PLANES.ESTANDAR.CARACTERISTICAS;
  //       this.price = AppConstants.PLANES.ESTANDAR.PRECIO;
  //       break;
  //     case AppConstants.PLANES.AVANZADO.NOMBRE:
  //       this.planFeatures = AppConstants.PLANES.AVANZADO.CARACTERISTICAS;
  //       this.price = AppConstants.PLANES.AVANZADO.PRECIO;
  //       break;
  //   }
  // }

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

  // renewSubscription() {
  //   const paymentUrl = this.PLAN_URLS[this.planName];
  //   if (paymentUrl) {
  //     window.location.href = paymentUrl;
  //   }
  // }

  // logout() {
  //   // this.apiService.logout();
  //   this.router.navigate(['/login']);
  // }

  async processPayment() {
    if (this.loading) return;
    this.loading = true;

    try {
      const cardNumber = this.paymentData.cardNumber.replace(/\s/g, '');
      if (cardNumber.length < 13 || cardNumber.length > 16) {
        this.alertService.error('El número de tarjeta debe tener entre 13 y 16 dígitos');
        return;
      }

      const monthPadded = this.paymentData.expirationMonth.padStart(2, '0');
      const userData = this.apiService.auth_user();

      const paymentMethodData = {
        customer: {
          id: userData.id.toString(),
          name: userData.name,
          email: userData.email,
          phoneNumber: userData.telefono || ''
        },
        card: {
          number: cardNumber,
          cardHolder: this.paymentData.cardHolder.trim(),
          expirationMonth: monthPadded,
          expirationYear: this.paymentData.expirationYear,
          cvv: this.paymentData.cvv
        },
        plan: {
          id_plan: userData.plan_id.toString(),
          plan_name: this.planName
        },
        billingInfo: this.billingInfo
      };

      const result = await this.n1coPaymentService.createPaymentMethod(paymentMethodData).toPromise();

      if (result.requires_3ds) {
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
          result.authentication_url
        );
        this.mostrar3DSModal = true;
        
        await this.handle3DSAuthentication(result);
      } else if (result.success) {
        this.alertService.success('Exito','Pago procesado exitosamente');
        this.n1coPaymentService.setPaymentResponse(result);
        this.router.navigate(['/pago-exitoso-paywall']);
      }

    } catch (error: any) {
      this.alertService.error('Error al procesar el pago: ' + 
        (error.error?.message || error.message || 'Error desconocido'));
    } finally {
      this.loading = false;
    }
  }

  private async handle3DSAuthentication(result: any) {
    const checkAuthentication = async () => {
      try {
        const authStatus = await this.n1coPaymentService.checkAuthenticationStatus({
          authentication_id: result.authentication_id,
          order_id: result.order_id
        }).toPromise();

        switch (authStatus.estado) {
          case 'autenticacion_exitosa':
            this.mostrar3DSModal = false;
            const response = await this.n1coPaymentService.processDirectPayment3DS({
              authentication_id: result.authentication_id,
              order_id: result.order_id
            }).toPromise();
            
            if (response.success) {
              this.alertService.success('Exito','Pago procesado exitosamente');
              this.n1coPaymentService.setPaymentResponse(result);
              this.router.navigate(['/pago-exitoso-paywall']);
            }
            return true;


          case 'autenticacion_fallida':
          case 'autenticacion_cancelada':
            this.mostrar3DSModal = false;
            this.alertService.error('La autenticación ha fallado');
            return true;

          case 'autenticacion_pendiente':
            return false;

          default:
            return false;
        }
      } catch (error) {
        console.error('Error verificando autenticación:', error);
        this.alertService.error('Error verificando la autenticación');
        this.mostrar3DSModal = false;
        return true;
      }
    };

    setTimeout(() => {
      const interval = setInterval(async () => {
        const shouldStop = await checkAuthentication();
        if (shouldStop) {
          clearInterval(interval);
          this.loading = false;
        }
      }, 3000);

      setTimeout(() => {
        clearInterval(interval);
        if (this.mostrar3DSModal) {
          this.mostrar3DSModal = false;
          this.alertService.error('Tiempo de autenticación expirado');
          this.loading = false;
        }
      }, 120000);
    }, 10000);
  }

  togglePaymentForm() {
    this.showCardForm = !this.showCardForm;
  }

  logout() {
    this.apiService.logout();
    this.router.navigate(['/login']);
  }
}