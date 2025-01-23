import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';
import { firstValueFrom } from 'rxjs';

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

    constructor(
        private apiService: ApiService,
        private router: Router,
        private alertService: AlertService,
        private n1coPaymentService: N1coPaymentService
    ) { }

    ngOnInit() {
        this.user = this.apiService.register_user();
    }

    // Método original para checkout N1co
    public checkout() {
        let URL = this.apiService.baseUrl + '/payment/' + this.user.empresa.id;
        window.open(this.user.url_n1co + '/?callbackurl=' + URL, '_self');
    }

    // Nuevo método para procesar pago con tarjeta
    public async processDirectPayment() {
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
                    this.n1coPaymentService.processDirectPayment(chargeData)
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
            console.error('Error en processDirectPayment:', error);
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
}