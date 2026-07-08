import { Component, OnInit } from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PromocionalService, CodigoPromocional } from '@services/promocional.service';
declare let $: any;

@Component({
  selector: 'app-login-abaco',
  standalone: false,
  templateUrl: './login-abaco.component.html',
  styleUrls: ['./login-abaco.component.css'],
})
export class LoginAbacoComponent implements OnInit {
  public user: any = {};
  public loading = false;
  public saludo: string = '';
  public anio: any = '';
  public showpassword: boolean = false;
  public codigoPromocionalAbaco: CodigoPromocional | null = null;

  private readonly CAMPANIA_ABACO = 'ÁBACO';

  constructor(
    private apiService: ApiService,
    private router: Router,
    private route: ActivatedRoute,
    private alertService: AlertService,
    private promocionalService: PromocionalService,
  ) {}

  ngOnInit() {
    localStorage.clear();
    this.cargarCodigoPromocionalAbaco();

    if (this.route.snapshot.queryParamMap.get('passwordReset')) {
      setTimeout(() => this.alertService.success('¡Listo!', 'Tu contraseña ha sido actualizada correctamente.'));
    }
  }

  private cargarCodigoPromocionalAbaco(): void {
    this.promocionalService.obtenerPorCampania(this.CAMPANIA_ABACO).subscribe((codigo) => {
      this.codigoPromocionalAbaco = codigo;
    });
  }

  public get queryParamsRegistro(): { promo?: string } {
    if (this.codigoPromocionalAbaco?.codigo) {
      return { promo: this.codigoPromocionalAbaco.codigo };
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
