import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
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
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    RestauranteRoutingModule,
    SharedModule
  ]
})
export class RestauranteModule { }
