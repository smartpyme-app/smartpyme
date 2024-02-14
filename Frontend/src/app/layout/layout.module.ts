import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '@shared/shared.module';
import { TourNgxBootstrapModule } from 'ngx-ui-tour-ngx-bootstrap';
import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { CollapseModule } from 'ngx-bootstrap/collapse';
import { HeaderComponent } from './header/header.component';
import { AdminHeaderComponent } from './header/admin/admin-header.component';

import { FooterComponent } from './footer/footer.component';
import { SidebarComponent } from './sidebar/sidebar.component';
import { SidebarAdminComponent } from './sidebar/sidebar-admin/sidebar-admin.component';
import { SidebarVentasComponent } from './sidebar/sidebar-ventas/sidebar-ventas.component';
import { SidebarServiciosComponent } from './sidebar/sidebar-servicios/sidebar-servicios.component';
import { LayoutComponent } from './layout.component';

import { ThemeComponent } from './header/theme/theme.component';
import { PerfilComponent } from './header/perfil/perfil.component';


@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    ReactiveFormsModule,
    TourNgxBootstrapModule,
    TooltipModule.forRoot(),
    CollapseModule.forRoot(),
    FocusModule.forRoot(),
    // NotifierModule.withConfig({position: {horizontal:{ position:'middle' } }, theme: 'material'}),
  ],
  declarations: [
    FooterComponent,
    HeaderComponent,
    AdminHeaderComponent,
    SidebarComponent,
    SidebarAdminComponent,
    SidebarVentasComponent,
    SidebarServiciosComponent,
    LayoutComponent,
    PerfilComponent,
    ThemeComponent,
  ],
  exports: [
    FooterComponent,
    HeaderComponent,
    AdminHeaderComponent,
    SidebarComponent,
    SidebarAdminComponent,
    SidebarVentasComponent,
    SidebarServiciosComponent,
    LayoutComponent,
    PerfilComponent,
    ThemeComponent,
  ]
})
export class LayoutModule { }
