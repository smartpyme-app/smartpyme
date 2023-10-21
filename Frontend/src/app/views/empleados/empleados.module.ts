import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { FocusModule } from 'angular2-focus';
import { PipesModule } from '../../pipes/pipes.module';
import { SharedModule } from '../../shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';

import { EmpleadosRoutingModule } from './empleados.routing.module';

import { EmpleadoComponent } from './empleado/empleado.component';
import { EmpleadosComponent } from './empleados.component';
import { EmpleadoFletesComponent } from './empleado/fletes/empleado-fletes.component';
import { EmpleadoDocumentosComponent } from './empleado/documentos/empleado-documentos.component';
import { EmpleadoCuentaComponent } from './empleado/cuenta/empleado-cuenta.component';
import { EmpleadoMetasComponent } from './empleado/metas/empleado-metas.component';

import { PropinasComponent } from './propinas/propinas.component';

import { AsistenciasComponent } from './asistencias/asistencias.component';
import { AsistenciaComponent } from './asistencias/asistencia/asistencia.component';
import { AsistenciaMarcadorComponent } from './asistencias/asistencia-marcador/asistencia-marcador.component';

import { PlanillasComponent } from './planillas/planillas.component';
import { PlanillaComponent } from './planillas/planilla/planilla.component';
import { PlanillaDetallesComponent } from './planillas/planilla/detalles/planilla-detalles.component';

import { ComisionesComponent } from './comisiones/comisiones.component';
import { ComisionComponent } from './comisiones/comision/comision.component';
import { MetasComponent } from './metas/metas.component';


@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    NgSelectModule,
    EmpleadosRoutingModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    FocusModule.forRoot()
  ],
  declarations: [
    EmpleadosComponent,
    EmpleadoComponent,
    EmpleadoFletesComponent,
    EmpleadoCuentaComponent,
    EmpleadoMetasComponent,
    EmpleadoDocumentosComponent,
    PropinasComponent,
    AsistenciasComponent,
    AsistenciaComponent,
    AsistenciaMarcadorComponent,
    PlanillasComponent,
    PlanillaComponent,
    PlanillaDetallesComponent,
    ComisionesComponent,
    ComisionComponent,
    MetasComponent
  ],
  exports: [
    EmpleadosComponent,
    EmpleadoComponent,
    EmpleadoFletesComponent,
    EmpleadoCuentaComponent,
    EmpleadoMetasComponent,
    EmpleadoDocumentosComponent,
    PropinasComponent,
    AsistenciasComponent,
    AsistenciaComponent,
    AsistenciaMarcadorComponent,
    PlanillasComponent,
    PlanillaComponent,
    PlanillaDetallesComponent,
    ComisionesComponent,
    ComisionComponent,
    MetasComponent
  ]
})
export class EmpleadosModule { }
