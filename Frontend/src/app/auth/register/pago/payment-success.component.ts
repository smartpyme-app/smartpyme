// payment-success.component.ts
import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router } from '@angular/router';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';

@Component({
    selector: 'app-payment-success',
    templateUrl: './payment-success.component.html',
    styleUrls: ['./payment-success.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class PaymentSuccessComponent implements OnInit, OnDestroy {
  countdown: number = 10;
  paymentResponse: any;

  constructor(
    private router: Router, 
    private n1coPaymentService: N1coPaymentService) {}

  ngOnInit() {
    this.paymentResponse = this.n1coPaymentService.getPaymentResponse();

    if (!this.paymentResponse) {
      this.router.navigate(['/login']);
      return;
    }

    const timer = setInterval(() => {
      this.countdown--;
      if (this.countdown <= 0) {
        clearInterval(timer);
        this.n1coPaymentService.clearPaymentResponse();
        this.router.navigate(['/login']);
      }
    }, 1000);
  }

  ngOnDestroy() {
    this.n1coPaymentService.clearPaymentResponse();
  }

  goToLogin() {
    this.n1coPaymentService.clearPaymentResponse();
    this.router.navigate(['/login']);
  }
}