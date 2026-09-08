import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ChartCardComponent } from '../../dashboard/components/chart-card/chart-card.component';
import { MetricCard } from '../../dashboard/models/chart-config.model';

@Component({
  selector: 'app-prestamo-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, CurrencyPipe, ChartCardComponent],
  templateUrl: './prestamo-detalle.component.html',
})
export class PrestamoDetalleComponent implements OnInit {
  prestamo: any = null;
  formas: any[] = [];
  saving = false;
  pago: any = { fecha: '', metodo: '', n_cuotas: 1 };
  puedePagar = false;
  puedeEditar = false;
  modalRef?: BsModalRef;

  constructor(
    private route: ActivatedRoute,
    private apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
  ) {}

  ngOnInit(): void {
    this.puedePagar = this.apiService.hasPermission('finanzas.prestamos.pagar');
    this.puedeEditar = this.apiService.hasPermission('finanzas.prestamos.crear');
    this.apiService.getAll('formas-de-pago/list').subscribe({
      next: (formas) => { this.formas = formas ?? []; },
    });
    this.cargar();
  }

  get pendientes() {
    return (this.prestamo?.cuotas ?? []).filter((c: any) => c.estado !== 'pagada');
  }

  get cuotasPago() {
    const n = Math.min(Math.max(1, Number(this.pago.n_cuotas) || 1), this.pendientes.length || 1);
    return this.pendientes.slice(0, n);
  }

  get resumenPago() {
    return this.cuotasPago.reduce(
      (acc: { capital: number; interes: number; total: number }, c: any) => ({
        capital: acc.capital + Number(c.capital || 0),
        interes: acc.interes + Number(c.interes || 0),
        total: acc.total + Number(c.total || 0),
      }),
      { capital: 0, interes: 0, total: 0 },
    );
  }

  get kpis(): MetricCard[] {
    const monto = Number(this.prestamo?.monto || 0);
    const saldo = Number(this.prestamo?.saldo || 0);
    return [
      { title: 'Monto', value: monto, type: 'currency' },
      { title: 'Saldo', value: saldo, type: 'currency' },
      { title: 'Pagado', value: Math.max(monto - saldo, 0), type: 'currency' },
      { title: 'Cuotas pendientes', value: this.pendientes.length, type: 'number' },
    ];
  }

  cargar(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.apiService.read('prestamos-empresa/', id).subscribe({
      next: (p) => { this.prestamo = p; },
      error: (err) => this.alertService.error(err),
    });
  }

  abrirPago(template: TemplateRef<any>): void {
    this.pago = {
      fecha: this.pago.fecha || new Date().toISOString().slice(0, 10),
      metodo: this.pago.metodo || '',
      n_cuotas: 1,
    };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  cerrarModal(): void {
    this.modalRef?.hide();
  }

  guardarTabla(): void {
    this.saving = true;
    this.apiService.store('prestamos-empresa/' + this.prestamo.id + '/cuotas', { cuotas: this.prestamo.cuotas }).subscribe({
      next: (p) => {
        this.prestamo = p;
        this.saving = false;
        this.alertService.success('Listo', 'Tabla actualizada');
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      },
    });
  }

  registrarPago(): void {
    if (!this.pago.fecha) {
      this.alertService.error('Indique la fecha del pago.');
      return;
    }
    this.saving = true;
    this.apiService.store('prestamos-empresa/' + this.prestamo.id + '/pagos', this.pago).subscribe({
      next: () => {
        this.saving = false;
        this.pago = { fecha: this.pago.fecha, metodo: this.pago.metodo, n_cuotas: 1 };
        this.cerrarModal();
        this.alertService.success('Listo', 'Pago registrado');
        this.cargar();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      },
    });
  }

  etiquetaTipo(tipo: string): string {
    return tipo === 'institucion' ? 'Institución' : 'Persona';
  }

  etiquetaEstado(estado: string): string {
    return estado === 'pagado' ? 'Pagado' : 'Activo';
  }

  etiquetaFrecuencia(frecuencia: string): string {
    if (frecuencia === 'quincenal') return 'Quincenal';
    if (frecuencia === 'semanal') return 'Semanal';
    return 'Mensual';
  }

  etiquetaEstadoCuota(estado: string): string {
    if (estado === 'pagada') return 'Pagada';
    if (estado === 'atrasada') return 'Atrasada';
    return 'Pendiente';
  }

  claseEstadoCuota(estado: string): Record<string, boolean> {
    return {
      'bg-success': estado === 'pagada',
      'bg-danger': estado === 'atrasada',
      'bg-secondary': estado !== 'pagada' && estado !== 'atrasada',
    };
  }
}
