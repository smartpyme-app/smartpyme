import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';

import { EmpresasComponent }     from '@views/super-admin/empresas/empresas.component';
import { CrearEmpresaComponent }     from '@views/super-admin/empresas/empresa/crear-empresa.component';
import { LicenciasComponent }     from '@views/super-admin/licencias/licencias.component';
import { LicenciaComponent }     from '@views/super-admin/licencias/licencia/licencia.component';
import { AdminUsuariosComponent }     from '@views/super-admin/usuarios/admin-usuarios.component';
import { DashboardsComponent }     from '@views/super-admin/dashboards/dashboards.component';
import { DashboardComponent }     from '@views/super-admin/dashboards/dashboard/dashboard.component';
import { AdminFacturacionesComponent } from './facturaciones/admin-facturaciones.component';
import { AdminFacturacionComponent } from './facturaciones/facturacion/admin-facturacion.component';
import { AdminSucursalesComponent } from './sucursales/admin-sucursales.component';
import { AdminSucursalComponent } from './sucursales/sucursal/admin-sucursal.component';


const routes: Routes = [
  {
    path: 'admin',
    component: LayoutComponent,
    children: [
        { path: 'empresas', component: EmpresasComponent, title: 'Empresas' },
        // { path: 'empresa/crear', component: CrearEmpresaComponent, title: 'Empresa' },
        { path: 'empresa/:id', component: CrearEmpresaComponent, title: 'Empresa' },
        { path: 'licencias', component: LicenciasComponent, title: 'Licencias' },
        { path: 'licencia/:id', component: LicenciaComponent, title: 'Licencia' },
        { path: 'usuarios', component: AdminUsuariosComponent, title: 'Usuarios' },
        { path: 'dashboards', component: DashboardsComponent, title: 'Dashboards' },
        { path: 'dashboard/:id', component: DashboardComponent, title: 'Dashboard' },

        { path: 'sucursales', component: AdminSucursalesComponent, title: 'Sucursales' },
        { path: 'sucursal/:id', component: AdminSucursalComponent, title: 'Sucursal' },

        { path: 'facturaciones', component: AdminFacturacionesComponent, title: 'Facturacion' },
        { path: 'facturacion/crear', component: AdminFacturacionComponent, title: 'Facturacion' },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SuperAdminRoutingModule { }
