import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { TagInputModule } from 'ngx-chips';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { NgChartsModule } from 'ng2-charts';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ModalModule } from 'ngx-bootstrap/modal';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask';
import { NgSelectModule } from '@ng-select/ng-select';

import { ClientesComponent } from './clientes.component';
import { ClienteComponent } from './cliente/cliente.component';
import { ClienteDetallesComponent } from './cliente-detalles/cliente-detalles.component';
import { ClienteDatosComponent } from './cliente/datos/cliente-datos.component';
import { ClientesDashComponent } from './dash/clientes-dash.component';
import { ClienteInformacionComponent } from './cliente/informacion/cliente-informacion.component';
import { ClienteVentasComponent } from './cliente/ventas/cliente-ventas.component';
import { ClienteDocumentosComponent } from './cliente/documentos/cliente-documentos.component';
import { CuentasCobrarComponent } from './cuentas-cobrar/cuentas-cobrar.component';
import { ClienteVista360Component } from './cliente-vista-360/cliente-vista-360.component';

@NgModule({
  imports: [
    CommonModule,
    BrowserAnimationsModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    TagInputModule,
    NgChartsModule,
    NgSelectModule,
    NgxMaskDirective, 
    NgxMaskPipe,
    PopoverModule.forRoot(),
    ModalModule.forRoot(),
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
    // Componentes standalone
    ClientesComponent,
    ClienteComponent,
    ClienteDetallesComponent,
    ClienteDatosComponent,
    ClientesDashComponent,
    ClienteInformacionComponent,
    ClienteDocumentosComponent,
    ClienteVentasComponent,
    CuentasCobrarComponent,
    ClienteVista360Component
  ],
  exports: [
    ClientesComponent,
    ClienteComponent,
    ClienteDetallesComponent,
    ClienteDatosComponent,
    ClientesDashComponent,
    ClienteInformacionComponent,
    ClienteDocumentosComponent,
    ClienteVentasComponent,
    CuentasCobrarComponent,
    ClienteVista360Component
  ]
})
export class ClientesModule { }