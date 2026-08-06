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

    const token = this.apiService.auth_token();

    if (!token) {
      return next.handle(req);
    }

    // Preservar headers existentes; no forzar Accept: application/json en blobs
    // (wantsJson + proxies pueden devolver JSON/HTML guardado como .xlsx).
    let headers = req.headers.set('Authorization', 'Bearer ' + token);
    if (req.responseType !== 'blob') {
      headers = headers.set('Accept', 'application/json');
    }

    return next.handle(req.clone({ headers }));
  }
}
