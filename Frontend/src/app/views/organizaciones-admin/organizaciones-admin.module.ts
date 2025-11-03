import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';

import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ModalModule } from 'ngx-bootstrap/modal';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { TagInputModule } from 'ngx-chips';
import { NgSelectModule } from '@ng-select/ng-select';

import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';

import { OrganizacionesAdminRoutingModule } from './organizaciones-admin.routing.module';
import { OrganizacionEmpresasComponent } from './empresas/organizacion-empresas.component';
import { EmpresasUsuariosComponent } from './empresas/usuarios/empresas-usuarios.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    TagInputModule,
    NgSelectModule,
    OrganizacionesAdminRoutingModule,
    PopoverModule.forRoot(),
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
    // Componentes standalone
    OrganizacionEmpresasComponent,
    EmpresasUsuariosComponent
  ],
  exports: [
    OrganizacionEmpresasComponent,
    EmpresasUsuariosComponent
  ]
})
export class OrganizacionesAdminModule { }
