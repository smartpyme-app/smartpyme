import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { SharedModule } from '@shared/shared.module';

import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { CollapseModule } from 'ngx-bootstrap/collapse';

import { HeaderComponent } from './header/header.component';
import { AdminHeaderComponent } from './header/admin/admin-header.component';
import { CocineroHeaderComponent } from './header/cocinero/cocinero-header.component';
import { MeseroHeaderComponent } from './header/mesero/mesero-header.component';
import { VendedorHeaderComponent } from './header/vendedor/vendedor-header.component';
import { CajaHeaderComponent } from './header/caja/caja-header.component';

import { FooterComponent } from './footer/footer.component';
import { SidebarComponent } from './sidebar/sidebar.component';
import { LayoutComponent } from './layout.component';

import { ThemeComponent } from './header/theme/theme.component';
import { PerfilComponent } from './header/perfil/perfil.component';


@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    TooltipModule.forRoot(),
    CollapseModule.forRoot(),
    FocusModule.forRoot(),
    // NotifierModule.withConfig({position: {horizontal:{ position:'middle' } }, theme: 'material'}),
  ],
  declarations: [
    FooterComponent,
    HeaderComponent,
    AdminHeaderComponent,
    CocineroHeaderComponent,
    MeseroHeaderComponent,
    VendedorHeaderComponent,
    CajaHeaderComponent,
    SidebarComponent,
    LayoutComponent,
    PerfilComponent,
    ThemeComponent,
  ],
  exports: [
    FooterComponent,
    HeaderComponent,
    AdminHeaderComponent,
    CocineroHeaderComponent,
    MeseroHeaderComponent,
    VendedorHeaderComponent,
    CajaHeaderComponent,
    SidebarComponent,
    LayoutComponent,
    PerfilComponent,
    ThemeComponent,
  ]
})
export class LayoutModule { }
