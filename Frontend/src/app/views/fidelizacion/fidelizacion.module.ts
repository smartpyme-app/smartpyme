import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';

import { ConfiguracionClienteComponent } from './configuracion-cliente/configuracion-cliente.component';
import { ClientesFidelizacionComponent } from './clientes-fidelizacion/clientes-fidelizacion.component';
import { ClienteDetallesFidelizacionComponent } from './cliente-detalles-fidelizacion/cliente-detalles-fidelizacion.component';
import { FidelizacionRoutingModule } from './fidelizacion-routing.module';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    FidelizacionRoutingModule,
    SharedModule,
    PipesModule,
    PopoverModule.forRoot(),
    TooltipModule.forRoot(),
    ModalModule.forRoot(),
    // Componentes standalone
    ConfiguracionClienteComponent,
    ClientesFidelizacionComponent,
    ClienteDetallesFidelizacionComponent
  ]
})
export class FidelizacionModule { }
