import { NgModule } from '@angular/core';
import { PaquetesRoutingModule } from '@views/paquetes/paquetes.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    PaquetesRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class PaquetesModule { }
