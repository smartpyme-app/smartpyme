import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable()
export class CitasGuard implements CanActivate {

    constructor(private router: Router, private apiService: ApiService){}

    canActivate(
    next: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
        let usuario = this.apiService.auth_user()

        if(usuario.id_empresa != 2 && usuario.tipo == 'Citas')
            return true;
        
        this.router.navigate(['/']);
        return false;
  }
}
