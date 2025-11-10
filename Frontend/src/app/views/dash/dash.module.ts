import { NgModule } from '@angular/core';
import { DashRoutingModule } from './dash.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    DashRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class DashModule { }
