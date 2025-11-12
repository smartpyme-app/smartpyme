import { NgModule } from '@angular/core';
import { PlanillasRoutingModule } from './planillas.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    PlanillasRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class PlanillasModule { }