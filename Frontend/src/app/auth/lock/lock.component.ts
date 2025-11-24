import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router } from '@angular/router';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';
import { ModalManagerService } from '../../services/modal-manager.service';
import { BaseModalComponent } from '../../shared/base/base-modal.component';
import { LazyImageDirective } from '../../directives/lazy-image.directive';

declare let $:any;

@Component({
    selector: 'app-lock',
    templateUrl: './lock.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LazyImageDirective],
    
})
export class LockComponent extends BaseModalComponent implements OnInit {

    public usuarios: any = [];
    public usuario: any = {};
    public filtro: any = {};
    public user: any = {};
    public saludo:string = '';

    constructor( 
        private apiService: ApiService, 
        private router: Router, 
        protected override alertService: AlertService, 
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.saludo = this.apiService.saludar();
        // this.apiService.logout();
        this.usuario = this.apiService.auth_user();
        this.filtro.tipo = 'Vendedor';
        this.loading = true;
        this.apiService.store('usuarios/filtrar', this.filtro)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (usuarios) => {
                    this.usuarios = usuarios;
                    this.loading = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    override openModal(template: TemplateRef<any>, user:any) {
        this.user = user;
        super.openModal(template, { size: 'sm' });
    }

    onSubmit() {
        this.loading = true;
        // this.user.username = this.user.username.toLowerCase();
        this.user.password = this.user.password.toLowerCase();

        this.apiService.login(this.user)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (data) => {
                    this.router.navigate(['/']);
                    this.loading = false;
                    this.closeModal();
                },
                error: (error) => {
                    $('.modal').addClass("animated shake");
                    this.alertService.error('Datos incorrectos');
                    this.loading = false;
                }
            });
    }

}
