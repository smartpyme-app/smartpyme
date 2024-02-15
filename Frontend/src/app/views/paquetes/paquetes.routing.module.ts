import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { PaquetesComponent } from '@views/paquetes/paquetes.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Paquetes',
    children: [

        { path: 'paquetes', component: PaquetesComponent, title: 'Paquetes' },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PaquetesRoutingModule { }
