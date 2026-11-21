import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { ConsultaSaldoComponent } from './consulta-saldo/consulta-saldo.component';

const routes: Routes = [
  {
    path: '',
    title: 'Gift Cards',
    canActivate: [FuncionalidadGuard],
    data: { funcionalidadSlug: 'gift-cards' },
    children: [
      {
        path: '',
        redirectTo: 'gift-cards/consulta',
        pathMatch: 'full',
      },
      {
        path: 'gift-cards/consulta',
        component: ConsultaSaldoComponent,
        title: 'Consulta saldo gift card',
      },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class GiftCardsRoutingModule {}
