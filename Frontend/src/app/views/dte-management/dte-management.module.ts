import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { CollapseModule } from 'ngx-bootstrap/collapse';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';

import { DteManagementRoutingModule } from './dte-management.routing.module';

import { DteManagementComponent } from './dte-management.component';
import { EmailAccountsComponent } from './email-accounts/email-accounts.component';
import { SyncDashboardComponent } from './sync-dashboard/sync-dashboard.component';
import { DteInboxComponent } from './dte-inbox/dte-inbox.component';
import { DteDetailComponent } from './dte-detail/dte-detail.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    NgSelectModule,
    DteManagementRoutingModule,
    TooltipModule.forRoot(),
    ModalModule.forRoot(),
    CollapseModule.forRoot()
  ],
  declarations: [
    DteManagementComponent,
    EmailAccountsComponent,
    SyncDashboardComponent,
    DteInboxComponent,
    DteDetailComponent
  ],
  exports: [
    DteManagementComponent,
    EmailAccountsComponent,
    SyncDashboardComponent,
    DteInboxComponent,
    DteDetailComponent
  ]
})
export class DteManagementModule {}
