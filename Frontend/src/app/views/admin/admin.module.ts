import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';

import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { FocusModule } from 'angular2-focus';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ModalModule } from 'ngx-bootstrap/modal';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { TagInputModule } from 'ngx-chips';

import { PipesModule } from '../../pipes/pipes.module';
import { SharedModule } from '../../shared/shared.module';
import { AdminRoutingModule } from './admin.routing.module';

import { EmpresaComponent } from './empresa/empresa.component';
import { SucursalesComponent } from './sucursales/sucursales.component';
import { SucursalComponent } from './sucursales/sucursal/sucursal.component';

import { UsuariosComponent } from './usuarios/usuarios.component';
import { UsuarioComponent } from './usuarios/usuario/usuario.component';
import { CajasComponent } from './cajas/cajas.component';
import { CajaComponent } from './cajas/caja/caja.component';
import { CajaCortesComponent } from './cajas/caja/cortes/caja-cortes.component';
import { CajaUsuariosComponent } from './cajas/caja/usuarios/caja-usuarios.component';
import { CajaDocumentosComponent } from './cajas/caja/documentos/caja-documentos.component';
import { CajaEstadisticasComponent } from './cajas/estadisticas/caja-estadisticas.component';

import { NotificacionesComponent } from './notificaciones/notificaciones.component';
import { ReportesComponent } from './reportes/reportes.component';
import { DocsComponent } from './docs/docs.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    TagInputModule,
    AdminRoutingModule,
    FocusModule.forRoot(),
    PopoverModule.forRoot(),
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
  ],
  declarations: [
    EmpresaComponent,
    CajasComponent,
    CajaComponent,
    CajaUsuariosComponent,
    CajaCortesComponent,
    CajaDocumentosComponent,
    CajaEstadisticasComponent,
    SucursalesComponent,
    SucursalComponent,
    UsuariosComponent,
    UsuarioComponent,
    ReportesComponent,
    NotificacionesComponent,
    DocsComponent
  ],
  exports: [
    EmpresaComponent,
    CajasComponent,
    CajaComponent,
    CajaUsuariosComponent,
    CajaCortesComponent,
    CajaDocumentosComponent,
    CajaEstadisticasComponent,
    SucursalesComponent,
    SucursalComponent,
    UsuariosComponent,
    UsuarioComponent,
    ReportesComponent,
    NotificacionesComponent,
    DocsComponent
  ]
})
export class AdminModule { }
