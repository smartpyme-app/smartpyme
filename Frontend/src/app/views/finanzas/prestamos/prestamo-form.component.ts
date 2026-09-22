import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { CrearProveedorComponent } from '@shared/modals/crear-proveedor/crear-proveedor.component';
import { getEmpresaCurrencySymbol } from '@helpers/currency-format.helper';

@Component({
  selector: 'app-prestamo-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NgSelectModule, CrearProveedorComponent],
  templateUrl: './prestamo-form.component.html',
})
export class PrestamoFormComponent implements OnInit {
  cuentas: any[] = [];
  proveedores: any[] = [];
  preview: any[] = [];
  saving = false;
  loadingPreview = false;
  form: any = {
    historico: false,
    id_proveedor: null,
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
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
  ) {}

  get simboloMoneda(): string {
    return getEmpresaCurrencySymbol(this.apiService.auth_user()?.empresa);
  }

  /** SP-2215: asiento contable solo para roles de contabilidad. */
  get puedeVerGenerarAsiento(): boolean {
    return (
      this.apiService.validateRole('usuario_contador', true) ||
      this.apiService.validateRole('contador_superior', true) ||
      this.apiService.validateRole('contador_auxiliar', true) ||
      this.apiService.validateRole('admin', true) ||
      this.apiService.validateRole('super_admin', true)
    );
  }

  ngOnInit(): void {
    this.apiService.getAll('banco/cuentas/list').subscribe({
      next: (cuentas) => {
        this.cuentas = cuentas ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
    this.apiService.getAll('proveedores/list').subscribe({
      next: (proveedores) => {
        this.proveedores = proveedores ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  setProveedor(id: number): void {
    this.form.id_proveedor = id;
    if (!this.proveedores.some((p) => p.id === id)) {
      this.apiService.getAll('proveedores/list').subscribe({
        next: (proveedores) => {
          this.proveedores = proveedores ?? [];
        },
      });
    }
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
    if (!this.form.id_proveedor || !this.form.monto || this.preview.length < 2) {
      this.alertService.error('Seleccione acreedor, indique monto y genere la tabla.');
      return;
    }
    const payload = { ...this.form, cuotas: this.preview };
    if (!this.puedeVerGenerarAsiento) {
      payload.generar_asiento_desembolso = !this.form.historico;
    }
    this.saving = true;
    this.apiService.store('prestamos-empresa', payload).subscribe({
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
