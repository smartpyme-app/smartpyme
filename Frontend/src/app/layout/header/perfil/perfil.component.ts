import { Component, OnInit } from '@angular/core';
import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
  selector: 'app-perfil',
  templateUrl: './perfil.component.html'
})
export class PerfilComponent implements OnInit {

    public usuario:any = {};
    public loading:boolean = false;

    constructor( public apiService:ApiService, private alertService:AlertService ){}

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
    }

    public loadAll() {
    }

}
