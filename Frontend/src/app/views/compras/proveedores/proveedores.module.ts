import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TagInputModule } from 'ngx-chips';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask';
import { ProveedoresComponent } from './proveedores.component';
import { ProveedorDetallesComponent } from './proveedor-detalles/proveedor-detalles.component';
import { ProveedorComponent } from './proveedor/proveedor.component';
import { ProveedorComprasComponent } from './proveedor/compras/proveedor-compras.component';

import { CuentasPagarComponent } from './cuentas-pagar/cuentas-pagar.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,  
    RouterModule,
    PipesModule,
    SharedModule,
    TagInputModule,
    NgSelectModule,
    NgxMaskDirective, NgxMaskPipe,
    PopoverModule.forRoot(),
    FocusModule.forRoot(),
    TooltipModule.forRoot()
  ],
  declarations: [
  	ProveedoresComponent,
    ProveedorComponent,
    ProveedorDetallesComponent,
    ProveedorComprasComponent,
    CuentasPagarComponent
  ],
  exports: [
  	ProveedoresComponent,
    ProveedorComponent,
    ProveedorDetallesComponent,
    ProveedorComprasComponent,
    CuentasPagarComponent
  ]
})
export class ProveedoresModule { }
