import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '@shared/shared.module';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { CollapseModule } from 'ngx-bootstrap/collapse';
import { HeaderComponent } from './header/header.component';
import { AdminHeaderComponent } from './header/admin/admin-header.component';

import { FooterComponent } from './footer/footer.component';
import { SidebarComponent } from './sidebar/sidebar.component';
import { SidebarAdminComponent } from './sidebar/sidebar-admin/sidebar-admin.component';
import { SidebarOrganizacionesComponent } from './sidebar/sidebar-organizaciones/sidebar-organizaciones.component';
import { SidebarVentasComponent } from './sidebar/sidebar-ventas/sidebar-ventas.component';
import { SidebarServiciosComponent } from './sidebar/sidebar-servicios/sidebar-servicios.component';
import { LayoutComponent } from './layout.component';
import { SpeedDialComponent } from '../shared/speed-dial/speed-dial.component';
import { ChatDrawerComponent } from '../shared/chat/chat-drawer.component';

import { ThemeComponent } from './header/theme/theme.component';
import { PerfilComponent } from './header/perfil/perfil.component';


@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    ReactiveFormsModule,
    TooltipModule.forRoot(),
    CollapseModule.forRoot(),
    // Componentes standalone
    LayoutComponent,
    HeaderComponent,
    AdminHeaderComponent,
    FooterComponent,
    SidebarComponent,
    SidebarAdminComponent,
    SidebarOrganizacionesComponent,
    SidebarVentasComponent,
    SidebarServiciosComponent,
    PerfilComponent,
    ThemeComponent,
    SpeedDialComponent,
    ChatDrawerComponent
  ],
  declarations: [
    // Todos los componentes son standalone, se importan arriba
  ],
  exports: [
    FooterComponent,
    HeaderComponent,
    AdminHeaderComponent,
    SidebarComponent,
    SidebarAdminComponent,
    SidebarOrganizacionesComponent,
    SidebarVentasComponent,
    SidebarServiciosComponent,
    LayoutComponent,
    PerfilComponent,
    ThemeComponent,
    SpeedDialComponent,
    ChatDrawerComponent
  ]
})
export class LayoutModule { }
