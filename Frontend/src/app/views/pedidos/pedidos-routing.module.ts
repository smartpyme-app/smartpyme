import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { PermissionGuard } from '../../guards/permission.guard';
import { FuncionalidadGuard } from '../../guards/funcionalidad.guard';
import { PedidosShellComponent } from './pedidos-shell.component';
import { PedidosListaComponent } from './pedidos-lista/pedidos-lista.component';
import { PedidoFormComponent } from './pedido-form/pedido-form.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Pedidos',
    canActivate: [FuncionalidadGuard, PermissionGuard],
    data: {
      funcionalidadSlug: 'modulo-restaurante',
      permission: 'pedidos.ver',
    },
    children: [
      {
        path: '',
        redirectTo: 'pedidos',
        pathMatch: 'full'
      },
      {
        path: 'pedidos',
        component: PedidosShellComponent,
        children: [
          { path: '', component: PedidosListaComponent, title: 'Pedidos' },
          { path: 'nuevo', component: PedidoFormComponent, title: 'Nuevo pedido', canActivate: [PermissionGuard], data: { permission: 'pedidos.crear' } },
          { path: 'editar/:id', component: PedidoFormComponent, title: 'Editar pedido', canActivate: [PermissionGuard], data: { permission: 'pedidos.editar' } }
        ]
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PedidosRoutingModule { }
