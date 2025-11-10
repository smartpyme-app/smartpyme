import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';
import { PermissionGuard } from '@guards/permission.guard';

const routes: Routes = [
  {
    path: 'organizacion',
    component: LayoutComponent,
    children: [
      {
        path: 'empresas',
        loadComponent: () => import('@views/organizaciones-admin/empresas/organizacion-empresas.component').then(m => m.OrganizacionEmpresasComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.empresas.ver' },
        title: 'Empresas',
      },
      {
        path: 'empresa/crear',
        loadComponent: () => import('@views/super-admin/empresas/empresa/crear-empresa.component').then(m => m.CrearEmpresaComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.empresas.crear' },
        title: 'Empresa',
      },
      {
        path: 'empresa/:id',
        loadComponent: () => import('@views/super-admin/empresas/empresa/crear-empresa.component').then(m => m.CrearEmpresaComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.empresas.editar' },
        title: 'Empresa',
      },
      {
        path: 'empresas/usuarios',
        loadComponent: () => import('./empresas/usuarios/empresas-usuarios.component').then(m => m.EmpresasUsuariosComponent),
        title: 'Usuarios',
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.usuarios.ver' },
      },
      { 
        path: 'usuarios',
        loadComponent: () => import('@views/admin/usuarios/usuarios.component').then(m => m.UsuariosComponent), 
        title: 'Empresas',
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.usuarios.ver' },
      },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class OrganizacionesAdminRoutingModule {}
