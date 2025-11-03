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
import { DocsComponent } from './docs/docs.component'; // Ahora es standalone
// import { ThreedsModalComponent } from '../../auth/register/pago/modal/threeds-modal.component';
import { WhatsAppComponent } from './whatsapp/whatsapp.component';
import { WhatsAppEstadisticasComponent } from './whatsapp/estadisticas/whatsapp-estadisticas.component';
import { NgxIntlTelInputModule } from 'ngx-intl-tel-input';

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
    PopoverModule.forRoot(),
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
    ModalModule.forRoot(),
    NgxIntlTelInputModule,
    // Componentes standalone
    DocsComponent,
    EmpresaComponent,
    EliminarDatosComponent,
    SuscripcionComponent,
    SucursalesComponent,
    SucursalComponent,
    UsuariosComponent,
    UsuarioComponent,
    ReportesComponent,
    NotificacionesComponent,
    WhatsAppComponent,
    WhatsAppEstadisticasComponent
  ],
  exports: [
    // Todos los componentes standalone se exportan como imports standalone
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
    WhatsAppComponent,
    WhatsAppEstadisticasComponent
  ]
})
export class AdminModule { }
