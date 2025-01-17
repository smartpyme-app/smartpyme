import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';

import { OrganizacionEmpresasComponent } from '@views/organizaciones-admin/empresas/organizacion-empresas.component';
import { CrearEmpresaComponent } from '@views/super-admin/empresas/empresa/crear-empresa.component';
import { AdminUsuariosComponent } from '@views/super-admin/usuarios/admin-usuarios.component';
import { EmpresasUsuariosComponent } from './empresas/usuarios/empresas-usuarios.component';
import { UsuariosComponent } from '@views/admin/usuarios/usuarios.component';
import { PermissionGuard } from '@guards/permission.guard';

const routes: Routes = [
  {
    path: 'organizacion',
    component: LayoutComponent,
    children: [
      {
        path: 'empresas',
        component: OrganizacionEmpresasComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.empresas.ver' },
        title: 'Empresas',
      },

      {
        path: 'empresa/crear',
        component: CrearEmpresaComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.empresas.crear' },
        title: 'Empresa',
      },
      {
        path: 'empresa/:id',
        component: CrearEmpresaComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'organizacion.empresas.editar' },
        title: 'Empresa',
      },

      {
        path: 'empresas/usuarios',
        component: EmpresasUsuariosComponent,
        title: 'Usuarios',
      },

      { path: 'usuarios', component: UsuariosComponent, title: 'Empresas' },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class OrganizacionesAdminRoutingModule {}
