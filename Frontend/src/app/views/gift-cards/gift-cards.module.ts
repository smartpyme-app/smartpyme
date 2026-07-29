import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';

import { GiftCardsRoutingModule } from './gift-cards-routing.module';
import { ConsultaSaldoComponent } from './consulta-saldo/consulta-saldo.component';

@NgModule({
  declarations: [ConsultaSaldoComponent],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    GiftCardsRoutingModule,
    SharedModule,
    PipesModule,
  ],
})
export class GiftCardsModule {}
