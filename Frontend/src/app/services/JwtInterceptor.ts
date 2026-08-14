import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler } from '@angular/common/http';
import { ApiService } from '@services/api.service';

@Injectable()
export class JwtInterceptor  implements HttpInterceptor {

  constructor(
    private apiService: ApiService
  ){}

  intercept(req: HttpRequest<any>, next: HttpHandler) {

    const saltarJWT = req.params.get('saltarJWT');

    if (saltarJWT) {
        return next.handle(req);
    }

    let token = this.apiService.auth_token();

    if(token) {
      // setHeaders fusiona; no reemplazar headers (p.ej. Idempotency-Key).
      const httpRequest = req.clone({
        setHeaders: {
          'Accept': 'application/json',
          'Authorization': 'Bearer ' + token
        }
      });
      return next.handle(httpRequest);
    }else{
      return next.handle(req);
    }

  }
}
