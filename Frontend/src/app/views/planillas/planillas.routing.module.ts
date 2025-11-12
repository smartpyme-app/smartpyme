import {NgModule} from '@angular/core';
import {RouterModule, Routes} from '@angular/router';
import {LayoutComponent} from '../../layout/layout.component';

const routes: Routes = [
  {
    path: 'planilla',
    component: LayoutComponent,
    children: [
      {
        path: '', 
        loadComponent: () => import('./planillas.component').then(m => m.PlanillasComponent)
      },
      {
        path: 'empleados', 
        loadComponent: () => import('./empleados/empleados.component').then(m => m.EmpleadosComponent)
      },
      {
        path: 'empleado/crear', 
        loadComponent: () => import('./empleados/administrar-empleado.component').then(m => m.AdministrarEmpleadoComponent)
      },
      {
        path: 'empleado/editar/:id',
        loadComponent: () => import('./empleados/administrar-empleado.component').then(m => m.AdministrarEmpleadoComponent),
      },
      {
        path: 'detalle/:id', 
        loadComponent: () => import('./planillas/planilla-detalle.component').then(m => m.PlanillaDetalleComponent)
      },
      {
        path: 'planilla/:id/boletas', 
        loadComponent: () => import('./planillas/boleta-pago.component').then(m => m.BoletaPagoComponent)
      },
      {
        path: 'planilla/:id/boleta/:detalleId',
        loadComponent: () => import('./planillas/boleta-pago.component').then(m => m.BoletaPagoComponent),
      },
      {
        path: 'boletas/:id', 
        loadComponent: () => import('./planillas/ver-boletas.component').then(m => m.VerBoletasComponent)
      },
      {
        path: 'configuracion-planilla', 
        loadComponent: () => import('./configuracion-planilla/configuracion-planilla.component').then(m => m.ConfiguracionPlanillaComponent)
      },
      {
        path: 'test-constants', 
        loadComponent: () => import('./test-constants.component').then(m => m.TestConstantsComponent)
      }
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PlanillasRoutingModule {
}
