import { Component, OnInit } from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';

declare let $:any;

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html'
})
export class LoginComponent implements OnInit {

    public user: any = {};
    public loading = false;
    public saludo:string = '';
    public anio:any = '';
    public showpassword:boolean = false;

    constructor( private apiService: ApiService, private mhService: MHService,
        private router: Router, private alertService: AlertService) { }

    ngOnInit() {
        localStorage.clear();
    }

    submit() {
        this.loading = true;

        this.apiService.login(this.user)
        .subscribe(
            data => {
                this.router.navigate(['/']);
                this.loading = false;

                this.mhService.login();
                this.apiService.loadData();
                
            },
            error => {
                $('.container').addClass("animated shake");
                this.alertService.error(error);
                this.loading = false;
            });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  

}
