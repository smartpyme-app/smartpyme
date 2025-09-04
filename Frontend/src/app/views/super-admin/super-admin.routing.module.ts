import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SuperAdminGuard } from '@guards/super-admin.guard';
import { LayoutComponent } from '@layout/layout.component';

import { EmpresasComponent }     from '@views/super-admin/empresas/empresas.component';
import { CrearEmpresaComponent }     from '@views/super-admin/empresas/empresa/crear-empresa.component';
import { LicenciasComponent }     from '@views/super-admin/licencias/licencias.component';
import { LicenciaComponent }     from '@views/super-admin/licencias/licencia/licencia.component';
import { AdminUsuariosComponent }     from '@views/super-admin/usuarios/admin-usuarios.component';
import { AdminPlanesComponent }     from '@views/super-admin/planes/admin-planes.component';
import { AdminPagosComponent }     from '@views/super-admin/pagos/admin-pagos.component';
import { DashboardsComponent }     from '@views/super-admin/dashboards/dashboards.component';
import { DashboardComponent }     from '@views/super-admin/dashboards/dashboard/dashboard.component';
import { AdminSucursalesComponent } from './sucursales/admin-sucursales.component';
import { AdminSucursalComponent } from './sucursales/sucursal/admin-sucursal.component';
import { AdminSuscripcionesComponent } from './suscripciones/admin-suscripciones.component';
import { EmpresasFuncionalidadesComponent } from '@views/super-admin/funcionalidades/empresas-funcionalidades.component';
import { RolesPermisosComponent } from '@views/admin/roles-permisos/roles-permisos.component';
import { ModulesComponent } from '@views/admin/modules/modules.component';
import { ModuleFormComponent } from '@views/admin/modules/create/module-form.component';


const routes: Routes = [
  {
    path: 'admin',
    component: LayoutComponent,
    canActivate: [SuperAdminGuard],
    children: [
        { path: 'empresas', component: EmpresasComponent, title: 'Empresas' },
        // { path: 'empresa/crear', component: CrearEmpresaComponent, title: 'Empresa' },
        { path: 'empresa/:id', component: CrearEmpresaComponent, title: 'Empresa' },
        { path: 'licencias', component: LicenciasComponent, title: 'Licencias' },
        { path: 'licencia/:id', component: LicenciaComponent, title: 'Licencia' },
        { path: 'usuarios', component: AdminUsuariosComponent, title: 'Usuarios' },
        { path: 'dashboards', component: DashboardsComponent, title: 'Dashboards' },
        { path: 'dashboard/:id', component: DashboardComponent, title: 'Dashboard' },
        { path: 'planes', component: AdminPlanesComponent, title: 'Planes' },
        { path: 'pagos', component: AdminPagosComponent, title: 'Planes' },

        { path: 'sucursales', component: AdminSucursalesComponent, title: 'Sucursales' },
        { path: 'sucursal/:id', component: AdminSucursalComponent, title: 'Sucursal' },

        { path: 'suscripciones', component: AdminSuscripcionesComponent, title: 'Suscripciones' },
        { path: 'funcionalidades', component: EmpresasFuncionalidadesComponent, title: 'Funcionalidades' },
        { path: 'roles-permisos', component: RolesPermisosComponent, title: 'Roles y permisos'},
        { path: 'modulos', component: ModulesComponent, title: 'Módulos'},
        { path: 'modulos/crear', component: ModuleFormComponent, title: 'Crear módulo'},
        { path: 'modulos/editar/:id', component: ModuleFormComponent, title: 'Editar módulo'}
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SuperAdminRoutingModule { }
