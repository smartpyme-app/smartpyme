import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable({
  providedIn: 'root'
})
export class FacturacionVersionGuard implements CanActivate {

    constructor(private router: Router, private apiService: ApiService) {}

    canActivate(
        next: ActivatedRouteSnapshot,
        state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
        
        // Si la ruta es 'venta/crear', verificar la configuración
        if (state.url.includes('venta/crear') && !state.url.includes('ventas-v2')) {
            const empresa = this.apiService.auth_user()?.empresa;
            
            // Obtener la configuración de versión de facturación
            let versionFacturacion = 'original';
            if (empresa?.custom_empresa?.configuraciones?.version_facturacion) {
                versionFacturacion = empresa.custom_empresa.configuraciones.version_facturacion;
            }
            
            // Si está configurada la versión v2, redirigir
            if (versionFacturacion === 'v2') {
                this.router.navigate(['/ventas-v2/crear']);
                return false;
            }
        }
        
        return true;
    }
}

