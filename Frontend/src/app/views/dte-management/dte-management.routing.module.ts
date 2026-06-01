import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { DteManagementComponent } from './dte-management.component';
import { EmailAccountsComponent } from './email-accounts/email-accounts.component';
import { SyncDashboardComponent } from './sync-dashboard/sync-dashboard.component';
import { DteInboxComponent } from './dte-inbox/dte-inbox.component';
import { DteDetailComponent } from './dte-detail/dte-detail.component';
import { FuncionalidadGuard, SLUG_DESCARGA_AUTOMATIZADA_DTES } from '@guards/funcionalidad.guard';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'DTEs',
    canActivate: [FuncionalidadGuard],
    data: { funcionalidadSlug: SLUG_DESCARGA_AUTOMATIZADA_DTES },
    children: [
      {
        path: 'dte-management',
        component: DteManagementComponent,
        children: [
          { path: '', redirectTo: 'cuentas', pathMatch: 'full' },
          { path: 'cuentas', component: EmailAccountsComponent, title: 'Cuentas de correo' },
          { path: 'dashboard', component: SyncDashboardComponent, title: 'Dashboard DTEs' },
          { path: 'dtes', component: DteInboxComponent, title: 'Bandeja de DTEs' },
          { path: 'dtes/:id', component: DteDetailComponent, title: 'Detalle DTE' }
        ]
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DteManagementRoutingModule {}
