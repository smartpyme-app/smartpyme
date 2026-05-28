import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { LibroIvaPaisService } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';
import {
  comprasPorImpuestoResumenLibroIva,
  resumenTotalesLibroIva,
  sumaBaseDesgloseLibroIva,
  sumaComprasDesgloseLibroIva,
  sumaImpuestoDesgloseLibroIva,
  sumaVentasDesgloseLibroIva,
  totalFilaDesgloseLibroIva,
  ventasPorImpuestoResumenLibroIva,
} from '@views/contabilidad/libro-iva-shared/libro-iva-resumen.util';
import * as moment from 'moment';

@Component({
  selector: 'app-libro-iva-resumen',
  templateUrl: './libro-iva-resumen.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LibroIvaResumenComponent implements OnInit {
  readonly totalFilaDesglose = totalFilaDesgloseLibroIva;

  resumen: any = null;
  years: number[] = [];
  sucursales: any[] = [];
  loading = false;
  filtros: any = {};

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private cdr: ChangeDetectorRef,
    private libroIvaPais: LibroIvaPaisService
  ) {}

  ngOnInit(): void {
    if (this.libroIvaPais.redirigirSiPaisIncorrecto('sv', this.router)) {
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

  setTime(): void {
    this.filtros.inicio = moment([this.filtros.anio, this.filtros.mes - 1]).startOf('month').format('YYYY-MM-DD');
    this.filtros.fin = moment([this.filtros.anio, this.filtros.mes - 1]).endOf('month').format('YYYY-MM-DD');
  }

  loadResumen(): void {
    this.setTime();
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

  get resumenTotales(): { ventas: number; compras: number; compras_sin_devoluciones: number; gastos: number } {
    return resumenTotalesLibroIva(this.resumen);
  }

  get ventasPorImpuesto(): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
    return ventasPorImpuestoResumenLibroIva(this.resumen);
  }

  get comprasPorImpuesto(): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
    return comprasPorImpuestoResumenLibroIva(this.resumen);
  }

  get sumaBaseCompras(): number {
    return sumaBaseDesgloseLibroIva(this.comprasPorImpuesto);
  }

  get sumaImpuestoCompras(): number {
    return sumaImpuestoDesgloseLibroIva(this.comprasPorImpuesto);
  }

  get sumaComprasDesglose(): number {
    return sumaComprasDesgloseLibroIva(this.comprasPorImpuesto);
  }

  get desgloseComprasCuadra(): boolean {
    return Math.abs(this.sumaComprasDesglose - this.resumenTotales.compras_sin_devoluciones) < 0.02;
  }

  get sumaBaseVentas(): number {
    return sumaBaseDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get sumaImpuestoVentas(): number {
    return sumaImpuestoDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get sumaVentasDesglose(): number {
    return sumaVentasDesgloseLibroIva(this.ventasPorImpuesto);
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
