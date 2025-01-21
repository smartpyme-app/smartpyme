import { Component, OnInit, Inject } from '@angular/core';
import { DOCUMENT } from '@angular/common';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

// declare var $:any;

@Component({
  selector: 'app-header',
  templateUrl: './header.component.html'
})
export class HeaderComponent implements OnInit {

    public usuario: any = {};
    public rol: any = {};
    public filtros: any = {};
    public elem: any;
    public notificaciones: any = [];
    public isfullscreen: boolean = false;
    public isVisible: boolean = false;

     constructor(public apiService: ApiService, private alertService: AlertService, @Inject(DOCUMENT) private document: any) { }

    ngOnInit() {
        // $('.drop-down').dropdown();
        this.usuario = this.apiService.auth_user();

        let user = localStorage.getItem('SP_user_permissions');
        if (user) {
            this.rol = JSON.parse(user).role;
            this.rol = this.rol.replace(/_/g, ' ').replace(/\b\w/g, (char: string) => char.toUpperCase());
        }



        this.apiService.loadTheme();

        this.elem = document.documentElement;

        this.loadNotificaciones();
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

    public toggleSidebar(){
        const myDiv = document.getElementById('tour.sidebar')!;
        if (this.isVisible) {
          myDiv.style.marginLeft  = '-280px';
        } else {
          myDiv.style.marginLeft  = '0px';
        }
        this.isVisible = !this.isVisible;
    }

    public loadNotificaciones() {
        this.filtros.leido = 0;
        this.filtros.paginate = 1;
        this.apiService.getAll('notificaciones', this.filtros).subscribe(notificaciones => { 
            this.notificaciones = notificaciones;
        }, error => {this.alertService.error(error); });
    }


}
