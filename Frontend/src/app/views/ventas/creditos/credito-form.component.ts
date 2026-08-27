import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { generarPreviewCuotas, planCuadra, PreviewCuota, sumaMontosCuotas } from './creditos-cuotas';
import { puedeCrearCredito } from './creditos-acceso';

@Component({
  selector: 'app-credito-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NgSelectModule],
  templateUrl: './credito-form.component.html',
})
export class CreditoFormComponent implements OnInit {
  clientes: any[] = [];
  saving = false;
  preview: PreviewCuota[] = [];
  form: any = {
    id_cliente: null,
    tipo: 'bien',
    monto: null,
    n_cuotas: 2,
    fecha_inicio: '',
    tasa_interes: 0,
    tasa_mora: 0,
    concepto: '',
  };

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!puedeCrearCredito(this.apiService.isVentasLimitado())) {
      this.alertService.error('Los usuarios de tipo "Ventas Limitado" no pueden crear créditos.');
      this.router.navigate(['/clientes/creditos']);
      return;
    }

    this.apiService.getAll('clientes/list').subscribe({
      next: (clientes) => {
        this.clientes = clientes ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  get previewValido(): boolean {
    return planCuadra(Number(this.form.monto) || 0, this.preview);
  }

  get sumaPreview(): number {
    return sumaMontosCuotas(this.preview);
  }

  regenerarPreview(): void {
    this.preview = generarPreviewCuotas(
      Number(this.form.monto) || 0,
      Number(this.form.n_cuotas) || 0,
      this.form.fecha_inicio,
    );
  }

  guardar(): void {
    if (!this.form.id_cliente || !this.previewValido) {
      this.alertService.error('Complete cliente, monto y cuotas. La suma debe coincidir con el contrato.');
      return;
    }

    this.saving = true;
    this.apiService.store('creditos-clientes', { ...this.form, cuotas: this.preview }).subscribe({
      next: (contrato) => {
        this.saving = false;
        this.router.navigate(['/clientes/creditos', contrato.id]);
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      },
    });
  }
}
