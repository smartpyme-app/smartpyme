import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { CitasComponent } from '@views/citas/citas.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Citas',
    children: [

        { path: 'citas', component: CitasComponent, title: 'Citas' },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CitasRoutingModule { }
