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

import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';

import { SuperAdminRoutingModule } from './super-admin.routing.module';

import { EmpresasComponent } from './empresas/empresas.component';
import { AdminUsuariosComponent } from './usuarios/admin-usuarios.component';
import { DashboardsComponent } from './dashboards/dashboards.component';
import { DashboardComponent } from './dashboards/dashboard/dashboard.component';

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
    FocusModule.forRoot(),
    PopoverModule.forRoot(),
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
  ],
  declarations: [
    EmpresasComponent,
    AdminUsuariosComponent,
    DashboardsComponent,
    DashboardComponent
  ],
  exports: [
    EmpresasComponent,
    AdminUsuariosComponent,
    DashboardsComponent,
    DashboardComponent
  ]
})
export class SuperAdminModule { }
