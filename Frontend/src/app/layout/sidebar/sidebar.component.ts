import { Component, OnInit } from '@angular/core';

import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-sidebar',
  templateUrl: './sidebar.component.html'
})

export class SidebarComponent implements OnInit {
    public isCollapsed:boolean = false;
    public isCollapsedMenu:boolean = false;
    public usuario: any = {};

    constructor(private apiService: ApiService) {
        const sidebarState = localStorage.getItem('sidebarCollapsed');
          if (sidebarState !== null) {
            this.isCollapsed = sidebarState === 'true';
          }
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
    }


    toggleSidebar() {
        this.isCollapsed = !this.isCollapsed;
        localStorage.setItem('sidebarCollapsed', this.isCollapsed.toString());
    }


    toggleSidebarMenu() {
        if (this.isCollapsed) {
            this.isCollapsed = false;
        };
    }

}
