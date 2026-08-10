import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  inject,
  OnInit,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RestauranteService } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

@Component({
  standalone: false,
  selector: 'app-cocina',
  templateUrl: './cocina.component.html',
  styleUrls: ['./cocina.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CocinaComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  comandas: any[] = [];
  comandasPendientes: any[] = [];
  comandasListas: any[] = [];
  loading = true;
  actualizandoId: number | null = null;

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    this.cargarComandas();
  }

  private rebuildListas(): void {
    this.comandasPendientes = this.comandas.filter(
      (c) => c.estado === 'pendiente' || c.estado === 'preparando'
    );
    this.comandasListas = this.comandas.filter((c) => c.estado === 'listo');
  }

  cargarComandas(): void {
    this.loading = true;
    this.cdr.markForCheck();
    this.restauranteService
      .getComandas()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (comandas) => {
          this.comandas = comandas;
          this.rebuildListas();
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.alertService.error(err);
          this.loading = false;
          this.cdr.markForCheck();
        }
      });
  }

  cambiarEstado(comanda: any, estado: 'pendiente' | 'preparando' | 'listo' | 'servido'): void {
    this.actualizandoId = comanda.id;
    this.cdr.markForCheck();
    this.restauranteService
      .actualizarEstadoComanda(comanda.id, estado)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.actualizandoId = null;
          this.cargarComandas();
        },
        error: (err) => {
          this.alertService.error(err);
          this.actualizandoId = null;
          this.cdr.markForCheck();
        }
      });
  }

  marcarServida(comanda: any): void {
    this.cambiarEstado(comanda, 'servido');
  }

  imprimir(comanda: any): void {
    this.restauranteService
      .imprimirComanda(comanda.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
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
}
