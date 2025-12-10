import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpErrorResponse } from '@angular/common/http';
import { map, catchError, retry, timeout } from 'rxjs/operators';
import { Observable, throwError } from 'rxjs';
import { environment } from './../../environments/environment';
import { AlertService } from '@services/alert.service';

@Injectable({
  providedIn: 'root'
})
export class HttpService {
  public appUrl: string = environment.APP_URL;
  public baseUrl: string = environment.API_URL;
  public apiUrl = this.baseUrl + '/api/';

  constructor(
    private http: HttpClient,
    private alertService: AlertService
  ) {}

  private getAuthToken(): string {
    const token = localStorage.getItem('SP_token');
    return token ? JSON.parse(token) : '';
  }

  getToUrl(url: string): Observable<any> {
    return this.http.get<any>(url).pipe(retry(0), catchError(this.handleError));
  }

  getAll(url: string, filtros: any = {}): Observable<any> {
    return this.http
      .get<any>(this.apiUrl + url, { params: filtros })
      .pipe(retry(0), catchError(this.handleError));
  }

  read(url: string, id: number): Observable<any> {
    return this.http
      .get<any>(this.apiUrl + url + id)
      .pipe(retry(0), catchError(this.handleError));
  }

  filter(url: string, filter: any): Observable<any> {
    return this.http
      .get<any>(this.apiUrl + url + filter)
      .pipe(retry(0), catchError(this.handleError));
  }

  get(url: string): Observable<any> {
    return this.http
      .get<any>(this.apiUrl + url)
      .pipe(retry(0), catchError(this.handleError));
  }

  store(url: string, model: any): Observable<any> {
    return this.http
      .post<any>(this.apiUrl + url, model)
      .pipe(retry(0), catchError(this.handleError));
  }

  storeWithTimeout(url: string, model: any, timeoutMs: number = 300000): Observable<any> {
    // Para respuestas grandes, usar 'text' y parsear manualmente para evitar problemas de parsing
    return this.http.post(this.apiUrl + url, model, {
      observe: 'response',
      responseType: 'text' as 'json' // Forzar como texto para parsear manualmente
    }).pipe(
      timeout(timeoutMs),
      retry(0),
      map(response => {
        // Si el status es 200, intentar parsear el texto como JSON
        if (response.status === 200 && response.body) {
          try {
            const bodyText = typeof response.body === 'string' 
              ? response.body 
              : String(response.body);
            const parsed = JSON.parse(bodyText);
            return parsed;
          } catch (e: any) {
            console.error('Error al parsear JSON:', e);
            const bodyText = typeof response.body === 'string' 
              ? response.body 
              : String(response.body);
            console.error('Respuesta recibida (primeros 500 caracteres):', bodyText.substring(0, 500));
            throw new Error('Error al parsear la respuesta del servidor: ' + (e?.message || String(e)));
          }
        }
        return response.body;
      }),
      catchError(this.handleError)
    );
  }

  update(url: string, id: number, model: any): Observable<any> {
    return this.http
      .put<any>(`${this.apiUrl}${url}/${id}`, model)
      .pipe(retry(0), catchError(this.handleError));
  }

  delete(url: string, id: number): Observable<any> {
    return this.http
      .delete<any>(this.apiUrl + url + id)
      .pipe(retry(0), catchError(this.handleError));
  }

  paginate(url: string, filtros: any = {}): Observable<any> {
    return this.http
      .get<any>(url, { params: filtros })
      .pipe(retry(0), catchError(this.handleError));
  }

  upload(url: string, formData: any): Observable<any> {
    let headers = new HttpHeaders();
    headers.append('Accept', 'application/json');
    const token = this.getAuthToken();
    if (token) {
      headers.append('Authorization', 'Bearer ' + token);
    }
    let options = { headers };
    return this.http
      .post(this.apiUrl + url, formData, options)
      .pipe(retry(0), catchError(this.handleError));
  }

  export(url: string, filtros: any): Observable<Blob> {
    return this.http.get(this.apiUrl + url, { responseType: 'blob', params: filtros });
  }

  exportWithUrl(url: string, filtros: any): Observable<any> {
    return this.http.get(this.apiUrl + url, { params: filtros });
  }

  exportAcumulado(url: string, filtros: any): Observable<Blob> {
    return this.http.post(this.apiUrl + url, filtros, {
      responseType: 'blob',
      headers: new HttpHeaders({
        'Authorization': 'Bearer ' + this.getAuthToken()
      }),
    });
  }

  exportAcumuladoReportes(url: string, filtros: any): Observable<Blob> {
    return this.http.post(this.apiUrl + url, filtros, {
      responseType: 'blob',
      observe: 'response',
      headers: new HttpHeaders({
        'Authorization': 'Bearer ' + this.getAuthToken()
      })
    }).pipe(
      map(response => {
        return new Blob([response.body!], {
          type: response.headers.get('Content-Type') || 'application/octet-stream'
        });
      })
    );
  }

  download(url: string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}${url}`, {
      responseType: 'blob',
      headers: new HttpHeaders({
        Authorization: 'Bearer ' + this.getAuthToken()
      })
    }).pipe(
      map((response) => {
        return new Blob([response]);
      }),
      catchError((error) => {
        console.error('Error al descargar el archivo:', error);
        return throwError(() => error);
      })
    );
  }

  generatePayrollSlips(planillaId: number): Observable<Blob> {
    return this.http
      .get(`${this.apiUrl}planillas/${planillaId}/boletas`, {
        responseType: 'blob',
      })
      .pipe(
        map((response) => {
          return new Blob([response], { type: 'application/pdf' });
        }),
        catchError((error) => {
          console.error('Error downloading payroll slips:', error);
          return throwError(() => error);
        })
      );
  }

  generateIndividualPayrollSlip(detalleId: number): Observable<Blob> {
    return this.http
      .get(`${this.apiUrl}planillas/detalles/${detalleId}/boleta`, {
        responseType: 'blob',
      })
      .pipe(
        map((response) => {
          return new Blob([response], { type: 'application/pdf' });
        }),
        catchError((error) => {
          console.error('Error downloading payroll slip:', error);
          return throwError(() => error);
        })
      );
  }

  getUserData(userId: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}me/${userId}`).pipe(
      map((response: any) => {
        if (response && response.user) {
          localStorage.setItem('SP_auth_user', JSON.stringify(response.user));
          return response.user;
        }
        return null;
      }),
      catchError(this.handleError)
    );
  }

  getActividadesEconomicas(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}actividades-economicas/excel`).pipe(
      map((response: any) => {
        return response;
      }),
      catchError(this.handleError)
    );
  }

  getModules(): Observable<any> {
    return this.http
      .get<any>(this.apiUrl + 'modules')
      .pipe(retry(0), catchError(this.handleError));
  }

  private handleError(error: HttpErrorResponse): Observable<never> {
    return throwError(() => error);
  }
}

