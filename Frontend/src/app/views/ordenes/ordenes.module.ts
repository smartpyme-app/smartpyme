import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';

import { PipesModule } from '../../pipes/pipes.module';

import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';

import { SharedModule } from '../../shared/shared.module';
import { FacturacionModule } from '../ventas/facturacion/facturacion.module';
import { OrdenesComponent } from './ordenes.component';
import { OrdenComponent } from './orden/orden.component';
import { OrdenClienteComponent } from './orden/cliente/orden-cliente.component';
import { OrdenProductosComponent } from './orden/productos/orden-productos.component';
import { OrdenDetallesComponent } from './orden/detalles/orden-detalles.component';

export class AppModule {}

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    FacturacionModule,
    FocusModule.forRoot(),
    PopoverModule.forRoot(),
    TooltipModule.forRoot()
  ],
  declarations: [
    OrdenesComponent,
    OrdenComponent,
    OrdenClienteComponent,
    OrdenProductosComponent,
    OrdenDetallesComponent,
  ],
  exports: [
    OrdenesComponent,
    OrdenComponent,
    OrdenClienteComponent,
    OrdenProductosComponent,
    OrdenDetallesComponent,
  ]
})
export class OrdenesModule { }
