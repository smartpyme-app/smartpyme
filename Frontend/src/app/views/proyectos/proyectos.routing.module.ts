import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { ProyectosComponent } from '@views/proyectos/proyectos.component';
import { ProyectoComponent } from '@views/proyectos/proyecto/proyecto.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Proyectos',
    children: [

        { path: 'proyectos', component: ProyectosComponent, title: 'Proyectos' },
        { path: 'proyecto/crear', component: ProyectoComponent, title: 'Proyecto' },
        { path: 'proyecto/editar/:id', component: ProyectoComponent, title: 'Proyecto' },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ProyectosRoutingModule { }
