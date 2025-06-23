import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, Router } from '@angular/router';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { AuthorizationService } from '@services/Authorization/authorization.service';

@Injectable({
  providedIn: 'root'
})
export class AuthorizationGuard implements CanActivate {

  constructor(
    private authorizationService: AuthorizationService,
    private router: Router
  ) { }

  canActivate(route: ActivatedRouteSnapshot): Observable<boolean> {
    const authType = route.data['authorizationType'];
    const authData = route.data['authorizationData'] || {};

    if (!authType) {
      return of(true); // No requiere autorización
    }

    return this.authorizationService.checkRequirement(authType, authData).pipe(
      map((response : any) => {
        if (response.requires_authorization) {
          // Redirigir a página de autorización pendiente o mostrar modal
          this.router.navigate(['/authorization-required'], {
            queryParams: { type: authType }
          });
          return false;
        }
        return true;
      }),
      catchError(() => of(true)) // En caso de error, permitir acceso
    );
  }
}