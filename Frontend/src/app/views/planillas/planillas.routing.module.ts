import {NgModule} from '@angular/core';
import {RouterModule, Routes} from '@angular/router';
import {LayoutComponent} from '../../layout/layout.component';
import {AdminGuard} from '../../guards/admin.guard';
import {PlanillasComponent} from './planillas.component';
import {EmpleadosComponent} from './empleados/empleados.component';
import {AdministrarEmpleadoComponent} from './empleados/administrar-empleado.component';
import {PlanillaDetalleComponent} from './planillas/planilla-detalle.component';
import {BoletaPagoComponent} from './planillas/boleta-pago.component';
import {VerBoletasComponent} from './planillas/ver-boletas.component';
import { ConfiguracionPlanillaComponent } from './configuracion-planilla/configuracion-planilla.component';
import { AguinaldosComponent } from './aguinaldos/aguinaldos.component';
import { AguinaldoDetalleComponent } from './aguinaldos/aguinaldo-detalle.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
      {path: '', component: PlanillasComponent},
      {path: 'empleados', component: EmpleadosComponent},
      {path: 'empleado/crear', component: AdministrarEmpleadoComponent},
      {path: 'empleado/editar/:id', component: AdministrarEmpleadoComponent},
      {path: 'detalle/:id', component: PlanillaDetalleComponent},
      {path: 'planilla/:id/boletas', component: BoletaPagoComponent},
      {path: 'planilla/:id/boleta/:detalleId', component: BoletaPagoComponent},
      {path: 'boletas/:id', component: VerBoletasComponent},
      {path: 'configuracion-planilla', component: ConfiguracionPlanillaComponent},
      {path: 'aguinaldos', component: AguinaldosComponent},
      {path: 'aguinaldo/detalle/:id', component: AguinaldoDetalleComponent}
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PlanillasRoutingModule {
}
