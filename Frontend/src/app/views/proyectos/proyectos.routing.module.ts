import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PermissionGuard } from '@guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    title: 'Proyectos',
    canActivate: [PermissionGuard],
    data: { permission: 'ventas.proyectos.ver' },
    children: [
      {
        path: 'proyectos',
        loadComponent: () => import('@views/proyectos/proyectos.component').then(m => m.ProyectosComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.proyectos.ver' },
        title: 'Proyectos',
      },
      {
        path: 'proyecto/crear',
        loadComponent: () => import('@views/proyectos/proyecto/proyecto.component').then(m => m.ProyectoComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.proyectos.crear' },
        title: 'Proyecto',
      },
      {
        path: 'proyecto/editar/:id',
        loadComponent: () => import('@views/proyectos/proyecto/proyecto.component').then(m => m.ProyectoComponent),
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
