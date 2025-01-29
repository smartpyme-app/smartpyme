import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable,throwError  } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
@Injectable({
    providedIn: 'root'
  })
  export class N1coPaymentService {
      private paymentResponse: any = null;
      private apiUrl = environment.API_URL + '/api';
  
      constructor(private http: HttpClient) {}
  
      // Crear método de pago (payment method)
      
      createPaymentMethod(data: any): Observable<any> {
        console.log('Datos enviados al backend:', data); // Añade este log
        
        const headers = new HttpHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        });
    
        const options = {
            headers: headers,
            withCredentials: true
        };
    
        const url = `${this.apiUrl}/payment/method`;
        
        return this.http.post(url, data, options);
    }
  
      // Procesar el cargo usando el payment method
      createMethodPaymentWithCharge(data: {
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

      processDirectPayment3DS(data: {
        authentication_id: string;
        order_id: string;
    }): Observable<any> {
        const url = `${this.apiUrl}/payment/process/3ds`;
    
        return this.http.post(url, data, {
            headers: new HttpHeaders({
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }),
            withCredentials: true
        });
    }

      // Validar el pago (especialmente después de 3DS)
      validatePayment(paymentId: string): Observable<any> {
          return this.http.get(`${this.apiUrl}/payment/validate/${paymentId}`);
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

      checkAuthenticationStatus(data: {
        authentication_id: string;
        order_id: string;
    }): Observable<any> {
        return this.http.post(`${this.apiUrl}/payment/check-auth-status`, data)
            .pipe(
                catchError(error => {
                    console.error('Error checking authentication status:', error);
                    return throwError(() => new Error('Error verificando el estado de autenticación'));
                })
            );
    }

    setPaymentResponse(response: any) {
        this.paymentResponse = response;
    }

    getPaymentResponse() {
        return this.paymentResponse;
    }

    clearPaymentResponse() {
        this.paymentResponse = null;
    }

      
  }

  