import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { PermissionService } from '../../../services/permission.service';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import { BaseComponent } from '@shared/base/base.component';

@Component({
    selector: 'app-perfil',
    templateUrl: './perfil.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, LazyImageDirective],
    
})
export class PerfilComponent extends BaseComponent implements OnInit {

    public usuario:any = {};
    public rol:any = {};
    public loading:boolean = false;

    constructor(
        public apiService:ApiService,
        private alertService:AlertService,
        private permissionService: PermissionService
    ){
        super();
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.refreshDisplayRole();

        this.permissionService.onPermissionsUpdated()
            .pipe(this.untilDestroyed())
            .subscribe(() => this.refreshDisplayRole());
    }

    private refreshDisplayRole(): void {
        this.rol = this.permissionService.getDisplayRoleName();
    }

    public loadAll() {
    }

}
