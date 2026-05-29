import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { SharedModule } from '@shared/shared.module';

import { RestauranteComponent } from './restaurante.component';
import { CuentaMesaComponent } from './cuenta-mesa/cuenta-mesa.component';
import { CocinaComponent } from './cocina/cocina.component';
import { RestauranteRoutingModule } from './restaurante-routing.module';

@NgModule({
  declarations: [
    RestauranteComponent,
    CuentaMesaComponent,
    CocinaComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    RestauranteRoutingModule,
    SharedModule
  ]
})
export class RestauranteModule { }
