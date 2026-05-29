import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpHeaders } from '@angular/common/http';
import { ApiService } from './../services/api.service';

@Injectable()
export class CacheInterceptor implements HttpInterceptor {

  constructor(
    private apiService: ApiService
  ){}

  intercept(req: HttpRequest<any>, next: HttpHandler) {
    const saltarJWT = req.params.get('saltarJWT'); 
    if (saltarJWT) {                                
      return next.handle(req);                      
    }                                               

    let token = this.apiService.auth_token();
    
    const httpRequest = req.clone({
      headers: new HttpHeaders({
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache',
        'Expires': 'Sat, 01 Jan 2000 00:00:00 GMT',
        'Content-Type':  'application/json',
        'Authorization': 'Bearer ' + token
      })
    });

    return next.handle(httpRequest);
  }
}