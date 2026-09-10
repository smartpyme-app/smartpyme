import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-prestamo-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NgSelectModule],
  templateUrl: './prestamo-form.component.html',
})
export class PrestamoFormComponent implements OnInit {
  cuentas: any[] = [];
  preview: any[] = [];
  saving = false;
  loadingPreview = false;
  form: any = {
    historico: false,
    tipo_acreedor: 'institucion',
    acreedor: '',
    concepto: '',
    monto: null,
    monto_original: null,
    genera_interes: false,
    tasa_interes: 0,
    n_cuotas: 2,
    frecuencia: 'mensual',
    fecha_desembolso: '',
    fecha_primera_cuota: '',
    id_cuenta_banco: null,
    generar_asiento_desembolso: true,
  };

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.apiService.getAll('banco/cuentas/list').subscribe({
      next: (cuentas) => {
        this.cuentas = cuentas ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  onHistoricoChange(): void {
    this.form.generar_asiento_desembolso = !this.form.historico;
  }

  regenerarPreview(): void {
    if (!this.form.monto || this.form.n_cuotas < 2 || !this.form.fecha_primera_cuota) {
      this.preview = [];
      return;
    }
    this.loadingPreview = true;
    this.apiService.store('prestamos-empresa/preview', {
      monto: this.form.monto,
      n_cuotas: this.form.n_cuotas,
      fecha_primera_cuota: this.form.fecha_primera_cuota,
      frecuencia: this.form.frecuencia,
      genera_interes: this.form.genera_interes,
      tasa_interes: this.form.tasa_interes,
    }).subscribe({
      next: (res) => {
        this.preview = res?.cuotas ?? [];
        this.loadingPreview = false;
      },
      error: (err) => {
        this.loadingPreview = false;
        this.alertService.error(err);
      },
    });
  }

  guardar(): void {
    if (!this.form.acreedor || !this.form.monto || this.preview.length < 2) {
      this.alertService.error('Complete acreedor, monto y genere la tabla.');
      return;
    }
    this.saving = true;
    this.apiService.store('prestamos-empresa', { ...this.form, cuotas: this.preview }).subscribe({
      next: (prestamo) => {
        this.saving = false;
        this.router.navigate(['/finanzas/prestamos', prestamo.id]);
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      },
    });
  }
}
