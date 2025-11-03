import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
    selector: 'app-perfil',
    templateUrl: './perfil.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class PerfilComponent implements OnInit {

    public usuario:any = {};
    public rol:any = {};
    public loading:boolean = false;

    constructor( public apiService:ApiService, private alertService:AlertService ){}

    ngOnInit() {
        this.usuario = this.apiService.auth_user();

        let user = localStorage.getItem('SP_user_permissions');
        if (user) {
            this.rol = JSON.parse(user).role;
            this.rol = this.rol.replace(/_/g, ' ').replace(/\b\w/g, (char: string) => char.toUpperCase());
        }
    }

    public loadAll() {
    }

}
