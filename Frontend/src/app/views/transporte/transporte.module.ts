import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { NgSelectModule } from '@ng-select/ng-select';

import { PipesModule } from '../../pipes/pipes.module';

import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';

import { TransporteRoutingModule } from './transporte.routing.module';

import { SharedModule } from '../../shared/shared.module';
import { FacturacionModule } from '../ventas/facturacion/facturacion.module';
import { FletesComponent } from './fletes/fletes.component';
import { FleteComponent } from './fletes/flete/flete.component';
import { FleteDetallesComponent } from './fletes/flete/detalles/flete-detalles.component';
import { FleteClienteComponent } from './fletes/flete/cliente/flete-cliente.component';

import { FlotasComponent } from './flotas/flotas.component';
import { FlotaComponent } from './flotas/flota/flota.component';
import { FlotaInformacionComponent } from './flotas/flota/informacion/flota-informacion.component';
import { FlotaMantenimientosComponent } from './flotas/flota/mantenimientos/flota-mantenimientos.component';
import { FlotaFletesComponent } from './flotas/flota/fletes/flota-fletes.component';
import { FlotaDatosComponent } from './flotas/flota/datos/flota-datos.component';
import { FlotaDocumentosComponent } from './flotas/flota/documentos/flota-documentos.component';

import { MotoristasComponent } from './motoristas/motoristas.component';

import { MantenimientosComponent } from './mantenimientos/mantenimientos.component';
import { MantenimientoComponent } from './mantenimientos/mantenimiento/mantenimiento.component';
import { MantenimientoProductoComponent } from './mantenimientos/mantenimiento/producto/mantenimiento-producto.component';
import { MantenimientoDetallesComponent } from './mantenimientos/mantenimiento/detalles/mantenimiento-detalles.component';

import { RepuestosComponent } from './repuestos/repuestos.component';

import { MotoristasFletesComponent } from '../reportes/fletes/motoristas/motoristas-fletes.component';

export class AppModule {}

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    NgSelectModule,
    FacturacionModule,
    TransporteRoutingModule,
    FocusModule.forRoot(),
    PopoverModule.forRoot(),
    TooltipModule.forRoot()
  ],
  declarations: [
    FletesComponent,
    FleteComponent,
    FleteDetallesComponent,
    FleteClienteComponent,
    FlotasComponent,
    FlotaComponent,
    FlotaInformacionComponent,
    FlotaMantenimientosComponent,
    FlotaFletesComponent,
    FlotaDatosComponent,
    FlotaDocumentosComponent,
    MotoristasComponent,
    MantenimientosComponent,
    MantenimientoComponent,
    MantenimientoProductoComponent,
    MantenimientoDetallesComponent,
    RepuestosComponent,
    MotoristasFletesComponent
  ],
  exports: [
    FletesComponent,
    FleteComponent,
    FleteDetallesComponent,
    FleteClienteComponent,
    FlotasComponent,
    FlotaComponent,
    FlotaInformacionComponent,
    FlotaMantenimientosComponent,
    FlotaFletesComponent,
    FlotaDatosComponent,
    FlotaDocumentosComponent,
    MotoristasComponent,
    MantenimientosComponent,
    MantenimientoComponent,
    MantenimientoProductoComponent,
    MantenimientoDetallesComponent,
    RepuestosComponent,
    MotoristasFletesComponent
  ]
})
export class TransporteModule { }
