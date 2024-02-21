import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { PaquetesComponent } from '@views/paquetes/paquetes.component';
import { PaqueteComponent } from '@views/paquetes/paquete/paquete.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Paquetes',
    children: [

        { path: 'paquetes', component: PaquetesComponent, title: 'Paquetes' },
        { path: 'paquete/crear', component: PaqueteComponent, title: 'Paquete' },
        { path: 'paquete/editar/:id', component: PaqueteComponent, title: 'Paquete' },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PaquetesRoutingModule { }
