import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpHeaders } from '@angular/common/http';
import { ApiService } from './../services/api.service';

@Injectable()
export class JwtInterceptor  implements HttpInterceptor {

  constructor(
    private apiService: ApiService
  ){}

  intercept(req: HttpRequest<any>, next: HttpHandler) {
    let token = this.apiService.auth_token();
    if(token) {
      const httpRequest = req.clone({
        headers: new HttpHeaders({
          'Accept':  'application/json',
          'Authorization': 'Bearer ' + token
        })
      });
      return next.handle(httpRequest);
    }else{
      return next.handle(req);
    }

  }
}