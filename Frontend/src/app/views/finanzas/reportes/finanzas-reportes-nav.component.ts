import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-finanzas-reportes-nav',
  standalone: true,
  imports: [CommonModule, RouterModule],
  template: `
    <div class="d-flex gap-2 flex-wrap">
      @if (apiService.hasPermission('finanzas.reporteria.ver')) {
        <button class="btn" routerLinkActive="btn-primary" [routerLinkActiveOptions]="{exact: true}" routerLink="/finanzas/reportes/cuentas-cobrar">Cuentas por cobrar</button>
        <button class="btn" routerLinkActive="btn-primary" [routerLinkActiveOptions]="{exact: true}" routerLink="/finanzas/reportes/cuentas-pagar">Cuentas por pagar</button>
        <button class="btn" routerLinkActive="btn-primary" [routerLinkActiveOptions]="{exact: true}" routerLink="/finanzas/reportes/antiguedad-cxc">Antigüedad CxC</button>
        <button class="btn" routerLinkActive="btn-primary" [routerLinkActiveOptions]="{exact: true}" routerLink="/finanzas/reportes/antiguedad-cxp">Antigüedad CxP</button>
      }
      @if (apiService.hasPermission('finanzas.libro_iva.ver')) {
        <button class="btn" routerLinkActive="btn-primary" [routerLinkActiveOptions]="{exact: true}" routerLink="/finanzas/reportes/resumen-impuestos">Resumen de impuestos</button>
      }
    </div>
  `,
})
export class FinanzasReportesNavComponent {
  constructor(public apiService: ApiService) {}
}

@Component({
  selector: 'app-finanzas-reportes-redirect',
  standalone: true,
  template: '',
})
export class FinanzasReportesRedirectComponent implements OnInit {
  constructor(
    private apiService: ApiService,
    private router: Router
  ) {}

  ngOnInit(): void {
    const destino = this.apiService.hasPermission('finanzas.reporteria.ver')
      ? '/finanzas/reportes/cuentas-cobrar'
      : '/finanzas/reportes/resumen-impuestos';
    void this.router.navigateByUrl(destino, { replaceUrl: true });
  }
}
