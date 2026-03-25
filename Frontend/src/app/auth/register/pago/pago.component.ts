import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';
import { PromocionalService } from '@services/promocional.service';
import { firstValueFrom } from 'rxjs';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { Estado } from '../../../models/estado.interface';

@Component({
  selector: 'app-pago',
  templateUrl: './pago.component.html',
})
export class PagoComponent implements OnInit {
  public user: any = {};
  public loading = false;
  public anio: number = new Date().getFullYear();
  public showDirectPayment = false;
  public paymentData = {
    cardNumber: '',
    cardHolder: '',
    expirationMonth: '',
    expirationYear: '',
    cvv: '',
  };

  public billingInfo = {
    countryCode: '',
    stateCode: '',
    zipCode: '',
  };

  public processingPayment = false;
  public mostrar3DSModal = false;
  public urlAutenticacion!: SafeResourceUrl;
  public authenticationId: string = '';
  public orderId: string = '';
  public estadoSeleccionado: any = null;
  private authCheckInterval: any = null;
  public paises = [];
  public estados: Estado[] = [];
  public tieneCodigoPromocional: boolean = false;
  public totalOriginal: number = 0;
  public tieneDescuento: boolean = false;

  constructor(
    private apiService: ApiService,
    private router: Router,
    private route: ActivatedRoute,
    private alertService: AlertService,
    private n1coPaymentService: N1coPaymentService,
    private sanitizer: DomSanitizer,
    private promocionalService: PromocionalService
  ) {}

  ngOnInit() {
    this.user = this.apiService.register_user();
    this.getPaises();

    // Verificar si hay código promocional en la URL
    const codigoPromocional = this.route.snapshot.queryParamMap.get('promo');
    if (codigoPromocional) {
      this.tieneCodigoPromocional = true;
      // Si hay código promocional, mostrar directamente el formulario de pago
      this.showDirectPayment = true;
    }

    // Calcular el total original y verificar descuento
    this.calcularTotalOriginal();
  }

  private calcularTotalOriginal() {
    if (!this.user.empresa) return;

    const plan = this.user.empresa.plan;
    // Obtener tipo_plan de empresa, puede venir como tipo_plan o frecuencia_pago
    const tipoPlan = this.user.empresa.tipo_plan || this.user.empresa.frecuencia_pago || 'Mensual';
    const totalActual = this.user.empresa.total || 0;

    // Calcular precio mensual base del plan
    let precioMensual = 0;

    // El plan puede venir como número o como string (nombre del plan)
    const planNumero = typeof plan === 'number' ? plan : parseInt(plan);
    const planNombre = typeof plan === 'string' ? plan.toLowerCase() : '';

    // Verificar por número primero
    if (planNumero == 1) {
      // Emprendedor
      precioMensual = 16.95;
    } else if (planNumero == 2) {
      // Estándar
      precioMensual = 28.25;
    } else if (planNumero == 3) {
      // Avanzado
      precioMensual = 56.5;
    } else if (planNumero == 4) {
      // Pro
      precioMensual = 113;
    } else {
      // Si no coincide con ningún número, buscar por nombre del plan
      if (
        planNombre.includes('estándar') ||
        planNombre.includes('estandar') ||
        planNombre === 'estándar' ||
        planNombre === 'estandar'
      ) {
        precioMensual = 28.25;
      } else if (planNombre.includes('avanzado') || planNombre === 'avanzado') {
        precioMensual = 56.5;
      } else if (planNombre.includes('pro') || planNombre === 'pro') {
        precioMensual = 113;
      } else if (
        planNombre.includes('emprendedor') ||
        planNombre === 'emprendedor'
      ) {
        precioMensual = 16.95;
      }
    }

    // Calcular precio original según la frecuencia de pago
    let precioOriginal = 0;
    if (tipoPlan === 'Mensual') {
      precioOriginal = precioMensual;
    } else if (tipoPlan === 'Trimestral') {
      precioOriginal = precioMensual * 3;
    } else if (tipoPlan === 'Anual') {
      // Aplicar 20% de descuento al plan anual
      precioOriginal = (precioMensual * 12) * 0.8;
    } else {
      // Por defecto, mensual
      precioOriginal = precioMensual;
    }

    this.totalOriginal = precioOriginal;

    // Verificar si hay descuento usando el servicio promocional
    const codigoPromocional =
      this.user.empresa?.codigo_promocional ||
      this.route.snapshot.queryParamMap.get('promo');

    if (codigoPromocional) {
      const tipoPlan = this.user.empresa?.tipo_plan;
      this.promocionalService.validarCodigo(codigoPromocional, tipoPlan).subscribe(
        codigoPromo => {
      if (codigoPromo && precioOriginal > 0) {
        this.tieneDescuento = true;
        this.totalOriginal = precioOriginal;
      }
        },
        error => {
          console.error('Error al validar código promocional:', error);
          // Si hay descuento comparando totales (por si acaso)
          if (
            precioOriginal > 0 &&
            totalActual > 0 &&
            totalActual < precioOriginal
          ) {
            this.tieneDescuento = true;
            this.totalOriginal = precioOriginal;
          }
        }
      );
    } else if (
      precioOriginal > 0 &&
      totalActual > 0 &&
      totalActual < precioOriginal
    ) {
      // Verificar si hay descuento comparando totales (por si acaso)
      this.tieneDescuento = true;
      this.totalOriginal = precioOriginal;
    }
  }

  // Método original para checkout N1co
  public checkout() {
    let URL = this.apiService.baseUrl + '/payment/' + this.user.empresa.id;
    window.open(this.user.url_n1co + '/?callbackurl=' + URL, '_self');
  }

  // Nuevo método para procesar pago con tarjeta
  public async createMethodPaymentWithCharge() {
    if (this.processingPayment) return;

    this.processingPayment = true;

    try {
      // Formatear el número de tarjeta: eliminar espacios y validar longitud
      const cardNumber = this.paymentData.cardNumber.replace(/\s/g, '');
      if (cardNumber.length < 13 || cardNumber.length > 16) {
        this.alertService.error(
          'El número de tarjeta debe tener entre 13 y 16 dígitos'
        );
        return;
      }

      // Formatear el mes con padding si es necesario
      const expirationMonth = this.paymentData.expirationMonth.padStart(2, '0');

      const paymentMethodData = {
        customer: {
          id: this.user.id.toString(),
          name: this.user.name,
          email: this.user.email,
          phoneNumber: this.user.telefono || '',
        },
        card: {
          number: cardNumber,
          cardHolder: this.paymentData.cardHolder.trim(),
          expirationMonth: expirationMonth,
          expirationYear: this.paymentData.expirationYear,
          cvv: this.paymentData.cvv,
        },
        plan: {
          id_plan: this.user.plan_id,
          plan_name: this.user.plan,
        },
        billingInfo: {
          countryCode: this.billingInfo.countryCode,
          stateCode: this.billingInfo.stateCode,
          zipCode: this.billingInfo.zipCode,
        },
      };

      const result = await firstValueFrom(
        this.n1coPaymentService.createPaymentMethod(paymentMethodData)
      );

      if (result.requires_3ds) {
        // console.log('Resultado 3DS:', result);
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
          result.authentication_url
        );
        // Guardar los IDs para usar cuando llegue el mensaje del iframe
        this.authenticationId = result.authentication_id;
        this.orderId = result.order_id;
        this.mostrar3DSModal = true;

        // Timeout máximo de seguridad (2 minutos)
        // Si no llega ningún mensaje del iframe, cerrar el modal
        setTimeout(() => {
          if (this.mostrar3DSModal) {
            this.stopAuthCheck();
            this.mostrar3DSModal = false;
            this.processingPayment = false;
            this.alertService.error('El tiempo de autenticación ha expirado');
          }
        }, 120000);

        return;
      }

      if (result.success) {
        // Proceder con el cargo usando el ID del método de pago
        const chargeData = {
          empresa_id: this.user.empresa.id,
          card_id: result.data.id,
          amount: this.user.empresa.plan.precio,
          customer_name: this.user.name,
          customer_email: this.user.email,
          customer_phone: this.user.telefono || '',
          description: `Pago plan ${this.user.empresa.plan.nombre}`,
        };

        const chargeResult = await firstValueFrom(
          this.n1coPaymentService.createMethodPaymentWithCharge(chargeData)
        );

        // Manejar la respuesta del cargo
        if (chargeResult.success) {
          this.alertService.success('Éxito', 'Pago procesado exitosamente');
          this.router.navigate(['/dashboard']);
        } else {
          this.alertService.error(
            chargeResult.message || 'Error al procesar el pago'
          );
        }
      }
    } catch (error: any) {
      console.error('Error en createMethodPaymentWithCharge:', error);
      this.alertService.error(
        'Error al procesar el pago: ' +
          (error.error?.message || error.message || 'Error desconocido')
      );
    } finally {
      this.processingPayment = false;
    }
  }

  private async handle3DSAuth(paymentResult: any) {
    try {
      const auth = await firstValueFrom(
        this.n1coPaymentService.handle3DSAuth(paymentResult.authentication.url)
      );

      if (auth.success) {
        const validationResult = await firstValueFrom(
          this.n1coPaymentService.validatePayment(paymentResult.orderId)
        );

        if (validationResult.success) {
          this.alertService.success('Éxito', 'Pago procesado exitosamente');
          this.router.navigate(['/dashboard']);
        } else {
          this.alertService.error('Error en la validación del pago');
        }
      } else {
        this.alertService.error('Error en la autenticación del pago');
      }
    } catch (error: any) {
      this.alertService.error(
        'Error en la autenticación: ' + (error.message || '')
      );
    }
  }

  public backToHome() {
    this.router.navigate(['/']);
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
      // Esperar 3 segundos para que el usuario vea la confirmación (check) en el iframe
      // console.log('Esperando para mostrar confirmación en el modal...');
      await new Promise((resolve) => setTimeout(resolve, 500));

      // Cerrar el modal después de mostrar la confirmación
      this.mostrar3DSModal = false;

      // Esperar 4 segundos antes de procesar el pago
      // console.log('Esperando 4 segundos antes de procesar el pago...');
      await new Promise((resolve) => setTimeout(resolve, 100));

      try {
        // Procesar el pago usando los datos del mensaje
        // console.log('Procesando pago después del delay...');
        const response = await firstValueFrom(
          this.n1coPaymentService.processDirectPayment3DS({
            authentication_id: messageData.authenticationId,
            order_id: messageData.orderId,
          })
        );

        if (response.success) {
          this.alertService.success('Éxito', 'Pago procesado exitosamente');
          this.n1coPaymentService.setPaymentResponse(response);
          this.router.navigate(['/pago-exitoso']);
        } else {
          this.alertService.error('Error al procesar el pago');
        }

        this.processingPayment = false;
      } catch (error: any) {
        console.error('Error procesando pago 3DS:', error);
        this.alertService.error(
          'Error al procesar el pago: ' +
            (error.error?.message || error.message || 'Error desconocido')
        );
        this.processingPayment = false;
      }
    }
    // Manejar autenticación fallida
    else if (
      messageData.messageType === 'authentication.failed' &&
      messageData.status === 'FAILED'
    ) {
      // Detener el polling ya que recibimos el mensaje directo del iframe
      this.stopAuthCheck();
      // console.log('❌ Autenticación fallida, actualizando estado en backend...');

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
        this.processingPayment = false;

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
        this.processingPayment = false;

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

  abrirModal3DS() {
    this.mostrar3DSModal = true;
    this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
      'https://front-3ds-sandbox.n1co.com/authentication/test'
    );
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
}
