import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { ApiService } from '@services/api.service';
import { Location } from '@angular/common';

@Injectable({
  providedIn: 'root'
})
export class SupervisorLimitadoGuard implements CanActivate {
  
  constructor(
    private apiService: ApiService, 
    private location: Location
  ) {}
  
  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): boolean {
    
    if (this.apiService.isSupervisorLimitado()) {
      this.location.back();
      return false;
    }

    return true;
  }
}