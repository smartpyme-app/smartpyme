import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { ConfiguracionClienteComponent } from './configuracion-cliente/configuracion-cliente.component';
import { ClientesFidelizacionComponent } from './clientes-fidelizacion/clientes-fidelizacion.component';
import { ClienteDetallesFidelizacionComponent } from './cliente-detalles-fidelizacion/cliente-detalles-fidelizacion.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Lealtad',
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
      },
      {
        path: 'fidelizacion/clientes',
        component: ClientesFidelizacionComponent,
        title: 'Clientes Lealtad'
      },
      {
        path: 'fidelizacion/clientes/:tipoId',
        component: ClientesFidelizacionComponent,
        title: 'Clientes Lealtad'
      },
      {
        path: 'fidelizacion/cliente-detalles/:id',
        component: ClienteDetallesFidelizacionComponent,
        title: 'Detalles del Cliente'
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FidelizacionRoutingModule { }
