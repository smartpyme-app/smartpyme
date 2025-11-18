import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';
import { AppConstants } from '../../../constants/app.constants';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { Estado } from '../../../models/estado.interface';
import { firstValueFrom } from 'rxjs';
import { ThreedsModalComponent } from '../../../auth/register/pago/modal/threeds-modal.component';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-paywall',
    templateUrl: './paywall.component.html',
    styleUrls: ['./paywall.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ThreedsModalComponent],
    
})
export class PaywallComponent implements OnInit {
  readonly ESTADOS_SUSCRIPCION = AppConstants.ESTADOS_SUSCRIPCION;
  readonly PLANES = AppConstants.PLANES;
  showAllPlans: boolean = true; 

  planName: string = '';
  planId: number = 0; 
  price: number = 0;
  montoPlanActual: number = this.apiService.auth_user().monto_plan;
  planFeatures: string[] = [];
  loading: boolean = false;
  estadoSuscripcion: string = '';
  diasFaltantes: number = 0;
  public estadoSeleccionado: any = null;
  public paises = [];
  public estados: Estado[] = [];
  public empresaId: number = 0;
  showPaymentOptions: boolean = false;
  showCardForm: boolean = false;
  paymentMethodExists: boolean = false;
  existingPaymentMethod: any = null;
  selectedPaymentOption: 'existing' | 'new' | null = null;

  
  paymentData = {
    cardNumber: '',
    cardHolder: '',
    expirationMonth: '',
    expirationYear: '',
    cvv: ''
  };

  billingInfo = {
    countryCode: '',
    stateCode: '',
    zipCode: ''
  };

  mostrar3DSModal = false;
  urlAutenticacion!: SafeResourceUrl;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

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
      this.empresaId = userData.empresa.id;
      this.setPlanFeatures(userData.plan);
    }


    this.getPaises();
    this.checkExistingPaymentMethod();
  }

  async checkExistingPaymentMethod() {
    try {
      const userData = this.apiService.auth_user();
      const response = await firstValueFrom(
        this.n1coPaymentService.getExistingPaymentMethod(userData.id)
      );
      
      if (response.success && response.data) {
        this.paymentMethodExists = true;
        this.existingPaymentMethod = response.data;
      }
    } catch (error) {
      console.log('No hay método de pago existente');
      this.paymentMethodExists = false;
    }
  }


  getPaises() {
    this.apiService.getAll('paises-suscripcion', this.paises)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (paises) => {
          this.paises = paises;
        },
        error: (error) => {
          this.alertService.error(error);
        }
      });
  }

  getEstados(countryCode: string) {
    this.apiService.getAll(`estados-por-pais/${countryCode}`, [])
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (estados) => {
          this.estados = estados;
        },
        error: (error) => {
          this.alertService.error(error);
        }
      });
  }

  onPaisChange() {
    if (this.billingInfo.countryCode) {
      this.getEstados(this.billingInfo.countryCode);      
      this.billingInfo.stateCode = '';      
      this.billingInfo.zipCode = '';
    }
  }

  onEstadoChange() {
    if (this.billingInfo.stateCode) {
      const estadoSeleccionado = this.estados.find(estado => estado.codigo === this.billingInfo.stateCode);
      
      if (estadoSeleccionado && estadoSeleccionado.codigo_postal) {
        this.billingInfo.zipCode = estadoSeleccionado.codigo_postal;
      }
    }
  }

  setPlanFeatures(plan: string) {
    this.planName = plan;
    const planData = Object.values(this.PLANES).find(p => p.NOMBRE === plan);
    if (planData) {
      this.planFeatures = planData.CARACTERISTICAS;
      this.price = planData.PRECIO;
      
      // Establecer el ID del plan basado en el nombre seleccionado
      switch (plan) {
        case this.PLANES.EMPRENDEDOR.NOMBRE:
          this.planId = AppConstants.PLANID.EMPRENDEDOR;
          break;
        case this.PLANES.ESTANDAR.NOMBRE:
          this.planId = AppConstants.PLANID.ESTANDAR;
          break;
        case this.PLANES.AVANZADO.NOMBRE:
          this.planId = AppConstants.PLANID.AVANZADO;
          break;
        case this.PLANES.PRO.NOMBRE:
          this.planId = AppConstants.PLANID.PRO;
          break;
        default:
          this.planId = 0;
      }
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

  showPaymentOptionsModal() {
    this.showPaymentOptions = true;
    this.showAllPlans = false;
  }

  // ✅ Seleccionar opción de pago
  selectPaymentOption(option: 'existing' | 'new') {
    this.selectedPaymentOption = option;
    
    if (option === 'new') {
      this.showCardForm = true;
      this.showPaymentOptions = false;
    } else if (option === 'existing') {
      this.processPaymentWithExistingMethod();
    }
  }

  // ✅ Procesar pago con método existente
  async processPaymentWithExistingMethod() {
    if (this.loading) return;
    this.loading = true;

    try {
      const userData = this.apiService.auth_user();
      const paymentData = {
        metodo_pago_id: this.existingPaymentMethod.id,
        id_usuario: userData.id,
        empresa_id: userData.empresa.id,
        plan_id: this.planId,
        customer_name: userData.name,
        customer_email: userData.email,
        customer_phone: userData.telefono || ''
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.processChargeWithExistingMethod(paymentData)
      );

      if (result.requires_3ds) {
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
          result.authentication_url
        );
        this.mostrar3DSModal = true;
        await this.handle3DSAuthentication(result);
      } else if (result.success) {
        this.alertService.success('Éxito', 'Pago procesado exitosamente');
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

  // ✅ Procesar pago con nuevo método
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
          id_plan: this.planId.toString(),
          plan_name: this.planName
        },
        billingInfo: this.billingInfo,
        forceNewPaymentMethod: true,
        updatePaymentMethod: true
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.createPaymentMethod(paymentMethodData)
      );

      if (result.requires_3ds) {
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
          result.authentication_url
        );
        this.mostrar3DSModal = true;
        await this.handle3DSAuthentication(result);
      } else if (result.success) {
        this.alertService.success('Éxito','Pago procesado exitosamente');
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
              this.n1coPaymentService.setPaymentResponse(response);
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
    // Actualizar la visibilidad de los planes
    this.showAllPlans = !this.showCardForm;
  }
  
  backToPaymentOptions() {
    this.showCardForm = false;
    this.showPaymentOptions = true;
    this.selectedPaymentOption = null;
  }

  // ✅ Cancelar todo y volver al inicio
  cancelPayment() {
    this.showCardForm = false;
    this.showPaymentOptions = false;
    this.showAllPlans = true;
    this.selectedPaymentOption = null;
  }


  logout() {
    this.apiService.logout();
    this.router.navigate(['/login']);
  }
}