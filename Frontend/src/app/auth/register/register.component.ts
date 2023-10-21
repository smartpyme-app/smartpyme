import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

declare let $:any;

@Component({
  selector: 'app-register',
  templateUrl: './register.component.html'
})
export class RegisterComponent implements OnInit {

    public user: any = {};
    public loading = false;
    public saludo:string = '';

    constructor( private apiService: ApiService, private router: Router, private alertService: AlertService) { }

    ngOnInit() {
        sessionStorage.removeItem('auth_user');
        sessionStorage.removeItem('token');
    }

    register() {
        this.loading = true;
        this.apiService.register(this.user)
        .subscribe(
            data => {
                this.alertService.success("Gracias por registrarse.");
                this.router.navigate(['/']);
            },
            error => {
                $('.container').addClass("animated shake");
                this.alertService.error(error);
                this.loading = false;
            });
    }

}
