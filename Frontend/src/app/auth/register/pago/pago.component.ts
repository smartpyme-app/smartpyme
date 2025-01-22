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
            // 1. Primero creamos el payment method
            const paymentMethodData = {
                customer: {
                    name: this.user.nombre,
                    email: this.user.email,
                    phoneNumber: this.user.telefono || ''
                },
                card: {
                    number: this.paymentData.cardNumber.replace(/\s/g, ''),
                    cardHolder: this.paymentData.cardHolder,
                    expirationMonth: this.paymentData.expirationMonth,
                    expirationYear: this.paymentData.expirationYear,
                    cvv: this.paymentData.cvv
                }
            };

            // Crear el payment method
            const paymentMethod = await firstValueFrom(
                this.n1coPaymentService.createPaymentMethod(paymentMethodData)
            );

            if (!paymentMethod.success) {
                throw new Error(paymentMethod.message || 'Error al crear el método de pago');
            }

            // 2. Procesamos el cargo con el payment method creado
            const chargeData = {
                empresa_id: this.user.empresa.id,
                card_id: paymentMethod.id,
                customer_name: this.user.nombre,
                customer_email: this.user.email,
                customer_phone: this.user.telefono || '',
                amount: this.user.empresa.plan.precio,
                description: `Pago plan ${this.user.empresa.plan.nombre}`
            };

            const result = await firstValueFrom(
                this.n1coPaymentService.processDirectPayment(chargeData)
            );

            // Si requiere autenticación 3DS
            if (result.authentication && result.authentication.url) {
                await this.handle3DSAuth(result);
            } else if (result.success) {
                this.alertService.success('Éxito','Pago procesado exitosamente');
                this.router.navigate(['/dashboard']);
            }
        } catch (error: any) {
            this.alertService.error('Error al procesar el pago: ' + (error.message || ''));
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