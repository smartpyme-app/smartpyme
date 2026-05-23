import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import * as moment from 'moment';

@Component({
  selector: 'app-libro-iva-resumen',
  templateUrl: './libro-iva-resumen.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LibroIvaResumenComponent implements OnInit {
  resumen: any = null;
  years: number[] = [];
  sucursales: any[] = [];
  loading = false;
  filtros: any = {};

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    const pais = this.apiService.auth_user()?.empresa?.pais ?? '';
    if (pais !== 'El Salvador') {
      void this.router.navigate(['/libro-iva/general']);
      return;
    }
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;
    for (let i = 0; i <= 10; i++) {
      this.years.push(currentYear - i);
    }
    this.filtros.id_sucursal = '';
    this.filtros.anio = currentYear;
    this.filtros.mes = currentMonth;
    this.setTime();

    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );
    this.loadResumen();
  }

  setTime(): boolean {
    const anio = Number(this.filtros.anio);
    const mes = Number(this.filtros.mes);
    if (!Number.isFinite(anio) || !Number.isFinite(mes) || mes < 1 || mes > 12) {
      return false;
    }
    const base = moment([anio, mes - 1]);
    if (!base.isValid()) {
      return false;
    }
    this.filtros.inicio = base.clone().startOf('month').format('YYYY-MM-DD');
    this.filtros.fin = base.clone().endOf('month').format('YYYY-MM-DD');
    return true;
  }

  loadResumen(): void {
    if (!this.setTime()) {
      this.alertService.error({
        status: 422,
        error: { errors: { inicio: ['Seleccione un mes y año válidos.'] } },
      });
      return;
    }
    this.loading = true;
    this.apiService.getAll('libro-iva/resumen-fiscal', this.filtros).subscribe(
      (data) => {
        this.resumen = data;
        this.loading = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );
  }

  get resumenTotales(): { ventas: number; compras: number; gastos: number } {
    const t = this.resumen?.totales;
    return {
      ventas: Number(t?.ventas ?? 0),
      compras: Number(t?.compras ?? 0),
      gastos: Number(t?.gastos ?? 0),
    };
  }

  get ventasPorImpuesto(): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
    return Array.isArray(this.resumen?.ventas_por_impuesto) ? this.resumen.ventas_por_impuesto : [];
  }

  get sumaVentasDesglose(): number {
    return this.ventasPorImpuesto.reduce((s, r) => s + Number(r.base ?? 0) + Number(r.iva ?? 0), 0);
  }

  get desgloseCuadraConTotalVentas(): boolean {
    return Math.abs(this.sumaVentasDesglose - this.resumenTotales.ventas) < 0.02;
  }

  get resumenIva(): {
    iva_a_favor: number;
    iva_en_contra: number;
    diferencia_estimada_pago_iva: number;
    credito_fiscal_compras: number | null;
    credito_fiscal_gastos: number | null;
    credito_fiscal_devoluciones_compras: number | null;
  } {
    const i = this.resumen?.iva;
    return {
      iva_a_favor: Number(i?.iva_a_favor ?? 0),
      iva_en_contra: Number(i?.iva_en_contra ?? 0),
      diferencia_estimada_pago_iva: Number(i?.diferencia_estimada_pago_iva ?? 0),
      credito_fiscal_compras: i?.credito_fiscal_compras != null ? Number(i.credito_fiscal_compras) : null,
      credito_fiscal_gastos: i?.credito_fiscal_gastos != null ? Number(i.credito_fiscal_gastos) : null,
      credito_fiscal_devoluciones_compras:
        i?.credito_fiscal_devoluciones_compras != null ? Number(i.credito_fiscal_devoluciones_compras) : null,
    };
  }

  get pagoCuentaIva(): { aplica: boolean; monto: number; descripcion: string } {
    const p = this.resumen?.pago_a_cuenta_iva;
    return {
      aplica: Boolean(p?.aplica),
      monto: Number(p?.monto ?? 0),
      descripcion: String(p?.descripcion ?? ''),
    };
  }
}
