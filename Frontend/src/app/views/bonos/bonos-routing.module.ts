import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { ReglasComponent } from './reglas/reglas.component';
import { GeneradosComponent } from './generados/generados.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Bonos',
    canActivate: [FuncionalidadGuard],
    data: { funcionalidadSlug: 'bonos-vendedores' },
    children: [
      {
        path: '',
        redirectTo: 'bonos/reglas',
        pathMatch: 'full'
      },
      {
        path: 'bonos/reglas',
        component: ReglasComponent,
        title: 'Reglas de bonos'
      },
      {
        path: 'bonos/generados',
        component: GeneradosComponent,
        title: 'Bonos generados'
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class BonosRoutingModule {}
