import { NgModule } from '@angular/core';
import { InventarioRoutingModule } from './inventario.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    InventarioRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class InventarioModule { }

