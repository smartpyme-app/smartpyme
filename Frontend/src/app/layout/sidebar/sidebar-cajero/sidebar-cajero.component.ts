import { Component, OnInit } from '@angular/core';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-sidebar-cajero',
  templateUrl: './sidebar-cajero.component.html'
})
export class SidebarCajeroComponent implements OnInit {
  public sidebarCollapsed = false;
  public usuario: any = {};
  public isVisible = false;
  public isAbacoSite = false;

  constructor(public apiService: ApiService) {}

  ngOnInit(): void {
    this.isAbacoSite = window.location.hostname === 'abaco.smartpyme.site';
    if (!localStorage.getItem('sidebarCollapsed')) {
      localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());
    } else {
      this.sidebarCollapsed = JSON.parse(localStorage.getItem('sidebarCollapsed')!);
    }
    this.usuario = this.apiService.auth_user();
  }

  toggleSidebar(): void {
    this.sidebarCollapsed = !this.sidebarCollapsed;
    localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());
  }

  toggleSidebarMenu(): void {
    if (this.sidebarCollapsed) {
      this.sidebarCollapsed = false;
      localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());
    }
  }
}
