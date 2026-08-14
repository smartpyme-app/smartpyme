import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ComisionEstimadoVolumen, ComisionPeriodo, ComisionesService } from '@services/comisiones.service';

@Component({
  selector: 'app-periodo-detalle-comisiones',
  standalone: false,
  templateUrl: './periodo-detalle.component.html'
})
export class PeriodoDetalleComponent implements OnInit {
  periodo: ComisionPeriodo | null = null;
  loading = false;
  procesandoLiquidacionId: number | null = null;
  periodoId = 0;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private comisionesService: ComisionesService,
    private alertService: AlertService,
    private apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.periodoId = Number(this.route.snapshot.paramMap.get('id'));
    if (!this.periodoId) {
      this.router.navigate(['/comisiones/periodos']);
      return;
    }
    this.loadPeriodo();
  }

  loadPeriodo(): void {
    this.loading = true;
    this.comisionesService.getPeriodo(this.periodoId).subscribe({
      next: (response) => {
        this.periodo = response?.data ?? response ?? null;
        this.loading = false;
        if (!this.periodo) {
          this.alertService.warning('Atención', 'No se encontró el período.');
          this.router.navigate(['/comisiones/periodos']);
        }
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.router.navigate(['/comisiones/periodos']);
      }
    });
  }

  volver(): void {
    this.router.navigate(['/comisiones/periodos']);
  }

  pagarLiquidacion(liquidacionId: number): void {
    if (!confirm('¿Marcar esta liquidación como pagada?')) {
      return;
    }

    this.procesandoLiquidacionId = liquidacionId;
    this.comisionesService.pagarLiquidacion(liquidacionId).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Liquidación pagada.');
        this.loadPeriodo();
        this.procesandoLiquidacionId = null;
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoLiquidacionId = null;
      }
    });
  }

  descargarComprobante(idVendedor: number, nombreVendedor: string): void {
    if (!this.periodo) {
      return;
    }
    this.comisionesService.descargarComprobante(idVendedor, this.periodo.id).subscribe({
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

  get estimados(): ComisionEstimadoVolumen[] {
    return this.periodo?.estimado ?? [];
  }

  nombreVendedorEstimado(item: ComisionEstimadoVolumen): string {
    const liq = this.periodo?.liquidaciones?.find((l) => l.id_vendedor === item.id_vendedor);
    return liq?.vendedor?.name || `Vendedor #${item.id_vendedor}`;
  }
}
