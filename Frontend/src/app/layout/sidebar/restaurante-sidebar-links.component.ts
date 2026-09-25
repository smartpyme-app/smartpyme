import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { PantallaRestaurante, RestauranteService } from '@services/restaurante.service';

@Component({
  selector: 'app-restaurante-sidebar-links',
  standalone: true,
  imports: [CommonModule, RouterModule],
  styles: [':host { display: contents; }'],
  template: `
    <li routerLinkActive="active" [routerLinkActiveOptions]="{exact: true}">
      <a [routerLink]="['/restaurante/pantallas']" class="link-body-emphasis d-inline-flex text-decoration-none">
        <i class="fa fa-tv me-2"></i> Pantallas
      </a>
    </li>
    <li routerLinkActive="active" [routerLinkActiveOptions]="{exact: true}">
      <a [routerLink]="['/restaurante/pantalla/general']" class="link-body-emphasis d-inline-flex text-decoration-none">
        <img src="/assets/icons/coffee.png" class="icon me-2">Pantalla general
      </a>
    </li>
    @for (pantalla of pantallas; track pantalla.id) {
      <li routerLinkActive="active" [routerLinkActiveOptions]="{exact: true}">
        <a [routerLink]="['/restaurante/pantalla', pantalla.id]" class="link-body-emphasis d-inline-flex text-decoration-none">
          <img src="/assets/icons/coffee.png" class="icon me-2">{{ pantalla.nombre }}
        </a>
      </li>
    }
  `,
})
export class RestauranteSidebarLinksComponent implements OnInit {
  pantallas: PantallaRestaurante[] = [];

  constructor(private restauranteService: RestauranteService) {}

  ngOnInit(): void {
    this.restauranteService.getPantallas({ activo: true }).subscribe({
      next: (filas) => {
        this.pantallas = filas || [];
      },
      error: () => {
        this.pantallas = [];
      },
    });
  }
}
