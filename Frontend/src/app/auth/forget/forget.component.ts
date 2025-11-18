import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

declare let $:any;

@Component({
    selector: 'app-forget',
    templateUrl: './forget.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NotificacionesContainerComponent],
    
})
export class ForgetComponent implements OnInit {

    public user: any = {};
    public loading = false;
    public anio:any = '';

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( private apiService: ApiService, private router: Router, private alertService: AlertService) { }

    ngOnInit() {
        this.anio = new Date().getFullYear();
    }

    submit() {
        this.loading = true;

        this.apiService.store('password/email', this.user)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (data) => {
                    this.alertService.success('Enviado', '¡Te hemos enviado por correo el enlace para restablecer tu contraseña!');
                    // this.router.navigate(['/login']);
                    this.loading = false;
                },
                error: (error) => {
                    $('.container').addClass("animated shake");
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }
  

}
