import { Component, OnInit, Inject } from '@angular/core';
import { DOCUMENT } from '@angular/common';
import { ApiService } from '../../services/api.service';

// declare var $:any;

@Component({
  selector: 'app-header',
  templateUrl: './header.component.html'
})
export class HeaderComponent implements OnInit {

    public usuario: any = {};
    public elem: any;
    public isfullscreen: boolean = false;

    constructor(private apiService: ApiService, @Inject(DOCUMENT) private document: any) { }

    ngOnInit() {
        // $('.drop-down').dropdown();
        this.usuario = this.apiService.auth_user();
        this.apiService.loadTheme();

        this.elem = document.documentElement;
    }

    public fullscreen() {
        if (!this.isfullscreen) {
            if (this.elem.requestFullscreen)
                this.elem.requestFullscreen();
        }else{
            if (this.document.exitFullscreen)
                this.document.exitFullscreen();
        }
        this.isfullscreen = !this.isfullscreen;
    }


}
