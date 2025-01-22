import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
@Injectable({
    providedIn: 'root'
  })
  export class N1coPaymentService {
      private apiUrl = environment.API_URL + '/api';
  
      constructor(private http: HttpClient) {}
  
      // Crear método de pago (payment method)
      
createPaymentMethod(data: {
    customer: {
        name: string;
        email: string;
        phoneNumber: string;
    };
    card: {
        number: string;
        expirationMonth: string;
        expirationYear: string;
        cvv: string;
        cardHolder: string;
    };
}): Observable<any> {
    const headers = new HttpHeaders({
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    });

    const options = {
        headers: headers,
        withCredentials: true
    };

    // Asegúrate de que esta es la URL correcta
    const url = `${this.apiUrl}/payment/method`;

    return this.http.post(url, {
        customer: {
            name: data.customer.name,
            email: data.customer.email,
            phoneNumber: data.customer.phoneNumber
        },
        card: {
            number: data.card.number.replace(/\s/g, ''), // Eliminar espacios
            expirationMonth: data.card.expirationMonth,
            expirationYear: data.card.expirationYear,
            cvv: data.card.cvv,
            cardHolder: data.card.cardHolder
        }
    }, options);
}
  
      // Procesar el cargo usando el payment method
      processDirectPayment(data: {
          empresa_id: number;
          card_id: string;
          customer_name: string;
          customer_email: string;
          customer_phone: string;
          amount: number;
          description?: string;
      }): Observable<any> {
          return this.http.post(`${this.apiUrl}/api/payment/process`, data);
      }
  
      // Validar el pago (especialmente después de 3DS)
      validatePayment(paymentId: string): Observable<any> {
          return this.http.get(`${this.apiUrl}/api/payment/validate/${paymentId}`);
      }
  
      handle3DSAuth(authUrl: string): Observable<any> {
          return new Observable(observer => {
              const popup = window.open(authUrl, '3DS Auth', 'width=600,height=600');
              
              const checkPopupClosed = setInterval(() => {
                  if (popup?.closed) {
                      clearInterval(checkPopupClosed);
                      observer.next({ success: true });
                      observer.complete();
                  }
              }, 500);
          });
      }
  }