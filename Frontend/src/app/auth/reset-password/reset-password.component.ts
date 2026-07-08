import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-reset-password',
  templateUrl: './reset-password.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NotificacionesContainerComponent],
})
export class ResetPasswordComponent implements OnInit {

  token = '';
  email = '';
  password = '';
  password_confirmation = '';
  loading = false;
  showPassword = false;
  showPasswordConfirm = false;
  anio: number = new Date().getFullYear();

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private apiService: ApiService,
    private alertService: AlertService
  ) {}

  ngOnInit() {
    this.token = this.route.snapshot.queryParamMap.get('token') || '';
    this.email = this.route.snapshot.queryParamMap.get('email') || '';

    if (!this.token || !this.email) {
      this.alertService.error('Enlace de recuperación inválido.');
      this.router.navigate(['/login']);
    }
  }

  submit() {
    this.loading = true;

    this.apiService.store('password/reset', {
      token: this.token,
      email: this.email,
      password: this.password,
      password_confirmation: this.password_confirmation
    }).subscribe(
      () => {
        this.alertService.success('¡Listo!', 'Tu contraseña ha sido actualizada correctamente.');
        this.router.navigate(['/login'], { queryParams: { passwordReset: 1 } });
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }
}
