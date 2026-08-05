import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';

import { RestauranteComponent } from './restaurante.component';
import { CuentaMesaComponent } from './cuenta-mesa/cuenta-mesa.component';
import { PosCatalogoComponent } from './cuenta-mesa/pos-catalogo/pos-catalogo.component';
import { PosSheetAgregarComponent } from './cuenta-mesa/pos-sheet-agregar/pos-sheet-agregar.component';
import { PosFlujoCuentaComponent } from './cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component';
import { CocinaComponent } from './cocina/cocina.component';
import { ZonasRestauranteComponent } from './zonas/zonas-restaurante.component';
import { RestauranteRoutingModule } from './restaurante-routing.module';

@NgModule({
  declarations: [
    RestauranteComponent,
    CuentaMesaComponent,
    PosCatalogoComponent,
    PosSheetAgregarComponent,
    PosFlujoCuentaComponent,
    CocinaComponent,
    ZonasRestauranteComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
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
