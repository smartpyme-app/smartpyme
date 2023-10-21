import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { FocusModule } from 'angular2-focus';
import { PipesModule } from '../../pipes/pipes.module';
import { SharedModule } from '../../shared/shared.module';

import { HistorialVentasComponent } from './ventas/historial/historial-ventas.component';
import { DetalleVentasComponent } from './ventas/detalle/detalle-ventas.component';
import { CategoriasVentasComponent } from './ventas/categorias/categorias-ventas.component';

import { HistorialComprasComponent } from './compras/historial/historial-compras.component';
import { DetalleComprasComponent } from './compras/detalle/detalle-compras.component';
import { CategoriasComprasComponent } from './compras/categorias/categorias-compras.component';

import { EmpleadosVentasComponent } from './empleados/ventas/empleados-ventas.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    ProgressbarModule.forRoot(),
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    ModalModule.forRoot(),
    FocusModule.forRoot()
  ],
  declarations: [
    HistorialVentasComponent,
    DetalleVentasComponent,
    CategoriasVentasComponent,
    HistorialComprasComponent,
    DetalleComprasComponent,
    CategoriasComprasComponent,
    EmpleadosVentasComponent
  ],
  exports: [
    HistorialVentasComponent,
    DetalleVentasComponent,
    CategoriasVentasComponent,
    HistorialComprasComponent,
    DetalleComprasComponent,
    CategoriasComprasComponent,
    EmpleadosVentasComponent
  ]
})
export class ReportesModule { }
