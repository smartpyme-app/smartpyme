import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';

import { BonosRoutingModule } from './bonos-routing.module';
import { ReglasComponent } from './reglas/reglas.component';
import { GeneradosComponent } from './generados/generados.component';

@NgModule({
  declarations: [
    ReglasComponent,
    GeneradosComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    BonosRoutingModule,
    SharedModule,
    PipesModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot()
  ]
})
export class BonosModule {}
