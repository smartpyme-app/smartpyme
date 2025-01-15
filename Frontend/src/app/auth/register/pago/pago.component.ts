import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

declare let $:any;

@Component({
  selector: 'app-pago',
  templateUrl: './pago.component.html'
})
export class PagoComponent implements OnInit {

    public user: any = {};
    public loading = false;
    public saludo:string = '';
    public anio:any = '';
    public showpassword:boolean = false;

    constructor( private apiService: ApiService, private router: Router,
        private alertService: AlertService) { }

    ngOnInit() {
        this.user = this.apiService.register_user();
    }

    submit() {
        this.loading = true;

        this.apiService.register(this.user)
        .subscribe(
            data => {
                this.router.navigate(['/pago']);
                this.loading = false;
            },
            error => {
                this.alertService.error(error);
                this.loading = false;
            });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  
    
    public checkout(){
        let URL = this.apiService.baseUrl + '/payment/' + this.user.empresa.id;
        // window.open(URL, '_parent');
        window.open(this.user.url_n1co + '/?callbackurl=' + URL,'_self');
    }

    public backToHome(){
        this.router.navigate(['/']);
    }

}
