import { NgModule } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';

import { CollapseModule } from 'ngx-bootstrap/collapse';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { FocusModule } from 'angular2-focus';
import { BsDropdownModule } from 'ngx-bootstrap/dropdown';
import { NgChartsModule } from 'ng2-charts';
import { PipesModule } from '../../pipes/pipes.module';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { SharedModule } from '../../shared/shared.module';

import { DashRoutingModule } from './dash.routing.module';

// import { FullCalendarModule } from '@fullcalendar/angular';

import { DashComponent }         from './dash.component';

import { AdminDashComponent }    from './admin/admin-dash.component';
import { DatosComponent }        from './admin/datos/datos.component';
import { DashOrdenesComponent }  from './admin/ordenes/dash-ordenes.component';

// import { CalendarioComponent }   from './admin/calendario/calendario.component';
import { TopsComponent }   from './admin/tops/tops.component';

import { VendedorDashComponent }   from './vendedor/vendedor-dash.component';
import { VendedorDatosComponent }   from './vendedor/datos/vendedor-datos.component';
import { VendedorProductosComponent }   from './vendedor/productos/vendedor-productos.component';

import { CajaDashComponent }   from './caja/caja-dash.component';
import { CajaOrdenesComponent }   from './caja/ordenes/caja-ordenes.component';
import { CajaVentasComponent }   from './caja/ventas/caja-ventas.component';
import { CajaDevolucionesComponent }   from './caja/devoluciones/caja-devoluciones.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    NgChartsModule,
    PipesModule,
    SharedModule,
    DashRoutingModule,
    // FullCalendarModule,
    TooltipModule.forRoot(),
    FocusModule.forRoot(),
    BsDropdownModule.forRoot(),
    TabsModule.forRoot(),
    CollapseModule.forRoot(),
    ProgressbarModule.forRoot(),
  ],
  declarations: [
  	DashComponent,
    DatosComponent,
    DashOrdenesComponent,
    // CalendarioComponent,
    TopsComponent,
    AdminDashComponent,
    CajaDashComponent,
    CajaOrdenesComponent,
    CajaVentasComponent,
    CajaDevolucionesComponent,
    VendedorDashComponent,
    VendedorDatosComponent,
    VendedorProductosComponent,
  ],
  exports: [
  	DashComponent,
    DatosComponent,
    DashOrdenesComponent,
    TopsComponent,
    AdminDashComponent,
    CajaDashComponent,
    CajaOrdenesComponent,
    CajaVentasComponent,
    CajaDevolucionesComponent,
    VendedorDashComponent,
    VendedorDatosComponent,
    VendedorProductosComponent,
  ]
})
export class DashModule { }
