import { NgModule } from '@angular/core';
import { ContabilidadRoutingModule } from './contabilidad.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    ContabilidadRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class ContabilidadModule { }
