import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SuperAdminGuard } from '@guards/super-admin.guard';
import { LayoutComponent } from '@layout/layout.component';


const routes: Routes = [
  {
    path: 'admin',
    component: LayoutComponent,
    canActivate: [SuperAdminGuard],
    children: [
        { 
          path: 'empresas', 
          loadComponent: () => import('@views/super-admin/empresas/empresas.component').then(m => m.EmpresasComponent), 
          title: 'Empresas' 
        },
        { 
          path: 'empresa/:id', 
          loadComponent: () => import('@views/super-admin/empresas/empresa/crear-empresa.component').then(m => m.CrearEmpresaComponent), 
          title: 'Empresa' 
        },
        { 
          path: 'licencias', 
          loadComponent: () => import('@views/super-admin/licencias/licencias.component').then(m => m.LicenciasComponent), 
          title: 'Licencias' 
        },
        { 
          path: 'licencia/:id', 
          loadComponent: () => import('@views/super-admin/licencias/licencia/licencia.component').then(m => m.LicenciaComponent), 
          title: 'Licencia' 
        },
        { 
          path: 'usuarios', 
          loadComponent: () => import('@views/super-admin/usuarios/admin-usuarios.component').then(m => m.AdminUsuariosComponent), 
          title: 'Usuarios' 
        },
        { 
          path: 'dashboards', 
          loadComponent: () => import('@views/super-admin/dashboards/dashboards.component').then(m => m.DashboardsComponent), 
          title: 'Dashboards' 
        },
        { 
          path: 'dashboard/:id', 
          loadComponent: () => import('@views/super-admin/dashboards/dashboard/dashboard.component').then(m => m.DashboardComponent), 
          title: 'Dashboard' 
        },
        { 
          path: 'planes', 
          loadComponent: () => import('@views/super-admin/planes/admin-planes.component').then(m => m.AdminPlanesComponent), 
          title: 'Planes' 
        },
        { 
          path: 'pagos', 
          loadComponent: () => import('@views/super-admin/pagos/admin-pagos.component').then(m => m.AdminPagosComponent), 
          title: 'Planes' 
        },
        { 
          path: 'sucursales', 
          loadComponent: () => import('./sucursales/admin-sucursales.component').then(m => m.AdminSucursalesComponent), 
          title: 'Sucursales' 
        },
        { 
          path: 'sucursal/:id', 
          loadComponent: () => import('./sucursales/sucursal/admin-sucursal.component').then(m => m.AdminSucursalComponent), 
          title: 'Sucursal' 
        },
        { 
          path: 'suscripciones', 
          loadComponent: () => import('./suscripciones/admin-suscripciones.component').then(m => m.AdminSuscripcionesComponent), 
          title: 'Suscripciones' 
        },
        { 
          path: 'funcionalidades', 
          loadComponent: () => import('@views/super-admin/funcionalidades/empresas-funcionalidades.component').then(m => m.EmpresasFuncionalidadesComponent), 
          title: 'Funcionalidades' 
        },
        { 
          path: 'roles-permisos', 
          loadComponent: () => import('@views/admin/roles-permisos/roles-permisos.component').then(m => m.RolesPermisosComponent), 
          title: 'Roles y permisos'
        },
        { 
          path: 'modulos', 
          loadComponent: () => import('@views/admin/modules/modules.component').then(m => m.ModulesComponent), 
          title: 'Módulos'
        },
        { 
          path: 'modulos/crear', 
          loadComponent: () => import('@views/admin/modules/create/module-form.component').then(m => m.ModuleFormComponent), 
          title: 'Crear módulo'
        },
        { 
          path: 'modulos/editar/:id', 
          loadComponent: () => import('@views/admin/modules/create/module-form.component').then(m => m.ModuleFormComponent), 
          title: 'Editar módulo'
        }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SuperAdminRoutingModule { }
