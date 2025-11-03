import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { FullCalendarModule } from '@fullcalendar/angular';
import { NgSelectModule } from '@ng-select/ng-select';

import { PaquetesRoutingModule } from '@views/paquetes/paquetes.routing.module';
import { PaquetesComponent } from './paquetes.component';
import { PaqueteComponent } from './paquete/paquete.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    PaquetesRoutingModule,
    NgSelectModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    ModalModule.forRoot(),
    // Componentes standalone
    PaquetesComponent,
    PaqueteComponent
  ],
  exports: [
    PaquetesComponent,
    PaqueteComponent
  ]
})
export class PaquetesModule { }
