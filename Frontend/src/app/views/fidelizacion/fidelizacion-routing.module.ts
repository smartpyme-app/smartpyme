import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { ConfiguracionClienteComponent } from './configuracion-cliente/configuracion-cliente.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Fidelización',
    children: [
      {
        path: '',
        redirectTo: 'fidelizacion/configuracion-cliente',
        pathMatch: 'full'
      },
      {
        path: 'fidelizacion/configuracion-cliente',
        component: ConfiguracionClienteComponent,
        title: 'Configuración de Cliente'
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FidelizacionRoutingModule { }
