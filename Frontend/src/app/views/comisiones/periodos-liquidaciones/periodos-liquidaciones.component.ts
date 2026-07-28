import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ComisionPeriodo, ComisionesService } from '@services/comisiones.service';

@Component({
  selector: 'app-periodos-liquidaciones-comisiones',
  standalone: false,
  templateUrl: './periodos-liquidaciones.component.html',
  styleUrls: ['./periodos-liquidaciones.component.css']
})
export class PeriodosLiquidacionesComponent implements OnInit {
  periodos: ComisionPeriodo[] = [];
  loading = false;
  procesandoPeriodoId: number | null = null;
  procesandoLiquidacionId: number | null = null;
  filtroEstado = '';
  expandedPeriodoId: number | null = null;

  constructor(
    private comisionesService: ComisionesService,
    private alertService: AlertService,
    private apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.loadPeriodos();
  }

  loadPeriodos(): void {
    this.loading = true;
    this.comisionesService.getPeriodos(this.filtroEstado || undefined).subscribe({
      next: (response) => {
        this.periodos = response.data ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  toggleExpand(id: number): void {
    this.expandedPeriodoId = this.expandedPeriodoId === id ? null : id;
  }

  cerrarPeriodo(periodo: ComisionPeriodo): void {
    if (!confirm(`¿Cerrar el período del ${this.formatFecha(periodo.fecha_inicio)} al ${this.formatFecha(periodo.fecha_fin)}?`)) {
      return;
    }

    this.procesandoPeriodoId = periodo.id;
    this.comisionesService.cerrarPeriodo(periodo.id).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Período cerrado.');
        this.loadPeriodos();
        this.procesandoPeriodoId = null;
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoPeriodoId = null;
      }
    });
  }

  pagarLiquidacion(liquidacionId: number): void {
    if (!confirm('¿Marcar esta liquidación como pagada?')) {
      return;
    }

    this.procesandoLiquidacionId = liquidacionId;
    this.comisionesService.pagarLiquidacion(liquidacionId).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Liquidación pagada.');
        this.loadPeriodos();
        this.procesandoLiquidacionId = null;
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoLiquidacionId = null;
      }
    });
  }

  descargarComprobante(idVendedor: number, periodoId: number, nombreVendedor: string): void {
    this.comisionesService.descargarComprobante(idVendedor, periodoId).subscribe({
      next: (blob) => {
        const filename = `comprobante-comision-${nombreVendedor.replace(/\s+/g, '-')}.pdf`;
        this.apiService.downloadFile(blob, filename);
      },
      error: (error) => {
        this.alertService.error(error);
      }
    });
  }

  estadoBadgeClass(estado: string): string {
    switch (estado) {
      case 'abierto':
        return 'bg-info';
      case 'cerrado':
        return 'bg-warning text-dark';
      case 'pagado':
        return 'bg-success';
      default:
        return 'bg-secondary';
    }
  }

  formatFecha(fecha: string): string {
    if (!fecha) {
      return '';
    }
    return fecha.substring(0, 10);
  }
}
