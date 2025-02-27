// src/app/components/shared/ver-historial-button/ver-historial-button.component.ts
import { Component, Input } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-ver-historial-button',
  template: `
    <a (click)="verHistorial()" class="list-group-item list-group-item-action border-0 click">
      <i class="fa fa-fw fa-magnifying-glass"></i> Ver historial
    </a>
  `
})
export class VerHistorialButtonComponent {
  @Input() empleado: any;

  constructor(private router: Router) {}

  verHistorial() {
    this.router.navigate(['/planilla/empleado/editar', this.empleado.id]).then(() => {
      // Usamos setTimeout para asegurar que el componente esté cargado
      setTimeout(() => {
        // Enviamos un evento personalizado para cambiar la pestaña
        window.dispatchEvent(new CustomEvent('cambiarTabHistorial'));
      }, 100);
    });
  }
}