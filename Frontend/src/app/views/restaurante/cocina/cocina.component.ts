import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  inject,
  OnInit,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { ApiService } from '@services/api.service';
import { RestauranteService } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';
import { RestauranteRealtimeService } from '@services/restaurante-realtime.service';
import { interval } from 'rxjs';
import { nombreLineaOrden as nombreLineaOrdenFn } from '../cuenta-mesa/pos/pos-menu-nav';

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
  titulo = 'Pantalla general';
  pantallaId: number | null = null;
  ahora = Date.now();
  verdeMin = 7;
  amarilloMin = 14;
  formVerde = 7;
  formAmarillo = 14;
  mostrarTiempos = false;
  puedeEditarTiempos = false;
  guardandoTiempos = false;
  private cargaSeq = 0;
  private tituloSeq = 0;

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private realtime: RestauranteRealtimeService,
    private route: ActivatedRoute,
    private apiService: ApiService,
  ) {}

  ngOnInit(): void {
    const tipo = String(this.apiService.auth_user()?.tipo ?? '').trim();
    this.puedeEditarTiempos = tipo === 'Administrador' || tipo === 'Super Administrador';
    // Misma ruta pantalla/:id: Angular reutiliza el componente, hay que reaccionar al param.
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = params.get('id');
      this.pantallaId = id && id !== 'general' ? Number(id) : null;
      this.titulo = this.pantallaId ? 'Pantalla' : 'Pantalla general';
      this.cdr.markForCheck();
      this.cargarTitulo();
      this.cargarComandas();
    });
    this.realtime.watch('cocina', () => this.cargarComandas());
    this.realtime.onRecover(() => this.cargarComandas());
    this.cargarSemaforo();
    interval(1000).pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.ahora = Date.now();
      this.cdr.markForCheck();
    });
  }

  private cargarSemaforo(): void {
    this.restauranteService.getSemaforo().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (cfg) => {
        this.verdeMin = Number(cfg?.verde_min) || 7;
        this.amarilloMin = Number(cfg?.amarillo_min) || 14;
        this.formVerde = this.verdeMin;
        this.formAmarillo = this.amarilloMin;
        this.cdr.markForCheck();
      },
    });
  }

  guardarTiempos(): void {
    const verde = Math.floor(Number(this.formVerde));
    const amarillo = Math.floor(Number(this.formAmarillo));
    if (verde < 1 || amarillo <= verde) {
      this.alertService.warning('Tiempos', 'El amarillo tiene que terminar después del verde.');
      return;
    }
    this.guardandoTiempos = true;
    this.cdr.markForCheck();
    this.restauranteService.guardarSemaforo(verde, amarillo).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (cfg) => {
        this.verdeMin = Number(cfg?.verde_min) || verde;
        this.amarilloMin = Number(cfg?.amarillo_min) || amarillo;
        this.formVerde = this.verdeMin;
        this.formAmarillo = this.amarilloMin;
        this.guardandoTiempos = false;
        this.mostrarTiempos = false;
        this.alertService.success('Tiempos guardados', 'El semáforo de las comandas usa estos minutos.');
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.guardandoTiempos = false;
        this.alertService.error(err);
        this.cdr.markForCheck();
      },
    });
  }

  reloj(comanda: { enviado_at?: string; created_at?: string }): string {
    const inicio = this.inicioMs(comanda);
    if (inicio === null) {
      return '--:--';
    }
    const seg = Math.max(0, Math.floor((this.ahora - inicio) / 1000));
    const h = Math.floor(seg / 3600);
    const m = Math.floor((seg % 3600) / 60);
    const s = seg % 60;
    const mm = m.toString().padStart(2, '0');
    const ss = s.toString().padStart(2, '0');
    return `${h}:${mm}:${ss}`;
  }

  colorSemaforo(comanda: { enviado_at?: string; created_at?: string }): 'verde' | 'amarillo' | 'rojo' {
    const inicio = this.inicioMs(comanda);
    if (inicio === null) {
      return 'verde';
    }
    const segundos = Math.max(0, Math.floor((this.ahora - inicio) / 1000));
    if (segundos < this.verdeMin * 60) {
      return 'verde';
    }
    if (segundos < this.amarilloMin * 60) {
      return 'amarillo';
    }
    return 'rojo';
  }

  private inicioMs(comanda: { enviado_at?: string; created_at?: string }): number | null {
    const raw = comanda?.enviado_at || comanda?.created_at;
    if (!raw) {
      return null;
    }
    const t = new Date(String(raw).replace(' ', 'T')).getTime();
    return Number.isNaN(t) ? null : t;
  }

  private cargarTitulo(): void {
    const seq = ++this.tituloSeq;
    const pantallaId = this.pantallaId;
    this.restauranteService.getPantallas({ activo: true }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (filas) => {
        if (seq !== this.tituloSeq) {
          return;
        }
        const pantalla = (filas || []).find((p) => p.id === pantallaId);
        this.titulo = pantalla?.nombre || (pantallaId ? 'Pantalla' : 'Pantalla general');
        this.cdr.markForCheck();
      },
    });
  }

  private rebuildListas(): void {
    this.comandasPendientes = this.comandas.filter(
      (c) => c.estado === 'pendiente' || c.estado === 'preparando'
    );
    this.comandasListas = this.comandas.filter((c) => c.estado === 'listo');
  }

  cargarComandas(): void {
    const seq = ++this.cargaSeq;
    const pantallaId = this.pantallaId;
    this.loading = true;
    this.cdr.markForCheck();
    this.restauranteService
      .getComandas(pantallaId ?? undefined)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (comandas) => {
          if (seq !== this.cargaSeq) {
            return;
          }
          this.comandas = comandas;
          this.rebuildListas();
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          if (seq !== this.cargaSeq) {
            return;
          }
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

  nombrePantalla(comanda: { pantalla?: { nombre?: string } | null; destino?: string } | null | undefined): string {
    if (comanda?.pantalla?.nombre) {
      return comanda.pantalla.nombre;
    }
    if (comanda?.destino === 'barra') {
      return 'Barra';
    }
    if (comanda?.destino === 'cocina') {
      return 'Cocina';
    }
    return '';
  }

  nombreLineaOrden(item: { producto?: { nombre?: string } | null; presentacion?: { nombre_comercial?: string } | null } | null | undefined): string {
    return nombreLineaOrdenFn(item ?? {});
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
