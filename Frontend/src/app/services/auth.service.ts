import { Injectable, inject, Injector } from '@angular/core';
import { HttpResponse } from '@angular/common/http';
import { map, switchMap, tap } from 'rxjs/operators';
import { Observable, of } from 'rxjs';
import { HttpService } from '@services/http.service';
import { PermissionService } from '@services/permission.service';
import { ConstantsService } from '@services/constants.service';
import { CountryI18nService } from '@services/country-i18n.service';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private injector = inject(Injector);

  constructor(
    private httpService: HttpService,
    private permissionService: PermissionService,
    private constantsService: ConstantsService
  ) {}

  login(user: any): Observable<any> {
    return this.httpService.store('login', user).pipe(
      switchMap((response: HttpResponse<any>) => {
        const data: any = response;
        if (!data.token || !data.user) {
          return of(data);
        }
        localStorage.setItem('SP_token', JSON.stringify(data.token));
        localStorage.setItem('SP_auth_user', JSON.stringify(data.user));
        return this.injector
          .get(CountryI18nService)
          .applyForEmpresa(data.user.empresa)
          .pipe(map(() => data));
      }),
      tap((data: any) => {
        if (data?.token && data?.user) {
          this.permissionService.loadUserPermissions(data.user.id);
          this.loadConstants();
        }
      })
    );
  }

  register(user: any): Observable<any> {
    return this.httpService.store('register', user).pipe(
      map((response: HttpResponse<any>) => {
        let data: any = response;
        if (data) {
          localStorage.setItem('SP_user_register', JSON.stringify(data));
        }
        return data;
      })
    );
  }

  logout(): void {
    let data: any = {};
    if (this.autenticated()) {
      data.usuario_id = this.auth_user().id;
      this.httpService.store('logout', data).subscribe(
        () => {},
        error => {
          console.error('Error en logout:', error);
        }
      );
    }
    localStorage.clear();
    this.permissionService.clearPermissions();
    this.injector.get(CountryI18nService).applyForEmpresa(null).subscribe();
  }

  autenticated(): boolean {
    const token = localStorage.getItem('SP_token');
    if (!token) {
      return false;
    }
    try {
      const parsedToken = JSON.parse(token);
      return !!parsedToken;
    } catch (error) {
      return false;
    }
  }

  auth_user(): any {
    const user = localStorage.getItem('SP_auth_user');
    return user ? JSON.parse(user) : null;
  }

  register_user(): any {
    const user = localStorage.getItem('SP_user_register');
    return user ? JSON.parse(user) : null;
  }

  auth_token(): string {
    const token = localStorage.getItem('SP_token');
    return token ? JSON.parse(token) : '';
  }

  private loadConstants(): void {
    this.constantsService.loadConstants().subscribe(
      (constants) => {
        localStorage.setItem('SP_constants', JSON.stringify(constants));
      },
      (error) => {
        console.error('Error cargando constantes:', error);
      }
    );
  }
}

