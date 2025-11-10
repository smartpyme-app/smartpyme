import { NgModule } from '@angular/core';
import { OrganizacionesAdminRoutingModule } from './organizaciones-admin.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// y importan sus propias dependencias, por lo que no necesitan estar aquí

@NgModule({
  imports: [
    OrganizacionesAdminRoutingModule,
  ],
  declarations: [],
  exports: []
})
export class OrganizacionesAdminModule { }
