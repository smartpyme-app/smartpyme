import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { EmpleadosComponent }     from './empleados.component';
import { EmpleadoComponent }     from './empleado/empleado.component';
import { AsistenciasComponent }     from './asistencias/asistencias.component';
import { AsistenciaComponent }     from './asistencias/asistencia/asistencia.component';

import { PlanillasComponent }     from './planillas/planillas.component';
import { PlanillaComponent }     from './planillas/planilla/planilla.component';

import { ComisionesComponent }     from './comisiones/comisiones.component';
import { ComisionComponent } from './comisiones/comision/comision.component';

import { PropinasComponent }     from '../../views/empleados/propinas/propinas.component';
import { MetasComponent }     from '../../views/empleados/metas/metas.component';

import { EmpleadosVentasComponent }     from '../../views/reportes/empleados/ventas/empleados-ventas.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        { path: 'empleados', component: EmpleadosComponent },
        { path: 'empleado/:id', component: EmpleadoComponent },

        { path: 'asistencias', component: AsistenciasComponent },
        { path: 'asistencia/:id', component: AsistenciaComponent },
        
        { path: 'planillas', component: PlanillasComponent },
        { path: 'planilla/:id', component: PlanillaComponent },
        
        { path: 'comisiones', component: ComisionesComponent },
        { path: 'comision/:id', component: ComisionComponent },
        
        { path: 'metas', component: MetasComponent },
        { path: 'propinas', component: PropinasComponent },

        // Reportes 
            { path: 'reporte/empleados/ventas', component: EmpleadosVentasComponent },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class EmpleadosRoutingModule { }
