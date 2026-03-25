import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';
import { AppConstants } from '../../../constants/app.constants';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { Estado } from '../../../models/estado.interface';
import { firstValueFrom } from 'rxjs';

@Component({
  selector: 'app-paywall',
  templateUrl: './paywall.component.html',
  styleUrls: ['./paywall.component.css'],
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
    cvv: '',
  };

  billingInfo = {
    countryCode: '',
    stateCode: '',
    zipCode: '',
  };

  mostrar3DSModal = false;
  urlAutenticacion!: SafeResourceUrl;
  authenticationId: string = '';
  orderId: string = '';
  private authCheckInterval: any = null;

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
    this.apiService.getAll('paises-suscripcion', this.paises).subscribe(
      (paises) => {
        this.paises = paises;
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  getEstados(countryCode: string) {
    this.apiService.getAll(`estados-por-pais/${countryCode}`, []).subscribe(
      (estados) => {
        this.estados = estados;
      },
      (error) => {
        this.alertService.error(error);
      }
    );
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
      const estadoSeleccionado = this.estados.find(
        (estado) => estado.codigo === this.billingInfo.stateCode
      );

      if (estadoSeleccionado && estadoSeleccionado.codigo_postal) {
        this.billingInfo.zipCode = estadoSeleccionado.codigo_postal;
      }
    }
  }

  setPlanFeatures(plan: string) {
    this.planName = plan;
    const planData = Object.values(this.PLANES).find((p) => p.NOMBRE === plan);
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
        customer_phone: userData.telefono || '',
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.processChargeWithExistingMethod(paymentData)
      );

      if (result.requires_3ds) {
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
          result.authentication_url
        );
        // Guardar los IDs para usar cuando llegue el mensaje del iframe
        this.authenticationId = result.authentication_id;
        this.orderId = result.order_id;
        this.mostrar3DSModal = true;
        await this.handle3DSAuthentication(result);
      } else if (result.success) {
        this.alertService.success('Éxito', 'Pago procesado exitosamente');
        this.n1coPaymentService.setPaymentResponse(result);
        this.router.navigate(['/pago-exitoso-paywall']);
      }
    } catch (error: any) {
      this.alertService.error(
        'Error al procesar el pago: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
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
        this.alertService.error(
          'El número de tarjeta debe tener entre 13 y 16 dígitos'
        );
        return;
      }

      const monthPadded = this.paymentData.expirationMonth.padStart(2, '0');
      const userData = this.apiService.auth_user();
      const paymentMethodData = {
        customer: {
          id: userData.id.toString(),
          name: userData.name,
          email: userData.email,
          phoneNumber: userData.telefono || '',
        },
        card: {
          number: cardNumber,
          cardHolder: this.paymentData.cardHolder.trim(),
          expirationMonth: monthPadded,
          expirationYear: this.paymentData.expirationYear,
          cvv: this.paymentData.cvv,
        },
        plan: {
          id_plan: this.planId.toString(),
          plan_name: this.planName,
        },
        billingInfo: this.billingInfo,
        forceNewPaymentMethod: true,
        updatePaymentMethod: true,
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.createPaymentMethod(paymentMethodData)
      );

      if (result.requires_3ds) {
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
          result.authentication_url
        );
        // Guardar los IDs para usar cuando llegue el mensaje del iframe
        this.authenticationId = result.authentication_id;
        this.orderId = result.order_id;
        this.mostrar3DSModal = true;
        await this.handle3DSAuthentication(result);
      } else if (result.success) {
        this.alertService.success('Éxito', 'Pago procesado exitosamente');
        this.n1coPaymentService.setPaymentResponse(result);
        this.router.navigate(['/pago-exitoso-paywall']);
      }
    } catch (error: any) {
      this.alertService.error(
        'Error al procesar el pago: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
    } finally {
      this.loading = false;
    }
  }

  private async handle3DSAuthentication(result: any) {
    // Timeout máximo de seguridad (2 minutos)
    // Si no llega ningún mensaje del iframe, cerrar el modal
    setTimeout(() => {
      if (this.mostrar3DSModal) {
        this.stopAuthCheck();
        this.mostrar3DSModal = false;
        this.loading = false;
        this.alertService.error('El tiempo de autenticación ha expirado');
      }
    }, 120000);
  }

  public async on3DSMessageReceived(messageData: any) {
    console.log('📨 Mensaje recibido del modal 3DS:', messageData);
    console.log('MessageType:', messageData.messageType);
    console.log('Status:', messageData.status);

    // Verificar que sea autenticación completa exitosa
    if (
      messageData.messageType === 'authentication.complete' &&
      messageData.status === 'SUCCESS'
    ) {
      // Detener el polling ya que recibimos el mensaje directo del iframe
      this.stopAuthCheck();

      // Esperar 500ms para que el usuario vea la confirmación (check) en el iframe
      await new Promise((resolve) => setTimeout(resolve, 500));

      // Cerrar el modal después de mostrar la confirmación
      this.mostrar3DSModal = false;

      // Esperar 100ms antes de procesar el pago
      await new Promise((resolve) => setTimeout(resolve, 100));

      try {
        // Procesar el pago usando los datos del mensaje
        const response = await firstValueFrom(
          this.n1coPaymentService.processDirectPayment3DS({
            authentication_id: messageData.authenticationId,
            order_id: messageData.orderId,
          })
        );

        if (response.success) {
          this.alertService.success('Éxito', 'Pago procesado exitosamente');
          this.n1coPaymentService.setPaymentResponse(response);
          this.router.navigate(['/pago-exitoso-paywall']);
        } else {
          this.alertService.error('Error al procesar el pago');
        }

        this.loading = false;
      } catch (error: any) {
        console.error('Error procesando pago 3DS:', error);
        this.alertService.error(
          'Error al procesar el pago: ' +
            (error.error?.message || error.message || 'Error desconocido')
        );
        this.loading = false;
      }
    }
    // Manejar autenticación fallida
    else if (
      messageData.messageType === 'authentication.failed' &&
      messageData.status === 'FAILED'
    ) {
      // Detener el polling ya que recibimos el mensaje directo del iframe
      this.stopAuthCheck();

      try {
        // Actualizar el estado en el backend a fallida
        await firstValueFrom(
          this.n1coPaymentService.changeStatusAuthentication3DS({
            authentication_id: messageData.authenticationId,
            order_id: messageData.orderId,
            status: 'failed',
          })
        );

        // Cerrar el modal
        this.mostrar3DSModal = false;
        this.loading = false;

        // Mostrar alerta de error
        this.alertService.error(
          'No fue posible procesar el pago. Por favor, reintenta nuevamente con otra tarjeta o comunícate con soporte.'
        );
      } catch (error: any) {
        console.error(
          'Error actualizando estado de autenticación fallida:',
          error
        );
        // Cerrar el modal de todas formas
        this.mostrar3DSModal = false;
        this.loading = false;

        // Mostrar alerta de error
        this.alertService.error(
          'No fue posible procesar el pago. Por favor, reintenta nuevamente con otra tarjeta o comunícate con soporte.'
        );
      }
    }
    // Si el mensaje no coincide con ningún caso conocido, loguear pero no detener el polling
    else {
      console.warn('⚠️ Mensaje recibido pero no reconocido:', messageData);
      console.warn(
        'MessageType esperado: authentication.complete o authentication.failed'
      );
      console.warn('Status esperado: SUCCESS o FAILED');
      // No detener el polling, puede que llegue otro mensaje
    }
  }

  private stopAuthCheck() {
    if (this.authCheckInterval) {
      clearInterval(this.authCheckInterval);
      this.authCheckInterval = null;
    }
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
