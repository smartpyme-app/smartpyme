import { Injectable } from '@angular/core';
import { Router, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable({
  providedIn: 'root'
})
export class SuperAdminGuard  {

    constructor(private router: Router, private apiService: ApiService){}

    canActivate(
    next: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
        
        // Verificar que el usuario esté autenticado
        if (!this.apiService.autenticated()) {
            this.router.navigate(['/login']);
            return false;
        }

        if (this.apiService.verifyRoleAdmin()) {
            return true;
        }

        this.router.navigate(['/']);
        return false;
  }
}
