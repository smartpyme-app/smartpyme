import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';

import { TranslatePipe } from '@ngx-translate/core';

@Component({
  selector: 'app-dte-management',
  templateUrl: './dte-management.component.html',
  standalone: true,
  imports: [CommonModule, RouterModule, TranslatePipe],
})
export class DteManagementComponent {}
