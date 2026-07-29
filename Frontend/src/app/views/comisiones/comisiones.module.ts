import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';

import { ComisionesRoutingModule } from './comisiones-routing.module';
import { ConfigCategoriasComponent } from './config-categorias/config-categorias.component';
import { PeriodosLiquidacionesComponent } from './periodos-liquidaciones/periodos-liquidaciones.component';
import { PeriodoDetalleComponent } from './periodo-detalle/periodo-detalle.component';
import { ReportesComponent } from './reportes/reportes.component';

@NgModule({
  declarations: [
    ConfigCategoriasComponent,
    PeriodosLiquidacionesComponent,
    PeriodoDetalleComponent,
    ReportesComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    ComisionesRoutingModule,
    SharedModule,
    PipesModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot()
  ]
})
export class ComisionesModule {}
