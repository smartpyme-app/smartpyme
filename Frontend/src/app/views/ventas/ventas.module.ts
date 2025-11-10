import { NgModule } from '@angular/core';
import { VentasRoutingModule } from '@views/ventas/ventas.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    VentasRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class VentasModule { }

