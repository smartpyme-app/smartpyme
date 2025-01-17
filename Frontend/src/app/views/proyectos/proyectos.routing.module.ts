import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { ProyectosComponent } from '@views/proyectos/proyectos.component';
import { ProyectoComponent } from '@views/proyectos/proyecto/proyecto.component';
import { PermissionGuard } from '@guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Proyectos',
    canActivate: [PermissionGuard],
    data: { permission: 'ventas.proyectos.ver' },
    children: [
      {
        path: 'proyectos',
        component: ProyectosComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.proyectos.ver' },
        title: 'Proyectos',
      },
      {
        path: 'proyecto/crear',
        component: ProyectoComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.proyectos.crear' },
        title: 'Proyecto',
      },
      {
        path: 'proyecto/editar/:id',
        component: ProyectoComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.proyectos.editar' },
        title: 'Proyecto',
      },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ProyectosRoutingModule {}
