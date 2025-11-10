import { NgModule } from '@angular/core';
import { CitasRoutingModule } from '@views/citas/citas.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    CitasRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class CitasModule { }
