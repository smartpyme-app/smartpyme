import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { RestauranteComponent } from './restaurante.component';
import { CuentaMesaComponent } from './cuenta-mesa/cuenta-mesa.component';
import { CocinaComponent } from './cocina/cocina.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Restaurante',
    children: [
      {
        path: '',
        redirectTo: 'restaurante',
        pathMatch: 'full'
      },
      {
        path: 'restaurante',
        component: RestauranteComponent,
        title: 'Mapa de Mesas'
      },
      {
        path: 'restaurante/cuenta/:id',
        component: CuentaMesaComponent,
        title: 'Cuenta Mesa'
      },
      {
        path: 'restaurante/cocina',
        component: CocinaComponent,
        title: 'Pantalla Cocina'
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class RestauranteRoutingModule { }
