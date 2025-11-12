// paywall-routing.module.ts
import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PaywallLayoutComponent } from './layout/paywall-layout.component';

const routes: Routes = [
  {
    path: '',
    component: PaywallLayoutComponent,
    children: [
      { 
        path: '',
        loadComponent: () => import('./components/paywall.component').then(m => m.PaywallComponent),
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