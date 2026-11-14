import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaNavComponent } from '../planilla-nav/planilla-nav.component';

interface ErrorSpu {
  fila: number | null;
  empleado: string | null;
  columna: string;
  mensaje: string;
}

@Component({
  selector: 'app-planilla-spu',
  standalone: true,
  templateUrl: './planilla-spu.component.html',
  imports: [CommonModule, FormsModule, PlanillaNavComponent],
})
export class PlanillaSpuComponent implements OnInit {
  readonly codigos = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
  readonly instituciones = ['COF', 'MAX', 'ISP', 'ISS'];
  readonly tiposDocumento = [
    { id: '01', nombre: 'DUI' },
    { id: '02', nombre: 'Pasaporte' },
    { id: '03', nombre: 'Carné de minoridad' },
    { id: '04', nombre: 'Carné de residente' },
  ];
  readonly meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

  filtros = { anio: new Date().getFullYear(), mes: new Date().getMonth() + 1, id_sucursal: null as number | null, complementaria: false };
  empleador: any = { numero_patronal_isss: '', tipo_persona: null, regimen_especial_isss: null, sucursales: [] };
  filas: any[] = [];
  errores: ErrorSpu[] = [];
  cargado = false;
  validado = false;
  loading = false;
  guardandoEmpleador = false;

  constructor(public apiService: ApiService, private alertService: AlertService, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.apiService.getAll('planillas/spu/empleador').subscribe({
      next: (data) => {
        this.empleador = data;
        this.filtros.id_sucursal = data.sucursales?.[0]?.id ?? null;
        this.cdr.markForCheck();
      },
      error: (error) => this.alertService.error(error),
    });
  }

  get periodo(): string {
    return String(this.filtros.mes).padStart(2, '0') + this.filtros.anio;
  }

  get erroresGenerales(): ErrorSpu[] {
    return this.errores.filter((e) => e.fila === null);
  }

  erroresDeFila(indice: number): ErrorSpu[] {
    return this.errores.filter((e) => e.fila === indice + 1);
  }

  guardarEmpleador(): void {
    this.guardandoEmpleador = true;
    this.apiService.store('planillas/spu/empleador', this.empleador).subscribe({
      next: (data) => {
        this.empleador = data;
        this.guardandoEmpleador = false;
        this.validado = false;
        this.alertService.success('Datos guardados', 'Datos ISSS del empleador actualizados.');
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.guardandoEmpleador = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  cargar(): void {
    this.loading = true;
    this.apiService.getAll('planillas/spu/preview', { ...this.filtros, complementaria: this.filtros.complementaria ? 1 : 0 }).subscribe({
      next: (data) => {
        this.filas = data.filas;
        this.cargado = true;
        this.aplicarErrores(data.errores);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.loading = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  validar(): void {
    this.loading = true;
    this.apiService.store('planillas/spu/validar', this.cuerpo()).subscribe({
      next: (data) => {
        this.aplicarErrores(data.errores);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.loading = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  descargar(): void {
    this.loading = true;
    this.apiService.exportPost('planillas/spu/csv', this.cuerpo()).subscribe({
      next: (blob) => {
        this.apiService.downloadFile(blob, `planilla-unica-${this.periodo}.csv`);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.loading = false;
        this.validado = false;
        this.alertService.error(error);
        this.cdr.markForCheck();
      },
    });
  }

  cambio(): void {
    this.validado = false;
  }

  duplicar(indice: number): void {
    this.filas.splice(indice + 1, 0, { ...this.filas[indice], advertencias: [] });
    this.cambio();
  }

  quitar(indice: number): void {
    this.filas.splice(indice, 1);
    this.cambio();
  }

  private cuerpo() {
    return { ...this.filtros, complementaria: this.filtros.complementaria ? 1 : 0, filas: this.filas };
  }

  private aplicarErrores(errores: ErrorSpu[] | undefined): void {
    this.errores = errores ?? [];
    this.validado = this.errores.length === 0 && this.filas.length > 0;
  }
}
