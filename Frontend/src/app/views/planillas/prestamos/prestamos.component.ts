import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-prestamos',
  templateUrl: './prestamos.component.html',
})
export class PrestamosComponent implements OnInit {
  tabActivo: 'listado' | 'estado-cuenta' | 'crear' | 'abono' = 'listado';

  // Estado de cuenta
  empleados: any[] = [];
  empleadoSeleccionado: any = null;
  estadoCuenta: any = null;
  loadingEmpleados = false;
  loadingEstadoCuenta = false;

  // Listado préstamos (para pestaña o filtros)
  prestamos: any = { data: [], total: 0 };
  loadingPrestamos = false;
  filtros: any = { id_empleado: '', estado: '', paginate: 15 };

  // Crear préstamo
  formPrestamo: any = {
    id_empleado: null,
    monto_inicial: null,
    descripcion: 'Préstamo personal autorizado',
    fecha_desembolso: new Date().toISOString().slice(0, 10),
  };
  savingPrestamo = false;
  empleadosParaSelect: any[] = [];

  // Abono
  formAbono: any = {
    id_prestamo: null,
    monto: null,
    tipo: 'abono_efectivo',
    descripcion: 'Abono según recibo efectivo',
    fecha: new Date().toISOString().slice(0, 10),
  };
  prestamosParaAbono: any[] = [];
  empleadoParaAbono: any = null;
  savingAbono = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService
  ) {}

  ngOnInit() {
    this.cargarEmpleados();
    this.cargarPrestamos();
  }

  setTab(tab: 'listado' | 'estado-cuenta' | 'crear' | 'abono') {
    this.tabActivo = tab;
    if (tab === 'crear') {
      this.cargarEmpleadosParaSelect();
    }
    if (tab === 'abono') {
      this.prestamosParaAbono = [];
      this.empleadoParaAbono = null;
      this.formAbono = {
        id_prestamo: null,
        monto: null,
        tipo: 'abono_efectivo',
        descripcion: 'Abono según recibo efectivo',
        fecha: new Date().toISOString().slice(0, 10),
      };
    }
  }

  cargarEmpleados() {
    this.loadingEmpleados = true;
    this.apiService.getAll('empleados', { paginate: 500, estado: 1 }).subscribe({
      next: (res: any) => {
        this.empleados = res?.data ?? res ?? [];
        if (Array.isArray(res) && res.length) this.empleados = res;
        this.loadingEmpleados = false;
      },
      error: () => {
        this.loadingEmpleados = false;
      },
    });
  }

  cargarEmpleadosParaSelect() {
    this.apiService.getAll('empleados', { paginate: 500, estado: 1 }).subscribe({
      next: (res: any) => {
        this.empleadosParaSelect = res?.data ?? res ?? [];
        if (Array.isArray(res) && res.length) this.empleadosParaSelect = res;
      },
    });
  }

  cargarPrestamos() {
    this.loadingPrestamos = true;
    const params: any = { paginate: this.filtros.paginate };
    if (this.filtros.id_empleado) params.id_empleado = this.filtros.id_empleado;
    if (this.filtros.estado) params.estado = this.filtros.estado;
    this.apiService.getAll('planillas/prestamos', params).subscribe({
      next: (res: any) => {
        this.prestamos = res?.data ? res : { data: res, total: (res?.length ?? 0) };
        this.loadingPrestamos = false;
      },
      error: () => {
        this.loadingPrestamos = false;
      },
    });
  }

  cargarEstadoCuenta() {
    if (!this.empleadoSeleccionado?.id) {
      this.alertService.error('Seleccione un empleado');
      return;
    }
    this.loadingEstadoCuenta = true;
    this.estadoCuenta = null;
    this.apiService
      .getAll('planillas/prestamos/estado-cuenta', { id_empleado: this.empleadoSeleccionado.id })
      .subscribe({
        next: (data: any) => {
          this.estadoCuenta = data;
          this.loadingEstadoCuenta = false;
        },
        error: (err) => {
          this.alertService.error(err);
          this.loadingEstadoCuenta = false;
        },
      });
  }

  onEmpleadoChangeCrear() {
    this.formPrestamo.id_empleado = this.formPrestamo.id_empleado;
  }

  crearPrestamo() {
    if (!this.formPrestamo.id_empleado || !this.formPrestamo.monto_inicial || this.formPrestamo.monto_inicial <= 0) {
      this.alertService.error('Seleccione empleado e ingrese un monto mayor a cero');
      return;
    }
    this.savingPrestamo = true;
    this.apiService.store('planillas/prestamos', this.formPrestamo).subscribe({
      next: () => {
        this.alertService.success('Éxito', 'Préstamo creado correctamente');
        this.formPrestamo = {
          id_empleado: null,
          monto_inicial: null,
          descripcion: 'Préstamo personal autorizado',
          fecha_desembolso: new Date().toISOString().slice(0, 10),
        };
        this.setTab('listado');
        this.cargarPrestamos();
        this.savingPrestamo = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.savingPrestamo = false;
      },
    });
  }

  cargarPrestamosActivosEmpleado() {
    if (!this.empleadoParaAbono?.id) {
      this.prestamosParaAbono = [];
      return;
    }
    const id = this.empleadoParaAbono.id;
    this.apiService
      .getAll(`planillas/prestamos/empleado/${id}/prestamos-activos`, {})
      .subscribe({
        next: (data: any) => {
          this.prestamosParaAbono = Array.isArray(data) ? data : (data?.data ?? []);
        },
        error: () => {
          this.prestamosParaAbono = [];
        },
      });
  }

  registrarAbono() {
    if (!this.formAbono.id_prestamo || !this.formAbono.monto || this.formAbono.monto <= 0) {
      this.alertService.error('Seleccione préstamo e ingrese un monto mayor a cero');
      return;
    }
    this.savingAbono = true;
    this.apiService.store('planillas/prestamos/abono', this.formAbono).subscribe({
      next: () => {
        this.alertService.success('Éxito', 'Abono registrado correctamente');
        this.formAbono = {
          id_prestamo: null,
          monto: null,
          tipo: 'abono_efectivo',
          descripcion: 'Abono según recibo efectivo',
          fecha: new Date().toISOString().slice(0, 10),
        };
        this.prestamosParaAbono = [];
        this.empleadoParaAbono = null;
        this.setTab('listado');
        this.cargarPrestamos();
        this.savingAbono = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.savingAbono = false;
      },
    });
  }

  getNombreEmpleado(e: any) {
    if (!e) return '';
    return [e.nombres, e.apellidos].filter(Boolean).join(' ');
  }

  setPagination(event: any): void {
    this.loadingPrestamos = true;
    const url = this.prestamos?.path ? this.prestamos.path + '?page=' + event.page : (this.apiService.apiUrl + 'planillas/prestamos?page=' + event.page);
    const params: any = { paginate: this.filtros.paginate };
    if (this.filtros.id_empleado) params.id_empleado = this.filtros.id_empleado;
    if (this.filtros.estado) params.estado = this.filtros.estado;
    this.apiService.paginate(url, params).subscribe({
      next: (res: any) => {
        this.prestamos = res;
        this.loadingPrestamos = false;
      },
      error: () => {
        this.loadingPrestamos = false;
      },
    });
  }

  getEstadoPrestamoClass(estado: string): string {
    if (estado === 'activo') return 'bg-warning text-dark';
    if (estado === 'liquidado') return 'bg-success';
    if (estado === 'cancelado') return 'bg-secondary';
    return 'bg-light text-dark';
  }

  getEstadoPrestamoNombre(estado: string): string {
    if (estado === 'activo') return 'Activo';
    if (estado === 'liquidado') return 'Liquidado';
    if (estado === 'cancelado') return 'Cancelado';
    return estado || '';
  }
}
