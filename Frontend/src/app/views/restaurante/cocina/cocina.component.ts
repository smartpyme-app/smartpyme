import { Component, OnInit } from '@angular/core';
import { RestauranteService } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-cocina',
  templateUrl: './cocina.component.html',
  styleUrls: ['./cocina.component.css']
})
export class CocinaComponent implements OnInit {
  comandas: any[] = [];
  loading = true;
  actualizandoId: number | null = null;

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    this.cargarComandas();
  }

  cargarComandas(): void {
    this.loading = true;
    this.restauranteService.getComandas().subscribe({
      next: (comandas) => {
        this.comandas = comandas;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  cambiarEstado(comanda: any, estado: 'pendiente' | 'preparando' | 'listo'): void {
    this.actualizandoId = comanda.id;
    this.restauranteService.actualizarEstadoComanda(comanda.id, estado).subscribe({
      next: () => {
        this.actualizandoId = null;
        this.cargarComandas();
      },
      error: (err) => {
        this.alertService.error(err);
        this.actualizandoId = null;
      }
    });
  }

  imprimir(comanda: any): void {
    this.restauranteService.imprimirComanda(comanda.id).subscribe({
      next: (html) => {
        const w = window.open('', '_blank', 'width=400,height=600');
        if (w) {
          w.document.write(html);
          w.document.close();
          w.focus();
        }
      },
      error: (err) => this.alertService.error(err)
    });
  }

  getComandasPendientes(): any[] {
    return this.comandas.filter(c => c.estado === 'pendiente' || c.estado === 'preparando');
  }

  getComandasListas(): any[] {
    return this.comandas.filter(c => c.estado === 'listo');
  }
}
