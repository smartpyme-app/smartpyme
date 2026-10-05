import { Component } from '@angular/core';

@Component({
  selector: 'app-contadores-portafolio',
  standalone: true,
  template: `
    <main class="contadores-portafolio">
      <header>
        <p>SmartPyme Contadores</p>
        <h1>Empresas</h1>
      </header>
      <p>El listado de empresas asignadas se conecta en la siguiente fase.</p>
    </main>
  `,
  styles: [`
    .contadores-portafolio {
      min-height: 100vh;
      padding: 48px 32px;
      background: #f6f8fb;
      color: #16324f;
    }
    header p {
      margin: 0 0 8px;
      color: #1775e5;
      font-weight: 600;
    }
    h1 { margin: 0 0 16px; }
  `],
})
export class ContadoresPortafolioComponent {}
