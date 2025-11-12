import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
    selector: 'app-theme',
    templateUrl: './theme.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
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
