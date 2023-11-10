import { Component, OnInit } from '@angular/core';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

declare let $:any;

@Component({
  selector: 'app-forget',
  templateUrl: './forget.component.html'
})
export class ForgetComponent implements OnInit {

    public user: any = {};
    public loading = false;
    public anio:any = '';

    constructor( private apiService: ApiService, private router: Router, private alertService: AlertService) { }

    ngOnInit() {
        this.anio = new Date().getFullYear();
    }

    submit() {
        this.loading = true;

        this.apiService.store('password/email', this.user)
        .subscribe(
            data => {
                this.router.navigate(['/login']);
                this.loading = false;
            },
            error => {
                $('.container').addClass("animated shake");
                this.alertService.error(error);
                this.loading = false;
            });
    }
  

}
