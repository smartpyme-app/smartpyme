import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { N1coPaymentService } from '@services/n1co/N1coPaymentService';

@Component({
  selector: 'app-payment-success',
  templateUrl: './payment-success.component.html',
  styleUrls: ['./payment-success.component.css']
})
export class PaymentSuccessPaywallComponent implements OnInit, OnDestroy {
  countdown: number = 10;
  paymentResponse: any;
  currentYear: number = new Date().getFullYear();
  private timer: any;

  constructor(
    private router: Router, 
    private n1coPaymentService: N1coPaymentService
  ) {}

  ngOnInit() {
    this.paymentResponse = this.n1coPaymentService.getPaymentResponse();

    if (!this.paymentResponse) {
      this.router.navigate(['/login']);
      return;
    }

    this.startCountdown();
  }

  ngOnDestroy() {
    this.clearTimer();
    this.n1coPaymentService.clearPaymentResponse();
  }

  private startCountdown() {
    this.timer = setInterval(() => {
      this.countdown--;
      if (this.countdown <= 0) {
        this.clearTimer();
        this.goToLogin();
      }
    }, 1000);
  }

  private clearTimer() {
    if (this.timer) {
      clearInterval(this.timer);
    }
  }

  goToLogin() {
    this.clearTimer();
    this.n1coPaymentService.clearPaymentResponse();
    this.router.navigate(['/login']);
  }

  getMessage(): string {
    if (this.paymentResponse?.success) {
      return `Tu pago ha sido procesado exitosamente. Tu suscripción ha sido ${
        this.paymentResponse.isRenewal ? 'renovada' : 'activada'
      }.`;
    }
    return this.paymentResponse?.message || 'Ha ocurrido un error en el procesamiento del pago.';
  }
}