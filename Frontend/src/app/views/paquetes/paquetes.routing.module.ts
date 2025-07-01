import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { PaquetesComponent } from '@views/paquetes/paquetes.component';
import { PaqueteComponent } from '@views/paquetes/paquete/paquete.component';
import { PermissionGuard } from '@guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Paquetes',
    children: [
      {
        path: 'paquetes',
        component: PaquetesComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.paquetes.ver' },
        title: 'Paquetes',
      },
      {
        path: 'paquete/crear',
        component: PaqueteComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.paquetes.crear' },
        title: 'Paquete',
      },
      {
        path: 'paquete/editar/:id',
        component: PaqueteComponent,
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
