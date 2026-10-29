import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { finalize, take } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import {
  CierreMesDetalle,
  CierreMesItem,
  CierrePresupuestoFila,
  CierreVarTono,
} from './cierre-mes.model';
import {
  CIERRE_MES_VACIA,
  FinanzasCierreMesDataService,
} from '../../../services/finanzas-cierre-mes-data.service';

const ACCION_CHECKLIST: Record<string, string> = {
  registrar_gasto: 'Registrar gasto →',
  revisar_dia: 'Revisar día →',
  asignar_costos: 'Asignar costos →',
  clasificar: 'Clasificar →',
};

@Component({
  selector: 'app-cierre-mes',
  templateUrl: './cierre-mes.component.html',
  styleUrls: ['./cierre-mes.component.css'],
  standalone: true,
  imports: [CommonModule],
})
export class CierreMesComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly cierreData = inject(FinanzasCierreMesDataService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly anio = new Date().getFullYear();
  data = { ...CIERRE_MES_VACIA, anio: this.anio };
  mesSeleccionadoId: number | null = null;
  cargando = false;
  contenidoListo = false;
  errorMensaje: string | null = null;
  private loadSeq = 0;

  ngOnInit(): void {
    this.cargar();
  }

  seleccionarMes(mes: CierreMesItem): void {
    if (mes.estado === 'pendiente' || mes.id === this.mesSeleccionadoId) {
      return;
    }
    this.mesSeleccionadoId = mes.id;
    this.cargar(mes.id + 1);
  }

  mesesCerradosConteo(): number {
    return this.data.mesesCerrados;
  }

  detalleActivo(): CierreMesDetalle | null {
    return this.data.detalle;
  }

  pendientesChecklist(detalle: CierreMesDetalle): number {
    return detalle.checklist.filter((i) => i.estado !== 'ok').length;
  }

  puedeCertificar(detalle: CierreMesDetalle): boolean {
    return detalle.puedeCertificar && this.pendientesChecklist(detalle) === 0;
  }

  etiquetaAccion(codigo: string): string {
    return ACCION_CHECKLIST[codigo] ?? codigo;
  }

  etiquetaMes(): string {
    const mes = this.data.meses.find((m) => m.id === this.mesSeleccionadoId);
    return (mes?.etiqueta ?? this.data.detalle?.mesEtiquetaCorto ?? '').toLowerCase();
  }

  formatMonto(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  formatVarMes(pct: number | null): string {
    if (pct === null) {
      return '—';
    }
    if (pct > 0) {
      return `+${pct}%`;
    }
    if (pct < 0) {
      return `${pct}%`;
    }
    return '0%';
  }

  formatVarAcum(fila: CierrePresupuestoFila): string {
    if (fila.varAcumMonto === null && fila.varAcumPct === null) {
      return '—';
    }
    const monto = fila.varAcumMonto ?? 0;
    const sign = monto < 0 ? '-' : monto > 0 ? '+' : '';
    const abs = formatEmpresaCurrency(Math.abs(monto), this.api.auth_user()?.empresa);
    return `${sign}${abs} (${this.formatVarMes(fila.varAcumPct)})`;
  }

  tonoAcum(fila: CierrePresupuestoFila): CierreVarTono {
    return this.tonoPorPct(fila.categoria, fila.varAcumPct);
  }

  claseVarTono(tono: CierreVarTono): string {
    switch (tono) {
      case 'pos':
        return 'cierre-text-pos';
      case 'neg':
        return 'cierre-text-neg';
      case 'warn':
        return 'cierre-text-warn';
      default:
        return 'cierre-text-muted';
    }
  }

  private tonoPorPct(categoria: string, pct: number | null): CierreVarTono {
    if (pct === null || pct === 0) {
      return 'neutral';
    }
    if (categoria === 'Ventas netas') {
      return pct < 0 ? 'neg' : 'pos';
    }
    if (pct >= 10) {
      return 'warn';
    }
    return pct > 0 ? 'neg' : 'pos';
  }

  private cargar(mes?: number): void {
    const seq = ++this.loadSeq;
    this.cargando = true;
    this.errorMensaje = null;
    this.cdr.markForCheck();
    this.cierreData
      .obtener({ anio: this.anio, mes })
      .pipe(
        take(1),
        finalize(() => {
          if (seq === this.loadSeq) {
            this.cargando = false;
            this.cdr.markForCheck();
          }
        }),
      )
      .subscribe((payload) => {
        if (seq !== this.loadSeq) {
          return;
        }
        if (!payload) {
          if (!this.contenidoListo) {
            this.data = { ...CIERRE_MES_VACIA, anio: this.anio };
          }
          this.errorMensaje = 'No se pudo cargar el cierre de mes. Revisa la conexión con analytics.';
          return;
        }
        this.data = payload;
        this.contenidoListo = true;
        this.errorMensaje = null;
        if (mes == null) {
          this.mesSeleccionadoId = payload.mesActivoId;
        }
      });
  }
}
