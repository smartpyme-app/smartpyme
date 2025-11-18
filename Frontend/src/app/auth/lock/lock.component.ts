import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

declare let $:any;

@Component({
    selector: 'app-lock',
    templateUrl: './lock.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class LockComponent implements OnInit {

    public usuarios: any = [];
    public usuario: any = {};
    public filtro: any = {};
    public user: any = {};
    public loading = false;
    public saludo:string = '';

    modalRef!: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( private apiService: ApiService, private router: Router, private alertService: AlertService, private modalService: BsModalService) { }

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

    openModal(template: TemplateRef<any>, user:any) {
        this.user = user;
        this.modalRef = this.modalService.show(template, { class: 'modal-sm' });
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
                    this.modalRef.hide();
                },
                error: (error) => {
                    $('.modal').addClass("animated shake");
                    this.alertService.error('Datos incorrectos');
                    this.loading = false;
                }
            });
    }

}
