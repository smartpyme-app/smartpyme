import { Component } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-layout',
  templateUrl: './layout.component.html',
  styleUrls: ['./layout.component.css']
})

export class LayoutComponent  {
    public usuario: any = {};
    public elem: any;
    public isfullscreen: boolean = false;
    public isVisible: boolean = false;

    constructor(public apiService: ApiService, public alertService: AlertService, private router: Router) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
    }

    RedirectSuscripcion() {
        this.router.navigate(['/suscripcion']);
    }

}
