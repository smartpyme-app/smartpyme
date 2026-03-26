import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ModalModule } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';

import { PedidosShellComponent } from './pedidos-shell.component';
import { PedidosListaComponent } from './pedidos-lista/pedidos-lista.component';
import { PedidoFormComponent } from './pedido-form/pedido-form.component';
import { PedidosRoutingModule } from './pedidos-routing.module';

@NgModule({
  declarations: [
    PedidosShellComponent,
    PedidosListaComponent,
    PedidoFormComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    ModalModule.forRoot(),
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    SharedModule,
    PedidosRoutingModule
  ]
})
export class PedidosModule { }
