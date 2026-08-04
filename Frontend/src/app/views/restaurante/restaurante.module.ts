import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';

import { RestauranteComponent } from './restaurante.component';
import { CuentaMesaComponent } from './cuenta-mesa/cuenta-mesa.component';
import { CocinaComponent } from './cocina/cocina.component';
import { ZonasRestauranteComponent } from './zonas/zonas-restaurante.component';
import { RestauranteRoutingModule } from './restaurante-routing.module';

@NgModule({
  declarations: [
    RestauranteComponent,
    CuentaMesaComponent,
    CocinaComponent,
    ZonasRestauranteComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    ModalModule.forRoot(),
    RestauranteRoutingModule,
    SharedModule
  ]
})
export class RestauranteModule { }
