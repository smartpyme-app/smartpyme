// paywall-routing.module.ts
import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PaywallLayoutComponent } from './layout/paywall-layout.component';
import { PaywallComponent } from './components/paywall.component';

const routes: Routes = [
  {
    path: '',
    component: PaywallLayoutComponent,
    children: [
      { 
        path: '',
        component: PaywallComponent,
        title: 'Renovar Suscripción'
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PaywallRoutingModule { }