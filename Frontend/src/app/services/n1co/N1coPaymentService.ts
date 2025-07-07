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
      
      createPaymentMethod(data: any): Observable<any> {
        
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

    updateMethodPayment(data: any): Observable<any> {
        const url = `${this.apiUrl}/payment/update-method-payment`;

        const headers = new HttpHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        });
    
        const options = {
            headers: headers,
            withCredentials: true
        };
    

        return this.http.post(url, data, options);
    }
  
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

      createChargewithMethodPayment(data: {
        metodo_pago_id: number;
        id_usuario: number;
        empresa_id: number;
        plan_id: number;
        amount: number;
        customer_name: string;
        customer_email: string;
        customer_phone: string;
        description?: string;
    }): Observable<any> {
        return this.http.post(`${this.apiUrl}/payment/process-ready`, data);
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

    getExistingPaymentMethod(userId: number): Observable<any> {
        return this.http.get(`${this.apiUrl}/payment/methods/${userId}`)
          .pipe(
            catchError(error => {
              console.error('Error al obtener método de pago:', error);
              return throwError(error);
            })
          );
      }
      
      // Método para procesar pago con método existente
      processChargeWithExistingMethod(paymentData: any): Observable<any> {
        return this.http.post(`${this.apiUrl}/payment/process-ready`, paymentData)
          .pipe(
            catchError(error => {
              console.error('Error al procesar pago con método existente:', error);
              return throwError(error);
            })
          );
      }
              

      
  }

  