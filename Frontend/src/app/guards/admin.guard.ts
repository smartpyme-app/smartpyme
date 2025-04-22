import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable()
export class AdminGuard implements CanActivate {

	constructor(private router: Router, private apiService: ApiService){}

  canActivate(
    next: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
  		let user = this.apiService.auth_user()
		const currentUrl = state.url;

		  const adminOnlyRoutes = [
			'/suscripcion',
			'/usuarios',
			'/sucursales',
			'/mi-cuenta'
		  ];
		  
		  // Si la ruta actual es una de las restringidas, solo permitir acceso a administradores
		  if (adminOnlyRoutes.some(route => currentUrl.includes(route))) {
			
			if (user.tipo === 'Administrador') {
			  return true;
			} else {
			  this.router.navigate(['/']);
			  return false;
			}
		  }
		//Supervisor Limitado
		if(user.tipo == 'Administrador' || user.tipo == 'Contador' || user.tipo == 'Supervisor' || user.tipo == 'Supervisor Limitado')
	        return true;
	    
	    this.router.navigate(['/']);
	    return false;
  }
}
