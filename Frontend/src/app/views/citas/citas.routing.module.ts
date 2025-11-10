import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Citas',
    children: [
        { 
          path: 'citas', 
          loadComponent: () => import('@views/citas/citas.component').then(m => m.CitasComponent), 
          title: 'Citas' 
        },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CitasRoutingModule { }
