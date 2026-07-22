import { Component, OnDestroy, OnInit, TemplateRef } from '@angular/core';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { of, Subject, Subscription } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, map, switchMap } from 'rxjs/operators';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import {
  Comision,
  ComisionFiltros,
  ComisionOrigen,
  ComisionSaveResponse,
  ComisionSummary,
} from '../../../models/comision.interface';

@Component({
  selector: 'app-comisiones',
  templateUrl: './comisiones.component.html',
})
export class ComisionesComponent implements OnInit, OnDestroy {
  comisiones: any = { data: [], total: 0 };
  summary: ComisionSummary = { cantidad: 0, total_comisiones: 0, por_vendedor: [] };
  vendedores: any[] = [];
  categorias: any[] = [];
  loading = false;
  loadingSummary = false;
  saving = false;
  filtrado = false;

  searchVentas$ = new Subject<string>();
  ventasResultados: any[] = [];
  ventasLoading = false;
  ventaSeleccionada: any = null;
  private searchVentasSub?: Subscription;

  filtros: ComisionFiltros = {
    id_vendedor: null,
    fecha_inicio: '',
    fecha_fin: '',
    correlativo_referencia: '',
    origen: '',
    paginate: 25,
  };

  comision: Comision = this.nuevaComision();
  origenes: { value: ComisionOrigen; label: string }[] = [
    { value: 'venta', label: 'Venta' },
    { value: 'manual', label: 'Manual' },
    { value: 'canje_tarjeta', label: 'Canje tarjeta' },
  ];

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {
    this.searchVentasSub = this.searchVentas$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((term) => {
          const q = (term || '').trim();
          if (q.length < 1) {
            this.ventasLoading = false;
            return of([]);
          }
          this.ventasLoading = true;
          return this.apiService.getAll('ventas', {
            buscador: q,
            paginate: 15,
            orden: 'fecha',
            direccion: 'desc',
          }).pipe(
            map((res: any) => res?.data ?? (Array.isArray(res) ? res : [])),
            catchError(() => of([]))
          );
        })
      )
      .subscribe((rows) => {
        this.ventasResultados = rows || [];
        this.ventasLoading = false;
      });
  }

  ngOnInit(): void {
    this.cargarVendedores();
    this.cargarCategorias();
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.searchVentasSub?.unsubscribe();
  }

  get opcionesVentas(): any[] {
    if (!this.ventaSeleccionada) {
      return this.ventasResultados;
    }
    const id = this.ventaSeleccionada.id;
    const resto = this.ventasResultados.filter((v) => v.id !== id);
    return [this.ventaSeleccionada, ...resto];
  }

  get montoComisionPreview(): number {
    const base = Number(this.comision.base_calculo) || 0;
    const tasa = Number(this.comision.tasa_comision) || 0;
    return Math.round(base * (tasa / 100) * 100) / 100;
  }

  loadAll(): void {
    this.filtros = {
      id_vendedor: null,
      fecha_inicio: '',
      fecha_fin: '',
      correlativo_referencia: '',
      origen: '',
      paginate: this.filtros.paginate || 25,
    };
    this.filtrado = false;
    this.filtrar();
  }

  filtrar(): void {
    this.loading = true;
    this.filtrado = this.tieneFiltrosActivos();
    this.apiService.getAll('planillas/comisiones', this.paramsFiltro()).subscribe({
      next: (res) => {
        this.comisiones = res?.data ? res : { data: res ?? [], total: res?.length ?? 0 };
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
    this.cargarSummary();
  }

  openFilter(template: TemplateRef<any>): void {
    this.modalRef = this.modalService.show(template, { class: 'modal-md' });
  }

  aplicarFiltros(): void {
    this.filtrar();
    this.modalRef?.hide();
  }

  cargarSummary(): void {
    this.loadingSummary = true;
    this.apiService.getAll('planillas/comisiones/summary', this.paramsFiltro()).subscribe({
      next: (res: ComisionSummary) => {
        this.summary = res ?? { cantidad: 0, total_comisiones: 0, por_vendedor: [] };
        this.loadingSummary = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loadingSummary = false;
      },
    });
  }

  cargarVendedores(): void {
    this.apiService.getAll('usuarios/list').subscribe({
      next: (res: any) => {
        this.vendedores = Array.isArray(res) ? res : res?.data ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  cargarCategorias(): void {
    this.apiService.getAll('categorias/list').subscribe({
      next: (res: any) => {
        this.categorias = Array.isArray(res) ? res : res?.data ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  openModal(template: TemplateRef<any>, item: Partial<Comision> | null = null): void {
    this.comision = item?.id ? ({ ...item } as Comision) : this.nuevaComision();
    this.ventasResultados = [];
    this.ventaSeleccionada = this.comision.correlativo_referencia
      ? {
          id: this.comision.id_venta || null,
          correlativo: this.comision.correlativo_referencia,
          nombre_documento: 'Ref.',
          total: this.comision.base_calculo,
        }
      : null;
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  closeModal(): void {
    this.alertService.modal = false;
    this.modalRef?.hide();
    this.ventaSeleccionada = null;
    this.ventasResultados = [];
  }

  onVentaSeleccionada(venta: any): void {
    if (!venta) {
      this.comision.correlativo_referencia = '';
      return;
    }

    if (typeof venta === 'string') {
      this.comision.correlativo_referencia = venta.trim();
      this.ventaSeleccionada = {
        id: null,
        correlativo: this.comision.correlativo_referencia,
        nombre_documento: 'Ref.',
      };
      return;
    }

    this.ventaSeleccionada = venta;
    this.comision.correlativo_referencia =
      venta.correlativo != null ? String(venta.correlativo) : '';

    if (!this.comision.base_calculo && venta.total != null) {
      this.comision.base_calculo = Number(venta.total);
    }
    if (!this.comision.id_vendedor && venta.id_vendedor) {
      this.comision.id_vendedor = venta.id_vendedor;
    }
  }

  labelVenta(v: any): string {
    if (!v) {
      return '';
    }
    if (typeof v === 'string') {
      return v;
    }
    const doc = v.nombre_documento || v.documento?.nombre || 'Venta';
    const corr = v.correlativo != null ? v.correlativo : '—';
    const cliente =
      v.cliente?.nombre_empresa ||
      v.cliente?.nombre_completo ||
      v.nombre_cliente ||
      '';
    const total = v.total != null ? ` · ${Number(v.total).toFixed(2)}` : '';
    return cliente ? `${doc} #${corr} · ${cliente}${total}` : `${doc} #${corr}${total}`;
  }

  compareVentas = (a: any, b: any): boolean => {
    if (!a || !b) {
      return a === b;
    }
    if (a.id && b.id) {
      return a.id === b.id;
    }
    return String(a.correlativo) === String(b.correlativo);
  };

  addTagCorrelativo = (term: string) => term?.trim() || null;

  onSubmit(): void {
    if (!this.comision.id_vendedor || !this.comision.base_calculo || this.comision.tasa_comision == null) {
      this.alertService.error('Complete vendedor, base de cálculo y tasa de comisión');
      return;
    }

    this.saving = true;
    const payload = {
      id_vendedor: this.comision.id_vendedor,
      origen: this.comision.origen,
      correlativo_referencia: this.comision.correlativo_referencia || null,
      categoria: this.comision.categoria || null,
      base_calculo: this.comision.base_calculo,
      tasa_comision: this.comision.tasa_comision,
      fecha: this.comision.fecha,
      notas: this.comision.notas || null,
    };

    const req$ = this.comision.id
      ? this.apiService.update('planillas/comisiones', this.comision.id, payload)
      : this.apiService.store('planillas/comisiones', payload);

    req$.subscribe({
      next: (res: ComisionSaveResponse) => {
        if (res?.advertencia) {
          this.alertService.warning('Aviso', res.advertencia);
        } else {
          this.alertService.success(
            'Éxito',
            this.comision.id ? 'Comisión actualizada' : 'Comisión registrada'
          );
        }
        this.saving = false;
        this.closeModal();
        this.filtrar();
      },
      error: (err) => {
        this.alertService.error(err);
        this.saving = false;
      },
    });
  }

  delete(id: number): void {
    if (!confirm('¿Desea eliminar el registro de comisión?')) {
      return;
    }
    this.apiService.delete('planillas/comisiones/', id).subscribe({
      next: () => {
        this.alertService.success('Éxito', 'Comisión eliminada');
        this.filtrar();
      },
      error: (err) => this.alertService.error(err),
    });
  }

  limpiarFiltros(): void {
    this.loadAll();
  }

  private tieneFiltrosActivos(): boolean {
    return !!(
      this.filtros.id_vendedor ||
      this.filtros.fecha_inicio ||
      this.filtros.fecha_fin ||
      this.filtros.correlativo_referencia ||
      this.filtros.origen
    );
  }

  setPagination(event: any): void {
    this.loading = true;
    const url = this.comisiones?.path
      ? this.comisiones.path + '?page=' + event.page
      : this.apiService.apiUrl + 'planillas/comisiones?page=' + event.page;

    this.apiService.paginate(url, this.paramsFiltro()).subscribe({
      next: (res) => {
        this.comisiones = res;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  getNombreVendedor(v: any): string {
    if (!v) {
      return '';
    }
    return v.name || v.username || '';
  }

  labelOrigen(origen: string): string {
    return this.origenes.find((o) => o.value === origen)?.label || origen;
  }

  private paramsFiltro(): any {
    const params: any = { paginate: this.filtros.paginate || 25 };
    if (this.filtros.id_vendedor) {
      params.id_vendedor = this.filtros.id_vendedor;
    }
    if (this.filtros.fecha_inicio) {
      params.fecha_inicio = this.filtros.fecha_inicio;
    }
    if (this.filtros.fecha_fin) {
      params.fecha_fin = this.filtros.fecha_fin;
    }
    if (this.filtros.correlativo_referencia) {
      params.correlativo_referencia = this.filtros.correlativo_referencia;
    }
    if (this.filtros.origen) {
      params.origen = this.filtros.origen;
    }
    return params;
  }

  private nuevaComision(): Comision {
    return {
      id_vendedor: null as any,
      origen: 'venta',
      correlativo_referencia: '',
      categoria: '',
      base_calculo: null as any,
      tasa_comision: null as any,
      fecha: new Date().toISOString().slice(0, 10),
      notas: '',
    };
  }
}
