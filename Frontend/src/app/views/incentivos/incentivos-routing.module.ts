import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { DashboardComponent } from './dashboard/dashboard.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Incentivos',
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
