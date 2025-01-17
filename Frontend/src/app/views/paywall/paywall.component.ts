// paywall.component.ts
import { Component, OnInit, Input } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../../environments/environment';

interface PaymentLink {
  orderCode: string;
  orderId: number;
  paymentLinkUrl: string;
}

@Component({
  selector: 'app-paywall',
  templateUrl: './paywall.component.html',
  styleUrls: ['./paywall.component.css']
})
export class PaywallComponent implements OnInit {
  @Input() planName: string = '';
  @Input() price: number = 0;
  @Input() currencySymbol: string = '$';
  @Input() planFeatures: string[] = [];
  
  loading: boolean = false;
  
  constructor(private http: HttpClient) {}

  ngOnInit() {
    // Cargar datos iniciales si es necesario
  }

  renewSubscription() {
    console.log('Renovación de suscripción');
  }

  // async renewSubscription() {
  //   try {
  //     this.loading = true;

  //     const paymentData = {
  //       orderName: `Renovación Plan ${this.planName}`,
  //       orderDescription: `Renovación de suscripción - Plan ${this.planName}`,
  //       amount: this.price,
  //       successUrl: `${window.location.origin}/subscription/success`,
  //       cancelUrl: `${window.location.origin}/subscription/cancel`,
  //       metadata: [
  //         {
  //           name: "subscriptionRenewal",
  //           value: "true"
  //         }
  //       ]
  //     };

      // Crear enlace de pago con N1CO
      // const headers = new HttpHeaders({
      //   'Authorization': `Bearer ${environment.API_KEY}`,
      //   'Content-Type': 'application/json'
      // });

      // const response = await this.http.post<PaymentLink>(
      //   `${environment.n1coApiUrl}/paymentlink/checkout`,
      //   paymentData,
      //   { headers }
      // ).toPromise();

      // Redirigir al usuario al enlace de pago
    // if (response && response.paymentLinkUrl) {
    //   window.location.href = response.paymentLinkUrl;
    // }

    // } catch (error) {
    //   console.error('Error al procesar el pago:', error);
    //   // Manejar el error apropiadamente
    // } finally {
    //   this.loading = false;
    // }
  // }

}