import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../directives/lazy-image.directive';

declare let $:any;

@Component({
    selector: 'app-login',
    templateUrl: './login.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule, NotificacionesContainerComponent, LazyImageDirective]
})
export class LoginComponent implements OnInit {

    public user: any = {};
    public loading = false;
    public saludo:string = '';
    public anio:any = '';
    public showpassword:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( private apiService: ApiService, private mhService: MHService,
        private router: Router, private alertService: AlertService) { }

    ngOnInit() {
        const returnUrl = localStorage.getItem('returnUrl');
        localStorage.clear();

        if (returnUrl) {
            localStorage.setItem('returnUrl', returnUrl);
        }  

        this.user = { email: '', password: '' };
    }

    submit() {
        this.loading = true;
    
        this.apiService.login(this.user)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (data) => {
                    const returnUrl = localStorage.getItem('returnUrl') || '/';
                    localStorage.removeItem('returnUrl');
        
                    this.user = this.apiService.auth_user();
        
                    if(this.user.empresa.fe_ambiente == '01'){
                        localStorage.setItem('SP_mh_url_base', 'https://api.dtes.mh.gob.sv');
                    }else{
                        localStorage.setItem('SP_mh_url_base', 'https://apitest.dtes.mh.gob.sv');
                    }
        
                    if(this.user.empresa.mh_usuario && this.user.empresa.mh_contrasena){
                        this.mhService.login();
                    }
        
                    this.apiService.loadData();
                    setTimeout(() => {
                        this.router.navigate([returnUrl]);
                    }, 100);
                    
                    this.loading = false;
                },
                error: (error) => {
                    $('.container').addClass("animated shake");
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  

}
