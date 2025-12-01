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
  templateUrl: './pago.component.html'
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
        cvv: ''
    };

    public billingInfo = {
        countryCode: '',
        stateCode: '',
        zipCode: ''
    };


    public processingPayment = false;
    public mostrar3DSModal = false;
    public urlAutenticacion!: SafeResourceUrl;
    public estadoSeleccionado: any = null;
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
        
    ) { }

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
        const tipoPlan = this.user.empresa.tipo_plan;
        const totalActual = this.user.empresa.total || 0;
        
        // Calcular precio original basado en el plan
        let precioOriginal = 0;
        
        // El plan puede venir como número o como string (nombre del plan)
        const planNumero = typeof plan === 'number' ? plan : parseInt(plan);
        const planNombre = typeof plan === 'string' ? plan.toLowerCase() : '';
        
        // Verificar por número primero
        if (planNumero == 1) { // Emprendedor
            precioOriginal = tipoPlan === 'Mensual' ? 16.95 : 203.4;
        } else if (planNumero == 2) { // Estándar
            precioOriginal = tipoPlan === 'Mensual' ? 28.25 : 339;
        } else if (planNumero == 3) { // Avanzado
            precioOriginal = tipoPlan === 'Mensual' ? 56.5 : 678;
        } else if (planNumero == 4) { // Pro
            precioOriginal = tipoPlan === 'Mensual' ? 113 : 1220;
        } else {
            // Si no coincide con ningún número, buscar por nombre del plan
            if (planNombre.includes('estándar') || planNombre.includes('estandar') || planNombre === 'estándar' || planNombre === 'estandar') {
                precioOriginal = tipoPlan === 'Mensual' ? 28.25 : 339;
            } else if (planNombre.includes('avanzado') || planNombre === 'avanzado') {
                precioOriginal = tipoPlan === 'Mensual' ? 56.5 : 678;
            } else if (planNombre.includes('pro') || planNombre === 'pro') {
                precioOriginal = tipoPlan === 'Mensual' ? 113 : 1220;
            } else if (planNombre.includes('emprendedor') || planNombre === 'emprendedor') {
                precioOriginal = tipoPlan === 'Mensual' ? 16.95 : 203.4;
            }
        }
        
        this.totalOriginal = precioOriginal;
        
        // Verificar si hay descuento usando el servicio promocional
        const codigoPromocional = this.user.empresa?.codigo_promocional || 
                                  this.route.snapshot.queryParamMap.get('promo');
        
        if (codigoPromocional && this.promocionalService.esCodigoValido(codigoPromocional)) {
            const codigoPromo = this.promocionalService.obtenerCodigoPromocional(codigoPromocional);
            if (codigoPromo && precioOriginal > 0) {
                this.tieneDescuento = true;
                this.totalOriginal = precioOriginal;
            }
        } else if (precioOriginal > 0 && totalActual > 0 && totalActual < precioOriginal) {
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
                this.alertService.error('El número de tarjeta debe tener entre 13 y 16 dígitos');
                return;
            }
    
            // Formatear el mes con padding si es necesario
            const expirationMonth = this.paymentData.expirationMonth.padStart(2, '0');
            
            const paymentMethodData = {
                customer: {
                    id: this.user.id.toString(),
                    name: this.user.name,
                    email: this.user.email,
                    phoneNumber: this.user.telefono || ''
                },
                card: {
                    number: cardNumber,
                    cardHolder: this.paymentData.cardHolder.trim(),
                    expirationMonth: expirationMonth,
                    expirationYear: this.paymentData.expirationYear,
                    cvv: this.paymentData.cvv
                },
                plan:{
                    id_plan: this.user.plan_id,
                    plan_name: this.user.plan,

                },
                billingInfo: {
                    countryCode: this.billingInfo.countryCode,
                    stateCode: this.billingInfo.stateCode,
                    zipCode: this.billingInfo.zipCode
                }
            };
    
            const result = await firstValueFrom(
                this.n1coPaymentService.createPaymentMethod(paymentMethodData)
            );

            if (result.requires_3ds) {
                console.log('Resultado 3DS:', result);
                this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl(
                    result.authentication_url
                );
                this.mostrar3DSModal = true;
                
                const checkAuthentication = async () => {
                    try {
                        const authStatus = await firstValueFrom(
                            this.n1coPaymentService.checkAuthenticationStatus({
                                authentication_id: result.authentication_id,
                                order_id: result.order_id
                            })
                        );
            
                        // Usar los estados definidos en tus constantes
                        switch (authStatus.estado) {
                            case 'autenticacion_exitosa':
                                this.mostrar3DSModal = false;
                                const response = await firstValueFrom(
                                    this.n1coPaymentService.processDirectPayment3DS({
                                        authentication_id: result.authentication_id,
                                        order_id: result.order_id
                                    })
                                );
                                
                                if (response.success) {
                                    this.alertService.success('Éxito', 'Pago procesado exitosamente');
                                    this.n1coPaymentService.setPaymentResponse(response);
                                    this.router.navigate(['/pago-exitoso']);
                                }
                                return true;
            
                            case 'autenticacion_rechazada':
                            case 'autenticacion_cancelada':
                            case 'autenticacion_fallida':
                                this.mostrar3DSModal = false;
                                this.alertService.error(`La autenticación ha fallado, intentalo nuevamente o contacta con nosotros`);
                                return true;
            
                            case 'autenticacion_pendiente':
                                return false;
            
                            default:
                                return false;
                        }
                    } catch (error) {
                        console.error('Error verificando autenticación:', error);
                        this.alertService.error('Error verificando el estado de la autenticación');
                        this.mostrar3DSModal = false;
                        return true;
                    }
                };
            
                // Esperar 10 segundos antes de empezar a verificar
                setTimeout(async () => {
                    const interval = setInterval(async () => {
                        const shouldStop = await checkAuthentication();
                        if (shouldStop) {
                            clearInterval(interval);
                            this.processingPayment = false;
                        }
                    }, 3000);
            
                    // Tiempo máximo de espera
                    setTimeout(() => {
                        clearInterval(interval);
                        if (this.mostrar3DSModal) {
                            this.mostrar3DSModal = false;
                            this.alertService.error('El tiempo de autenticación ha expirado');
                            this.processingPayment = false;
                        }
                    }, 120000);
                }, 10000);
                
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
                    description: `Pago plan ${this.user.empresa.plan.nombre}`
                };
    
                const chargeResult = await firstValueFrom(
                    this.n1coPaymentService.createMethodPaymentWithCharge(chargeData)
                );
    
                // Manejar la respuesta del cargo
                if (chargeResult.success) {
                    this.alertService.success('Éxito', 'Pago procesado exitosamente');
                    this.router.navigate(['/dashboard']);
                } else {
                    this.alertService.error(chargeResult.message || 'Error al procesar el pago');
                }
            }
    
        } catch (error: any) {
            console.error('Error en createMethodPaymentWithCharge:', error);
            this.alertService.error('Error al procesar el pago: ' + 
                (error.error?.message || error.message || 'Error desconocido'));
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
            this.alertService.error('Error en la autenticación: ' + (error.message || ''));
        }
    }

    public backToHome() {
        this.router.navigate(['/']);
    }

    abrirModal3DS() {
        this.mostrar3DSModal = true;
        this.urlAutenticacion = this.sanitizer.bypassSecurityTrustResourceUrl('https://front-3ds-sandbox.n1co.com/authentication/test');
      }

      getPaises() {
        this.apiService.getAll('paises-suscripcion', this.paises).subscribe(paises => { 
          this.paises = paises;
        }, error => {this.alertService.error(error); });
      }
    
      getEstados(countryCode: string) {
        this.apiService.getAll(`estados-por-pais/${countryCode}`, []).subscribe(
          estados => { 
            this.estados = estados;
          }, 
          error => {
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
          const estadoSeleccionado = this.estados.find(estado => estado.codigo === this.billingInfo.stateCode);
          
          if (estadoSeleccionado && estadoSeleccionado.codigo_postal) {
            this.billingInfo.zipCode = estadoSeleccionado.codigo_postal;
          }
        }
      }
}