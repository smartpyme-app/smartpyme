import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';

@Component({
  selector: 'app-facturacion-pos',
  standalone: true,
  imports: [RouterModule],
  template: `
    <p>Facturación POS — en construcción</p>
    <a routerLink="/ventas">Volver a ventas</a>
  `,
})
export class FacturacionPosComponent {}
