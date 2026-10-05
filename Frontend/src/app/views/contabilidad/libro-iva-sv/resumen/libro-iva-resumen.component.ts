import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
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
  ventasResumenContableLibroIva,
  mostrarVentasResumenContableLibroIva,
  resumenIvaLibroIva,
  pagoCuentaIvaResumenLibroIva,
} from '@views/contabilidad/libro-iva-shared/libro-iva-resumen.util';
import * as moment from 'moment';
import { LibroIvaResumenDescargasComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-resumen-descargas.component';

@Component({
  selector: 'app-libro-iva-resumen',
  templateUrl: './libro-iva-resumen.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule, CurrencyPipe, LibroIvaResumenDescargasComponent],
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

  get sumaBaseVentas(): number {
    return sumaBaseDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get sumaImpuestoVentas(): number {
    return sumaImpuestoDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get sumaVentasDesglose(): number {
    return sumaVentasDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get resumenIva() {
    return resumenIvaLibroIva(this.resumen);
  }

  get pagoCuentaIva() {
    return pagoCuentaIvaResumenLibroIva(this.resumen);
  }

  get ventasResumenContable() {
    return ventasResumenContableLibroIva(this.resumen);
  }

  get mostrarVentasResumenContable(): boolean {
    return mostrarVentasResumenContableLibroIva(this.resumen);
  }
}
