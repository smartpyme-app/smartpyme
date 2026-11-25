import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TranslatePipe } from '@ngx-translate/core';
import { Subject, of, Subscription } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { CONSIGNA_MOVIMIENTO_TIPOS } from '../../../constants/consigna-movimiento-tipos';

@Component({
  selector: 'app-consigna-kardex',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NgSelectModule, CurrencyPipe, TranslatePipe],
  templateUrl: './consigna-kardex.component.html',
})
export class ConsignaKardexComponent implements OnInit, OnDestroy {
  public movimientos: any[] = [];
  public producto: any = {};
  public bodegas: any[] = [];
  public tiposMovimiento = CONSIGNA_MOVIMIENTO_TIPOS;
  public loading = false;
  public downloading = false;
  public moduloActivo = false;
  public productoSeleccionado: any = null;
  public searchProductos$ = new Subject<string>();
  public searchResults: any[] = [];
  public searchLoading = false;
  public lotes: any[] = [];
  public lotesCargando = false;
  private searchSub?: Subscription;

  public filtros: any = {
    fecha_ini: '',
    fecha_fin: '',
    id_producto: '',
    id_bodega: '',
    lote_id: '',
    tipo: '',
    alcance: 'pool',
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService,
    private route: ActivatedRoute
  ) {
    this.searchSub = this.searchProductos$.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap((term) => {
        if (!term || term.length < 2) {
          return of([]);
        }
        this.searchLoading = true;
        return this.apiService
          .store('productos/buscar-modal', {
            termino: term,
            id_empresa: this.apiService.auth_user().id_empresa,
            limite: 20,
          })
          .pipe(catchError(() => of([])));
      })
    ).subscribe((results) => {
      this.searchResults = results || [];
      this.searchLoading = false;
    });
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  ngOnInit() {
    const alcance = this.route.snapshot.queryParamMap.get('alcance');
    if (alcance === 'custodia') {
      this.filtros.alcance = 'custodia';
    }
    const hace30 = new Date();
    hace30.setDate(hace30.getDate() - 30);
    this.filtros.fecha_ini = hace30.toISOString().slice(0, 10);
    this.filtros.fecha_fin = this.apiService.date();
    this.filtros.id_bodega = this.apiService.auth_user().id_bodega ?? '';

    this.funcionalidadesService.verificarAcceso('modulo-consignaciones').subscribe({
      next: (acceso) => {
        this.moduloActivo = acceso;
        if (acceso) {
          this.apiService.getAll('bodegas/list').subscribe({
            next: (bodegas) => {
              this.bodegas = bodegas ?? [];
            },
            error: (err) => this.alertService.error(err),
          });
        }
      },
      error: () => {
        this.moduloActivo = false;
      },
    });
  }

  get opcionesBuscador(): any[] {
    if (!this.productoSeleccionado?.id) {
      return this.searchResults;
    }
    const resto = this.searchResults.filter((p: any) => p.id !== this.productoSeleccionado.id);
    return [this.productoSeleccionado, ...resto];
  }

  compareProductos(a: any, b: any): boolean {
    return a && b && a.id === b.id;
  }

  onProductoDesdeBuscador(p: any): void {
    this.filtros.lote_id = '';
    this.lotes = [];
    if (!p?.id) {
      this.filtros.id_producto = '';
      this.productoSeleccionado = null;
      this.producto = {};
      this.movimientos = [];
      return;
    }
    this.filtros.id_producto = p.id;
    this.productoSeleccionado = p;
    this.apiService.getAll(`producto/${p.id}`).subscribe({
      next: (prod) => {
        this.producto = prod ?? { ...p };
        if (this.mostrarLote) {
          this.movimientos = [];
          this.cargarLotes(() => {
            if (this.filtros.lote_id) {
              this.loadAll();
            }
          });
        } else {
          this.loadAll();
        }
      },
      error: () => {
        this.producto = { ...p, inventario_por_lotes: p.inventario_por_lotes };
        if (this.mostrarLote) {
          this.cargarLotes();
        }
      },
    });
  }

  onBodegaChange(): void {
    if (!this.filtros.id_producto) {
      return;
    }
    if (this.mostrarLote) {
      this.filtros.lote_id = '';
      this.cargarLotes();
      this.movimientos = [];
    } else {
      this.loadAll();
    }
  }

  public isLotesActivo(): boolean {
    return this.apiService.isLotesActivo();
  }

  get mostrarLote(): boolean {
    return !!(
      this.isLotesActivo()
      && (this.producto?.inventario_por_lotes || this.productoSeleccionado?.inventario_por_lotes)
    );
  }

  get lotesRequeridos(): boolean {
    return this.mostrarLote && this.lotes.length > 0;
  }

  get loteSeleccionadoNumero(): string {
    if (!this.mostrarLote || !this.filtros.lote_id || !this.lotes?.length) {
      return '—';
    }
    const lote = this.lotes.find((l: any) => String(l.id) === String(this.filtros.lote_id));
    return lote?.numero_lote ?? '—';
  }

  public cargarLotes(onReady?: () => void): void {
    if (!this.filtros.id_producto || !this.filtros.id_bodega) {
      this.lotes = [];
      this.filtros.lote_id = '';
      this.lotesCargando = false;
      onReady?.();
      return;
    }
    const loteIdActual = this.filtros.lote_id ? String(this.filtros.lote_id) : '';
    this.lotesCargando = true;
    this.apiService.getAll('productos/consigna-kardex/lotes', {
      id_producto: this.filtros.id_producto,
      id_bodega: this.filtros.id_bodega,
    }).subscribe({
      next: (lotes: any[]) => {
        this.lotes = Array.isArray(lotes) ? lotes : [];
        if (loteIdActual) {
          const loteExiste = this.lotes.some((l: any) => String(l.id) === loteIdActual);
          this.filtros.lote_id = loteExiste ? loteIdActual : '';
        } else if (this.lotes.length === 1) {
          this.filtros.lote_id = this.lotes[0].id;
        }
        this.lotesCargando = false;
        onReady?.();
      },
      error: (err) => {
        this.lotes = [];
        this.filtros.lote_id = '';
        this.lotesCargando = false;
        this.alertService.error(err);
        onReady?.();
      },
    });
  }

  get mostrarCostosInventario(): boolean {
    return this.apiService.canVerCostosInventario();
  }

  get colspanTabla(): number {
    let cols = this.mostrarLote ? 16 : 15;
    if (!this.mostrarCostosInventario) {
      cols -= 6;
    }
    return cols;
  }

  /** Misma presentación que kardex físico: cronológico + existencias corridas. */
  get filasKardex(): any[] {
    const sorted = [...this.movimientos].sort((a, b) => {
      const fa = new Date(a.fecha).getTime();
      const fb = new Date(b.fecha).getTime();
      if (fa !== fb) {
        return fa - fb;
      }
      return (a.id ?? 0) - (b.id ?? 0);
    });
    let existencia = 0;
    const filas = sorted.map((m, i) => {
      const qty = Number(m.cantidad) || 0;
      const esLiquidacion =
        m.tipo === 'liquidacion_proveedor' || m.tipo === 'liquidacion_cliente' || m.es_registro_contable === true;
      const afectaSaldo = m.afecta_saldo_consigna !== false && !esLiquidacion;
      const entrada = afectaSaldo && qty > 0 ? qty : 0;
      const salida = esLiquidacion
        ? Math.abs(qty)
        : afectaSaldo && qty < 0
          ? Math.abs(qty)
          : 0;
      const costo = Number(m.costo_unitario) || 0;
      if (afectaSaldo) {
        existencia += qty;
      }
      return {
        ...m,
        n: i + 1,
        entrada_cantidad: entrada,
        salida_cantidad: salida,
        existencia_cantidad: existencia,
        costo_unitario: costo,
        entrada_valor: entrada * costo,
        total_valor: existencia * costo,
        nombre_tercero: m.nombre_tercero ?? m.nombre_cliente_movimiento ?? m.nombre_proveedor_origen ?? '—',
      };
  });

    return filas.reverse().map((f, i) => ({ ...f, n: i + 1 }));
  }

  public filtrar(): void {
    if (!this.filtros.id_producto) {
      this.alertService.warning('Kardex consigna', 'Seleccione un producto para consultar.');
      return;
    }
    if (!this.filtros.id_bodega) {
      this.alertService.warning('Kardex consigna', 'Seleccione una bodega.');
      return;
    }
    if (this.lotesCargando) {
      return;
    }
    if (this.mostrarLote && this.lotes.length === 0) {
      this.cargarLotes(() => this.loadAll());
      return;
    }
    this.loadAll();
  }

  public loadAll() {
    if (!this.filtros.id_producto) {
      this.alertService.warning('Kardex consigna', 'Seleccione un producto para consultar.');
      return;
    }
    if (!this.filtros.id_bodega) {
      this.alertService.warning('Kardex consigna', 'Seleccione una bodega.');
      return;
    }
    if (this.lotesRequeridos && !this.filtros.lote_id) {
      this.alertService.warning('Kardex consigna', 'Seleccione un lote.');
      this.movimientos = [];
      return;
    }
  if (!this.lotesRequeridos) {
      this.filtros.lote_id = '';
    }
    this.loading = true;
    this.apiService.getAll('productos/consigna-kardex', this.filtros).subscribe({
      next: (data) => {
        this.movimientos = Array.isArray(data) ? data : data?.data ?? [];
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  public exportar() {
    if (!this.filtros.id_producto) {
      this.alertService.warning('Kardex consigna', 'Seleccione un producto para exportar.');
      return;
    }
    this.downloading = true;
    this.apiService.export('productos/consigna-kardex/exportar', this.filtros).subscribe({
      next: (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'consigna-kardex.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.downloading = false;
      },
    });
  }
}
