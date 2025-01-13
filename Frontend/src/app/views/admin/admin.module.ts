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
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask'
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { AdminRoutingModule } from './admin.routing.module';

import { EmpresaComponent } from './empresa/empresa.component';
import { EliminarDatosComponent } from './empresa/eliminar-datos/eliminar-datos.component';
import { SuscripcionComponent } from './suscripcion/suscripcion.component';

import { SucursalesComponent } from './sucursales/sucursales.component';
import { SucursalComponent } from './sucursales/sucursal/sucursal.component';

import { UsuariosComponent } from './usuarios/usuarios.component';
import { UsuarioComponent } from './usuarios/usuario/usuario.component';

import { NotificacionesComponent } from './notificaciones/notificaciones.component';
import { ReportesComponent } from './reportes/reportes.component';
import { DocsComponent } from './docs/docs.component';
import { RolesPermisosComponent } from './roles-permisos/roles-permisos.component';
import { ModulesComponent } from './modules/modules.component';
import { ModuleFormComponent } from './modules/create/module-form.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    TagInputModule,
    NgSelectModule,
    AdminRoutingModule,
    NgxMaskDirective, NgxMaskPipe,
    FocusModule.forRoot(),
    PopoverModule.forRoot(),
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
  ],
  declarations: [
    EmpresaComponent,
    EliminarDatosComponent,
    SuscripcionComponent,
    SucursalesComponent,
    SucursalComponent,
    UsuariosComponent,
    UsuarioComponent,
    ReportesComponent,
    NotificacionesComponent,
    DocsComponent,
    RolesPermisosComponent,
    ModulesComponent,
    ModuleFormComponent
  ],
  exports: [
    EmpresaComponent,
    EliminarDatosComponent,
    SuscripcionComponent,
    SucursalesComponent,
    SucursalComponent,
    UsuariosComponent,
    UsuarioComponent,
    ReportesComponent,
    NotificacionesComponent,
    DocsComponent,
    RolesPermisosComponent,
    ModulesComponent,
    ModuleFormComponent
  ]
})
export class AdminModule { }

