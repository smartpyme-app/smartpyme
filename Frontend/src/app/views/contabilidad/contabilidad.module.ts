import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { PipesModule } from '../../pipes/pipes.module';
import { SharedModule } from '../../shared/shared.module';
import { NgChartsModule } from 'ng2-charts';
import { NgSelectModule } from '@ng-select/ng-select';

import { ContabilidadRoutingModule } from './contabilidad.routing.module';

import { LibroIvaComponent } from './libro-iva/libro-iva.component';
import { LibroComprasComponent } from './libro-compras/libro-compras.component';
import { GalonajeComponent } from './galonaje/galonaje.component';

import { CajasChicasComponent } from './cajas-chicas/cajas-chicas.component';
import { CajaChicaComponent } from './cajas-chicas/caja-chica/caja-chica.component';
import { CajaChicaDetallesComponent } from './cajas-chicas/caja-chica/detalles/caja-chica-detalles.component';

import { ActivosComponent } from './activos/activos.component';
import { ActivoComponent } from './activos/activo/activo.component';
import { ActivosCategoriasComponent } from './activos/categorias/activos-categorias.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    NgChartsModule,
    NgSelectModule,
    ContabilidadRoutingModule,
    PopoverModule.forRoot(),
    FocusModule.forRoot(),
    TooltipModule.forRoot()
  ],
  declarations: [
    LibroIvaComponent,
  	LibroComprasComponent,
    GalonajeComponent,
    CajasChicasComponent,
    CajaChicaComponent,
    CajaChicaDetallesComponent,
    ActivosComponent,
    ActivoComponent,
    ActivosCategoriasComponent
  ],
  exports: [
    LibroIvaComponent,
  	LibroComprasComponent,
    GalonajeComponent,
    CajasChicasComponent,
    CajaChicaComponent,
    CajaChicaDetallesComponent,
    ActivosComponent,
    ActivoComponent,
    ActivosCategoriasComponent
  ]
})
export class ContabilidadModule { }
