import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BonoGenerado, BonoManualPayload, BonoRegla, BonosService } from '@services/bonos.service';

@Component({
  selector: 'app-bonos-generados',
  standalone: false,
  templateUrl: './generados.component.html'
})
export class GeneradosComponent implements OnInit {
  bonos: BonoGenerado[] = [];
  reglasManuales: BonoRegla[] = [];
  vendedores: { id: number; name: string }[] = [];
  loading = false;
  evaluando = false;
  procesandoId: number | null = null;
  mostrandoManual = false;
  guardandoManual = false;
  formManual: BonoManualPayload = this.formularioManualVacio();

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
    this.loadVendedores();
    this.loadReglasManuales();
    this.loadBonos();
  }

  loadVendedores(): void {
    this.apiService.getAll('usuarios/list').subscribe({
      next: (usuarios: any) => {
        this.vendedores = Array.isArray(usuarios) ? usuarios : (usuarios?.data ?? []);
      },
      error: () => {
        this.vendedores = [];
      }
    });
  }

  loadReglasManuales(): void {
    this.bonosService.getReglas(true).subscribe({
      next: (response) => {
        this.reglasManuales = (response.data ?? []).filter((r) => r.tipo === 'cualitativo_manual');
      },
      error: () => {
        this.reglasManuales = [];
      }
    });
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

  get puedeAsignarManual(): boolean {
    return this.reglasManuales.length > 0;
  }

  abrirManual(): void {
    this.formManual = this.formularioManualVacio();
    if (this.reglasManuales.length === 1) {
      this.formManual.id_regla = this.reglasManuales[0].id;
    }
    this.mostrandoManual = true;
  }

  cancelarManual(): void {
    this.mostrandoManual = false;
    this.formManual = this.formularioManualVacio();
  }

  guardarManual(): void {
    if (!this.formManual.id_regla || !this.formManual.id_vendedor) {
      this.alertService.warning('Atención', 'Seleccione regla y vendedor.');
      return;
    }
    if (!this.formManual.periodo_inicio || !this.formManual.periodo_fin) {
      this.alertService.warning('Atención', 'Indique el período.');
      return;
    }
    if (!this.formManual.monto || this.formManual.monto <= 0) {
      this.alertService.warning('Atención', 'Ingrese un monto mayor a 0.');
      return;
    }

    this.guardandoManual = true;
    this.bonosService.crearManual(this.formManual).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Bono manual creado.');
        this.guardandoManual = false;
        this.cancelarManual();
        this.loadBonos();
      },
      error: (error) => {
        this.alertService.error(error);
        this.guardandoManual = false;
      }
    });
  }

  private formularioManualVacio(): BonoManualPayload {
    return {
      id_regla: 0,
      id_vendedor: 0,
      periodo_inicio: '',
      periodo_fin: '',
      monto: 0
    };
  }
}
