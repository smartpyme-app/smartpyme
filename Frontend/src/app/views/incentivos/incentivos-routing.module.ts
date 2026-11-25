import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DashboardComponent } from './dashboard/dashboard.component';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { PermissionGuard } from '../../guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    title: 'Incentivos',
    canActivate: [FuncionalidadGuard, PermissionGuard],
    data: {
      funcionalidadSlugs: ['comisiones-vendedores', 'bonos-vendedores', 'gift-cards'],
      permission: 'planilla.incentivos.ver',
    },
    children: [
      {
        path: '',
        redirectTo: 'incentivos/vendedores',
        pathMatch: 'full'
      },
      {
        path: 'incentivos/vendedores',
        component: DashboardComponent,
        title: 'Dashboard de incentivos'
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class IncentivosRoutingModule {}
