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
import { PrestamosComponent } from './prestamos/prestamos.component';

const routes: Routes = [
  {
    path: 'planilla',
    component: LayoutComponent,
    title: 'Planillas',
    children: [
      {path: '', component: PlanillasComponent, title: 'Planillas'},
      {path: 'empleados', component: EmpleadosComponent, title: 'Empleados'},
      {path: 'empleado/crear', component: AdministrarEmpleadoComponent, title: 'Empleado'},
      {
        path: 'empleado/editar/:id',
        component: AdministrarEmpleadoComponent,
        title: 'Empleado',
      },
      {path: 'detalle/:id', component: PlanillaDetalleComponent, title: 'Planilla'},

      {path: 'planilla/:id/boletas', component: BoletaPagoComponent, title: 'Boletas'},
      {
        path: 'planilla/:id/boleta/:detalleId',
        component: BoletaPagoComponent,
        title: 'Boleta',
      },
      {path: 'boletas/:id', component: VerBoletasComponent, title: 'Boletas'},
      {path: 'configuracion-planilla', component: ConfiguracionPlanillaComponent, title: 'Configuración de planilla'},
      {path: 'aguinaldos', component: AguinaldosComponent, title: 'Aguinaldos'},
      {path: 'aguinaldo/detalle/:id', component: AguinaldoDetalleComponent, title: 'Aguinaldo'},
      {path: 'prestamos', component: PrestamosComponent, title: 'Préstamos'}
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PlanillasRoutingModule {
}
