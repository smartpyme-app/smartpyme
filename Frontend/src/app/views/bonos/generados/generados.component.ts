import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BonoGenerado, BonosService } from '@services/bonos.service';

@Component({
  selector: 'app-bonos-generados',
  standalone: false,
  templateUrl: './generados.component.html'
})
export class GeneradosComponent implements OnInit {
  bonos: BonoGenerado[] = [];
  loading = false;
  evaluando = false;
  procesandoId: number | null = null;

  filtroEstado = '';
  filtroPeriodoInicio = '';
  filtroPeriodoFin = '';
  evalPeriodoInicio = '';
  evalPeriodoFin = '';

  constructor(
    private bonosService: BonosService,
    private alertService: AlertService,
    private apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.loadBonos();
  }

  loadBonos(): void {
    this.loading = true;
    const filtros: Record<string, string> = {};
    if (this.filtroEstado) {
      filtros['estado'] = this.filtroEstado;
    }
    if (this.filtroPeriodoInicio) {
      filtros['periodo_inicio'] = this.filtroPeriodoInicio;
    }
    if (this.filtroPeriodoFin) {
      filtros['periodo_fin'] = this.filtroPeriodoFin;
    }

    this.bonosService.getGenerados(filtros).subscribe({
      next: (response) => {
        this.bonos = response.data ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  calcularPeriodo(): void {
    if (!confirm('¿Calcular bonos para el período seleccionado?')) {
      return;
    }

    this.evaluando = true;
    this.bonosService.evaluar(
      this.evalPeriodoInicio || undefined,
      this.evalPeriodoFin || undefined
    ).subscribe({
      next: (response) => {
        const resumen = response.data;
        const msg = [
          `Creados: ${resumen.creados}`,
          `Actualizados: ${resumen.actualizados}`,
          `Eliminados: ${resumen.eliminados ?? 0}`,
          `Omitidos (sin monto): ${resumen.omitidos_monto}`,
          `Protegidos: ${resumen.protegidos}`
        ].join(' · ');
        this.alertService.success('Éxito', response.message || msg);
        this.evaluando = false;
        this.loadBonos();
      },
      error: (error) => {
        this.alertService.error(error);
        this.evaluando = false;
      }
    });
  }

  aprobar(bono: BonoGenerado): void {
    if (!confirm(`¿Aprobar bono de ${bono.vendedor?.name || 'vendedor'} por ${bono.monto}?`)) {
      return;
    }

    this.procesandoId = bono.id;
    this.bonosService.aprobar(bono.id).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Bono aprobado.');
        this.procesandoId = null;
        this.loadBonos();
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoId = null;
      }
    });
  }

  pagar(bono: BonoGenerado): void {
    if (!confirm(`¿Marcar como pagado el bono de ${bono.vendedor?.name || 'vendedor'}?`)) {
      return;
    }

    this.procesandoId = bono.id;
    this.bonosService.pagar(bono.id).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Bono pagado.');
        this.procesandoId = null;
        this.loadBonos();
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoId = null;
      }
    });
  }

  descargarComprobante(bono: BonoGenerado): void {
    const nombre = (bono.vendedor?.name || 'vendedor').replace(/\s+/g, '-');
    this.bonosService.descargarComprobante(bono.id).subscribe({
      next: (blob) => {
        this.apiService.downloadFile(blob, `comprobante-bono-${nombre}.pdf`);
      },
      error: (error) => {
        this.alertService.error(error);
      }
    });
  }

  tieneAcciones(bono: BonoGenerado): boolean {
    return bono.estado === 'pendiente'
      || bono.estado === 'aprobado'
      || bono.estado === 'pagado';
  }

  estadoBadgeClass(estado: string): string {
    switch (estado) {
      case 'pendiente':
        return 'bg-warning text-dark';
      case 'aprobado':
        return 'bg-info';
      case 'pagado':
        return 'bg-success';
      default:
        return 'bg-secondary';
    }
  }

  formatFecha(fecha: string): string {
    return fecha ? fecha.substring(0, 10) : '';
  }
}
