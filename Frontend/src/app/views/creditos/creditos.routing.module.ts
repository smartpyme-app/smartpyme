import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { CreditosComponent }     from '../../views/creditos/creditos.component';
import { CreditoComponent }     from '../../views/creditos/credito/credito.component';
import { PagosComponent }     from '../../views/creditos/pagos/pagos.component';
import { PlanDePagosComponent }     from '../../views/creditos/plan-de-pagos/plan-de-pagos.component';


const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        { path: 'creditos', component: CreditosComponent, title: 'Creditos' },
        { path: 'credito/:id', component: CreditoComponent },
        { path: 'pagos', component: PagosComponent, title: 'Pagos' },
        { path: 'plan-de-pagos', component: PlanDePagosComponent },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CreditosRoutingModule { }
