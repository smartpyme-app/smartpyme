import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

import { ConfiguracionClienteComponent } from './configuracion-cliente/configuracion-cliente.component';
import { FidelizacionRoutingModule } from './fidelizacion-routing.module';

@NgModule({
  declarations: [
    ConfiguracionClienteComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    FidelizacionRoutingModule,
    PopoverModule.forRoot(),
    TooltipModule.forRoot()
  ]
})
export class FidelizacionModule { }
