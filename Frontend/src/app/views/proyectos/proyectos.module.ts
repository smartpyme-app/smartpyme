import { NgModule } from '@angular/core';
import { ProyectosRoutingModule } from '@views/proyectos/proyectos.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    ProyectosRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class ProyectosModule { }
