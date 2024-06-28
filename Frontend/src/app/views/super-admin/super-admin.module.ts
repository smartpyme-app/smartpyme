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
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';

import { SuperAdminRoutingModule } from './super-admin.routing.module';

import { EmpresasComponent } from './empresas/empresas.component';
import { CrearEmpresaComponent } from './empresas/empresa/crear-empresa.component';
import { LicenciasComponent } from './licencias/licencias.component';
import { LicenciaComponent } from './licencias/licencia/licencia.component';
import { LicenciaEmpresasComponent } from './licencias/licencia/empresas/licencia-empresas.component';
import { AdminUsuariosComponent } from './usuarios/admin-usuarios.component';
import { DashboardsComponent } from './dashboards/dashboards.component';
import { DashboardComponent } from './dashboards/dashboard/dashboard.component';
import { AdminFacturacionesComponent } from './facturacion/admin-facturaciones.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    TagInputModule,
    NgSelectModule,
    SuperAdminRoutingModule,
    NgxMaskDirective, NgxMaskPipe,
    FocusModule.forRoot(),
    PopoverModule.forRoot(),
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
  ],
  declarations: [
    EmpresasComponent,
    CrearEmpresaComponent,
    LicenciasComponent,
    LicenciaComponent,
    LicenciaEmpresasComponent,
    AdminUsuariosComponent,
    DashboardsComponent,
    DashboardComponent,
    AdminFacturacionesComponent
  ],
  exports: [
    EmpresasComponent,
    CrearEmpresaComponent,
    LicenciasComponent,
    LicenciaComponent,
    LicenciaEmpresasComponent,
    AdminUsuariosComponent,
    DashboardsComponent,
    DashboardComponent,
    AdminFacturacionesComponent
  ]
})
export class SuperAdminModule { }
