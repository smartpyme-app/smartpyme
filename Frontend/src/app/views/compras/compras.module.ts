import { NgModule } from '@angular/core';
import { ComprasRoutingModule } from './compras.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    ComprasRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class ComprasModule { }
