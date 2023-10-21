import { Component, OnInit } from '@angular/core';

import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-sidebar',
  templateUrl: './sidebar.component.html'
})

export class SidebarComponent implements OnInit {
    public isCollapsed = false;
    public usuario: any = {};

    constructor(private apiService: ApiService) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();

    }


    toggleSidebar() {
        this.isCollapsed = !this.isCollapsed;
    }


    toggleSidebarMenu() {
        if (this.isCollapsed) {
            this.isCollapsed = false;
        };
    }

}
