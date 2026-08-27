import { Component, OnInit } from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PromocionalService, CodigoPromocional } from '@services/promocional.service';
declare let $: any;

@Component({
  selector: 'app-login-onvo',
  standalone: false,
  templateUrl: './login-onvo.component.html',
  styleUrls: ['./login-onvo.component.css'],
})
export class LoginOnvoComponent implements OnInit {
  public user: any = {};
  public loading = false;
  public saludo: string = '';
  public anio: any = '';
  public showpassword: boolean = false;
  public codigoPromocionalOnvo: CodigoPromocional | null = null;

  private readonly CAMPANIA_ONVO = 'ONVO';

  constructor(
    private apiService: ApiService,
    private router: Router,
    private route: ActivatedRoute,
    private alertService: AlertService,
    private promocionalService: PromocionalService,
  ) { }

  ngOnInit() {
    localStorage.clear();
    this.cargarCodigoPromocionalOnvo();

    if (this.route.snapshot.queryParamMap.get('passwordReset')) {
      setTimeout(() => this.alertService.success('¡Listo!', 'Tu contraseña ha sido actualizada correctamente.'));
    }
  }

  private cargarCodigoPromocionalOnvo(): void {
    this.promocionalService.obtenerPorCampania(this.CAMPANIA_ONVO).subscribe((codigo) => {
      this.codigoPromocionalOnvo = codigo;
    });
  }

  public get queryParamsRegistro(): { promo?: string } {
    if (this.codigoPromocionalOnvo?.codigo) {
      return { promo: this.codigoPromocionalOnvo.codigo };
    }
    return {};
  }

  submit() {
    this.loading = true;

    this.apiService.login(this.user).subscribe(
      (data) => {
        this.user = this.apiService.auth_user();

        setTimeout(() => {
          this.apiService.loadData();
        }, 2000);

        this.router.navigate(['/']);
        this.loading = false;
      },
      (error) => {
        $('.container').addClass('animated shake');
        this.alertService.error(error);
        this.loading = false;
      },
    );
  }

  public mostrarPassword() {
    this.showpassword = !this.showpassword;
  }
}
