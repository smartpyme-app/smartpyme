import { Component, OnInit } from '@angular/core';

import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
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

  constructor(
    private apiService: ApiService,
    private router: Router,
    private alertService: AlertService,
  ) {}

  ngOnInit() {
    localStorage.clear();
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
