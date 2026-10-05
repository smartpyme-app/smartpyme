import { Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule, ActivatedRoute } from '@angular/router';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { FE_PAIS_SV, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

declare let $: any;

@Component({
  selector: 'app-login-contadores',
  templateUrl: './login-contadores.component.html',
  styleUrls: ['./login-contadores.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NotificacionesContainerComponent],
})
export class LoginContadoresComponent implements OnInit {
  public user: any = {};
  public loading = false;
  public showpassword = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private apiService: ApiService,
    private mhService: MHService,
    private router: Router,
    private route: ActivatedRoute,
    private alertService: AlertService,
  ) {}

  ngOnInit() {
    localStorage.clear();
    this.user = { email: '', password: '' };

    if (this.route.snapshot.queryParamMap.get('passwordReset')) {
      setTimeout(() => this.alertService.success('¡Listo!', 'Tu contraseña ha sido actualizada correctamente.'));
    }
  }

  submit() {
    this.loading = true;

    this.apiService.login(this.user)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.user = this.apiService.auth_user();

          const paisFe = resolveCodigoPaisFe(this.user.empresa);
          if (paisFe === FE_PAIS_SV) {
            if (this.user.empresa.fe_ambiente == '01') {
              localStorage.setItem('SP_mh_url_base', 'https://api.dtes.mh.gob.sv');
            } else {
              localStorage.setItem('SP_mh_url_base', 'https://apitest.dtes.mh.gob.sv');
            }
            if (this.user.empresa.mh_usuario && this.user.empresa.mh_contrasena) {
              this.mhService.login();
            }
          } else {
            localStorage.removeItem('SP_mh_url_base');
            localStorage.removeItem('SP_token_mh');
          }

          this.apiService.loadData();
          this.router.navigate(['/contadores']);
          this.loading = false;
        },
        error: (error) => {
          $('.container').addClass('animated shake');
          this.alertService.error(error);
          this.loading = false;
        },
      });
  }

  public mostrarPassword() {
    this.showpassword = !this.showpassword;
  }
}
