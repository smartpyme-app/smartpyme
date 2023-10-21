import { Component, OnInit } from '@angular/core';
import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
  selector: 'app-theme',
  templateUrl: './theme.component.html'
})
export class ThemeComponent implements OnInit {

    public file:any;
    public loading:boolean = false;

    constructor( public apiService:ApiService, private alertService:AlertService ){}

    ngOnInit() {
        this.apiService.loadTheme();
    }

    public loadAll() {
    }

}
