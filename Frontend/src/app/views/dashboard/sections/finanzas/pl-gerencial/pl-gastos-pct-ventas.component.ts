import { ChangeDetectorRef, Component, Input, OnChanges, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrencyInt } from '@helpers/currency-format.helper';
import { FinanzasPlDataService, PlGerencialQuery } from '../../../services/finanzas-pl-data.service';
import { PlGastoPctVentaFila, PlGastoProveedor } from './pl-gerencial.model';
import { finalize, take } from 'rxjs/operators';

@Component({
  selector: 'app-pl-gastos-pct-ventas',
  templateUrl: './pl-gastos-pct-ventas.component.html',
  styleUrls: ['./pl-gastos-pct-ventas.component.css'],
  standalone: true,
  imports: [CommonModule],
})
export class PlGastosPctVentasComponent implements OnChanges {
  private readonly api = inject(ApiService);
  private readonly plData = inject(FinanzasPlDataService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly cache = new Map<string, PlGastoProveedor[]>();

  @Input() mesEtiqueta = 'septiembre';
  @Input() comparacionEtiqueta = 'comparación';
  @Input() query: PlGerencialQuery | null = null;
  @Input() data: { filas: PlGastoPctVentaFila[] } = { filas: [] };

  modal: PlGastoPctVentaFila | null = null;
  proveedores: PlGastoProveedor[] = [];
  cargando = false;
  error: string | null = null;

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['query'] && !changes['query'].firstChange) {
      this.cache.clear();
      this.cerrar();
    }
  }

  etiquetaMes(): string {
    return this.mesEtiqueta.trim().toLowerCase() || 'mes';
  }

  etiquetaComparacion(): string {
    return this.comparacionEtiqueta.trim().toLowerCase() || 'comparación';
  }

  anchoBarra(pct: number): number {
    const tope = this.escalaMaxPct();
    return Math.min(100, Math.max(0, (pct / tope) * 100));
  }

  formatoPct(value: number): string {
    return `${(value ?? 0).toFixed(1)}%`;
  }

  totalRubro(): number {
    return this.modal?.montoMes ?? 0;
  }

  totalProveedores(): number {
    return this.proveedores.filter((p) => !this.esCostoVendido(p.nombre)).length;
  }

  anchoProveedor(monto: number): number {
    let max = 0;
    for (const prov of this.proveedores) {
      max = Math.max(max, prov.monto ?? 0);
    }
    if (max <= 0) {
      return 0;
    }
    return Math.min(100, Math.max(4, (monto / max) * 100));
  }

  esCostoVendido(nombre: string): boolean {
    return nombre.toLowerCase().includes('productos vendidos');
  }

  abrir(fila: PlGastoPctVentaFila): void {
    const rubro = fila.rubro ?? '';
    this.modal = fila;
    this.error = null;
    const guardado = this.cache.get(rubro);
    if (guardado) {
      this.proveedores = guardado;
      this.cargando = false;
      return;
    }
    if (!this.query || !rubro) {
      this.proveedores = [];
      this.error = 'No se pudo consultar este rubro.';
      return;
    }
    this.cargando = true;
    this.proveedores = [];
    this.plData
      .obtenerProveedoresRubro(this.query, rubro, fila.montoMes ?? 0)
      .pipe(
        take(1),
        finalize(() => {
          this.cargando = false;
          this.refrescar();
        }),
      )
      .subscribe({
        next: (lista) => {
          if (this.modal?.rubro !== rubro) {
            return;
          }
          if (!lista) {
            this.error = 'No se pudieron cargar los proveedores.';
            return;
          }
          this.proveedores = lista;
          this.cache.set(rubro, lista);
        },
        error: () => {
          if (this.modal?.rubro === rubro) {
            this.error = 'No se pudieron cargar los proveedores.';
          }
        },
      });
  }

  private refrescar(): void {
    this.cdr.detectChanges();
  }

  cerrar(): void {
    this.modal = null;
    this.cargando = false;
    this.error = null;
  }

  formatMonto(value: number): string {
    return formatEmpresaCurrencyInt(value ?? 0, this.api.auth_user()?.empresa);
  }

  /** Escala al mayor % visible, con piso para que un 2% no llene la barra. */
  private escalaMaxPct(): number {
    let max = 40;
    for (const fila of this.data.filas) {
      max = Math.max(max, fila.pctMes ?? 0, fila.pctComparacion ?? 0);
    }
    return max;
  }
}
