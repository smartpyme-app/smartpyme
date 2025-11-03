// paywall-layout.component.ts
import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

@Component({
    selector: 'app-paywall-layout',
    templateUrl: './paywall-layout.component.html',
    styleUrls: ['./paywall-layout.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class PaywallLayoutComponent {}