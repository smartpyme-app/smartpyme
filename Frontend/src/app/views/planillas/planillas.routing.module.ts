import {NgModule} from '@angular/core';
import {RouterModule, Routes} from '@angular/router';
import {LayoutComponent} from '../../layout/layout.component';
import {PermissionGuard} from '../../guards/permission.guard';
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
    canActivate: [PermissionGuard],
    data: { permission: 'planilla.ver' },
    children: [
      {path: '', component: PlanillasComponent, title: 'Planillas', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }},
      {path: 'empleados', component: EmpleadosComponent, title: 'Empleados', canActivate: [PermissionGuard], data: { permission: 'planilla.empleados.ver' }},
      {path: 'empleado/crear', component: AdministrarEmpleadoComponent, title: 'Empleado', canActivate: [PermissionGuard], data: { permission: 'planilla.empleados.crear' }},
      {
        path: 'empleado/editar/:id',
        component: AdministrarEmpleadoComponent,
        title: 'Empleado',
        canActivate: [PermissionGuard],
        data: { permission: 'planilla.empleados.editar' },
      },
      {path: 'detalle/:id', component: PlanillaDetalleComponent, title: 'Planilla', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }},
      {path: 'planilla/:id/boletas', component: BoletaPagoComponent, title: 'Boletas', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }},
      {
        path: 'planilla/:id/boleta/:detalleId',
        component: BoletaPagoComponent,
        title: 'Boleta',
        canActivate: [PermissionGuard],
        data: { permission: 'planilla.registros.ver' },
      },
      {path: 'boletas/:id', component: VerBoletasComponent, title: 'Boletas', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }},
      {path: 'configuracion-planilla', component: ConfiguracionPlanillaComponent, title: 'Configuración de planilla', canActivate: [PermissionGuard], data: { permission: 'planilla.configuracion.ver' }},
      {path: 'aguinaldos', component: AguinaldosComponent, title: 'Aguinaldos', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }},
      {path: 'aguinaldo/detalle/:id', component: AguinaldoDetalleComponent, title: 'Aguinaldo', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }},
      {path: 'prestamos', component: PrestamosComponent, title: 'Préstamos', canActivate: [PermissionGuard], data: { permission: 'planilla.registros.ver' }}
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PlanillasRoutingModule {
}
