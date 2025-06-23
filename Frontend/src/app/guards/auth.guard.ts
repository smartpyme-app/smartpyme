import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable()
export class AuthGuard implements CanActivate {

	constructor(private router: Router, private apiService: ApiService){}

	canActivate(
		next: ActivatedRouteSnapshot,
		state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
			if(this.apiService.autenticated())
		        return true;

			localStorage.setItem('returnUrl', state.url);
		    this.router.navigate(['login']);
		    return false;
	}
}
