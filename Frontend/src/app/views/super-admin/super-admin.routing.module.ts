import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';

import { EmpresasComponent }     from '@views/super-admin/empresas/empresas.component';
import { AdminUsuariosComponent }     from '@views/super-admin/usuarios/admin-usuarios.component';
import { DashboardsComponent }     from '@views/super-admin/dashboards/dashboards.component';
import { DashboardComponent }     from '@views/super-admin/dashboards/dashboard/dashboard.component';

const routes: Routes = [
  {
    path: 'admin',
    component: LayoutComponent,
    children: [
        { path: 'empresas', component: EmpresasComponent, title: 'Empresas' },
        { path: 'usuarios', component: AdminUsuariosComponent, title: 'Usuarios' },
        { path: 'dashboards', component: DashboardsComponent, title: 'Dashboards' },
        { path: 'dashboard/:id', component: DashboardComponent, title: 'Dashboard' },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SuperAdminRoutingModule { }
