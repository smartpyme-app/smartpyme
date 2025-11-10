import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { PermissionGuard } from '@guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Paquetes',
    children: [
      {
        path: 'paquetes',
        loadComponent: () => import('@views/paquetes/paquetes.component').then(m => m.PaquetesComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.paquetes.ver' },
        title: 'Paquetes',
      },
      {
        path: 'paquete/crear',
        loadComponent: () => import('@views/paquetes/paquete/paquete.component').then(m => m.PaqueteComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.paquetes.crear' },
        title: 'Paquete',
      },
      {
        path: 'paquete/editar/:id',
        loadComponent: () => import('@views/paquetes/paquete/paquete.component').then(m => m.PaqueteComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.paquetes.editar' },
        title: 'Paquete',
      },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PaquetesRoutingModule {}
