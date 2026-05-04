import { Component, OnInit, TemplateRef, ViewChild, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Router, ActivatedRoute } from '@angular/router';
import { ModalManagerService } from '@services/modal-manager.service';
import { HttpCacheService } from '@services/http-cache.service';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';
import { SharedModule } from '@shared/shared.module';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-partidas',
    templateUrl: './partidas.component.html',
    styleUrls: ['./partidas.component.scss'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PopoverModule, NgSelectModule, SharedModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PartidasComponent extends BasePaginatedModalComponent implements OnInit {
  public partidas: any = {}; // Usar any porque tiene propiedades adicionales (total_anuladas, total_pendientes, totales_generales)
  public partida: any = {};
  public reporte = {
    fecha_inicio: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    fecha_fin: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0],
    concepto: '',
    cuenta: '',
    tipo_descarga: 'pdf',
    tipo_cuenta: 'all',
    /** Incluir columna y período anterior inmediato (misma duración) en Estado de resultados. */
    estadoCompararAnterior: false,
  };
  public catalogo: any = [];
  public months: Array<{ value: number; label: string }> = [];
  public years: number[] = [];
  public selectedMonth: number = new Date().getMonth() + 1;
  public selectedYear: number = new Date().getFullYear();

  // NUEVO: Para funcionalidad de reordenamiento
  public reordenamiento = {
    anio: new Date().getFullYear(), // Cambiar año por anio
    mes: new Date().getMonth() + 1,
    tipo: 'Ingreso'
  };

  /**
   * Catálogo para reportes: "Todas" + cuentas con etiqueta "código — nombre".
   */
  get opcionesTipoCuentaReporte(): Array<{ value: string | number; label: string }> {
    const opciones: Array<{ value: string | number; label: string }> = [
      { value: 'all', label: 'Todas las cuentas' },
    ];
    if (!Array.isArray(this.catalogo)) {
      return opciones;
    }
    for (const c of this.catalogo) {
      const codigo = c?.codigo ?? '';
      const nombre = c?.nombre ?? '';
      opciones.push({
        value: c.id,
        label: codigo ? `${codigo} — ${nombre}` : nombre || String(c.id),
      });
    }
    return opciones;
  }

  // NUEVO: Para mostrar totales
  public totalesGenerales: any = {
    gran_total_debe: 0,
    gran_total_haber: 0,
    total_registros_filtrados: 0
  };

  // NUEVO: Clave para persistir filtros
  private readonly FILTROS_STORAGE_KEY = 'partidas_filtros_v1';

  constructor(
    protected override apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private cacheService: HttpCacheService,
    private router: Router,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) {
    super(apiService, alertService, modalManager);
  }

  protected getPaginatedData(): PaginatedResponse | null {
    return this.partidas;
  }

  protected setPaginatedData(data: PaginatedResponse): void {
    this.partidas = data as any; // Cast a any para mantener propiedades adicionales
  }

  protected override onPaginateSuccess(response: PaginatedResponse): void {
    // Actualizar totales al paginar
    this.totalesGenerales = (response as any).totales_generales || this.totalesGenerales;
  }

  ngOnInit() {
    this.apiService.getAll('catalogo/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (catalogo) => {
        this.catalogo = catalogo;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    // NUEVO: Cargar filtros persistidos antes de loadAll
    this.cargarFiltrosPersistidos();
    this.loadAll();
    this.generateMonths();
    this.generateYears();
  }

  generateMonths() {
    this.months = [
      { value: 1, label: 'Enero' },
      { value: 2, label: 'Febrero' },
      { value: 3, label: 'Marzo' },
      { value: 4, label: 'Abril' },
      { value: 5, label: 'Mayo' },
      { value: 6, label: 'Junio' },
      { value: 7, label: 'Julio' },
      { value: 8, label: 'Agosto' },
      { value: 9, label: 'Septiembre' },
      { value: 10, label: 'Octubre' },
      { value: 11, label: 'Noviembre' },
      { value: 12, label: 'Diciembre' }
    ];
  }

  generateYears() {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 5; i <= currentYear + 2; i++) {
      this.years.push(i);
    }
  }

  public onYearChange() {
    // Método llamado cuando cambia el año en los formularios
    // Puede implementarse lógica adicional aquí si es necesario
  }

  /**
   * NUEVO: Cargar filtros desde sessionStorage
   */
  private cargarFiltrosPersistidos() {
    try {
      const filtrosGuardados = sessionStorage.getItem(this.FILTROS_STORAGE_KEY);
      if (filtrosGuardados) {
        this.filtros = JSON.parse(filtrosGuardados);
      } else {
        this.inicializarFiltrosDefault();
      }
    } catch (error) {
      console.error('Error cargando filtros:', error);
      this.inicializarFiltrosDefault();
    }
  }

  /**
   * NUEVO: Guardar filtros en sessionStorage
   */
  private guardarFiltros() {
    try {
      sessionStorage.setItem(this.FILTROS_STORAGE_KEY, JSON.stringify(this.filtros));
    } catch (error) {
      console.error('Error guardando filtros:', error);
    }
  }

  /**
   * NUEVO: Filtros por defecto
   */
  private inicializarFiltrosDefault() {
    this.filtros = {
      tipo: '',
      buscador: '',
      orden: 'correlativo', // NUEVO: Orden por correlativo por defecto
      direccion: 'desc',
      paginate: 25,
      page: 1,
      estado: '',
      incluir_anuladas: false // NUEVO: No mostrar anuladas por defecto
    };
  }

  public setOrden(columna: string) {
    if (this.filtros.columna == columna) {
      this.filtros.orden = this.filtros.orden == 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = 'asc';
    }
    this.filtros.columna = columna;
    this.filtrarPartidas();
  }

  public loadAll() {
    if (!this.filtros.orden) {
      this.inicializarFiltrosDefault();
    }

    this.filtrarPartidas();

    const today = new Date();
    this.reporte.fecha_inicio = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
    this.reporte.fecha_fin = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
    this.reporte.tipo_descarga = 'pdf';
    this.reporte.tipo_cuenta = 'all';
    this.reporte.concepto = '';
  }

  public filtrarPartidas(options?: { keepPage?: boolean }) {
    if (!options?.keepPage) {
      this.filtros.page = 1;
    }

    this.loading = true;

    this.guardarFiltros();

    this.apiService.getAll('partidas', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response: any) => {
        this.partidas = response;

        // NUEVO: Guardar totales generales
        this.totalesGenerales = response.totales_generales || {
          gran_total_debe: 0,
          gran_total_haber: 0,
          total_registros_filtrados: 0
        };

        this.loading = false;
        if (this.modalRef) {
          this.closeModal();
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public override openModal(template: TemplateRef<any>, partida: any) {
    this.partida = partida;
    super.openModal(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public openFilter(template: TemplateRef<any>) {
    this.openModalConfig(template, {
      class: 'modal-xl',
      backdrop: 'static',
      keyboard: false,
    });
  }

  /**
   * NUEVO: Modal para reordenar correlativos
   */
  public openReordenarModal(template: TemplateRef<any>) {
    this.openModalConfig(template, {
      class: 'modal-md',
      backdrop: 'static',
    });
  }

  public setEstado(partida: any, estado: any) {
    this.apiService.read('partida/', partida.id)
      .pipe(this.untilDestroyed())
      .subscribe(
      (partidaCompleta: any) => {
        partidaCompleta.estado = estado;
        this.onSubmit(partidaCompleta);
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public async setEstadoChange(partida: any) {
    try {
      await this.apiService.store('partida', partida)
        .pipe(this.untilDestroyed())
        .toPromise();

      // Invalidar cache del item específico y listas relacionadas
      if (partida?.id) {
        this.cacheService.delete(`/partida/${partida.id}`);
      }
      this.cacheService.invalidatePattern('/partidas');
      this.cacheService.invalidatePattern('/partida');

      this.alertService.success(
        'Partida actualizada',
        'El estado de la partida fue actualizado.'
      );
    } catch (error: any) {
      this.alertService.error(error);
    }
  }

  public setPagination(event: { page: number }): void {
    this.filtros.page = event.page;
    this.filtrarPartidas({ keepPage: true });
  }

  public async delete(partida: any) {
    const result = await Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar',
    });

    if (result.isConfirmed) {
      try {
        const data: any = await this.apiService.delete('partida/', partida.id)
          .pipe(this.untilDestroyed())
          .toPromise();

        // Invalidar cache del item eliminado y listas relacionadas
        if (partida?.id) {
          this.cacheService.delete(`/partida/${partida.id}`);
        }
        this.cacheService.invalidatePattern('/partidas');
        this.cacheService.invalidatePattern('/partida');

        for (let i = 0; i < this.partidas.data.length; i++) {
          if (this.partidas.data[i].id == data?.id)
            this.partidas.data.splice(i, 1);
        }
      } catch (error: any) {
        this.alertService.error(error);
      }
    }
  }

  public async onSubmit(partidaData?: any) {
    this.saving = true;
    try {
      const partidaToSave = partidaData || this.partida;
      const partidaGuardada: any = await this.apiService.store('partida', partidaToSave)
        .pipe(this.untilDestroyed())
        .toPromise();

      // Invalidar cache del item específico si se está editando
      const isNew = !partidaToSave.id;
      if (!isNew && partidaGuardada?.id) {
        this.cacheService.delete(`/partida/${partidaGuardada.id}`);
      }
      // Invalidar cache de listas relacionadas
      this.cacheService.invalidatePattern('/partidas');
      this.cacheService.invalidatePattern('/partida');

      if (isNew) {
        this.loadAll();
        this.alertService.success(
          'Partida creada',
          'El partida fue añadida exitosamente.'
        );
      } else {
        this.alertService.success(
          'Partida guardada',
          'El partida fue guardada exitosamente.'
        );
      }

      if (this.modalRef) {
        this.closeModal();
      }
      this.cdr.markForCheck();
    } catch (error: any) {
      this.alertService.error(error);
    } finally {
      this.saving = false;
      this.cdr.markForCheck();
    }
  }

  /**
   * NUEVO: Reordenar correlativos
   */
  public reordenarCorrelativos() {
    this.saving = true;

    this.apiService.store('partidas/reordenar-correlativos', this.reordenamiento)
      .pipe(this.untilDestroyed())
      .subscribe({
      next: (response: any) => {
        this.saving = false;
        this.alertService.success(
          'Correlativos reordenados',
          `Se reordenaron ${response.partidas_reordenadas} partidas exitosamente`
        );
        this.filtrarPartidas(); // Refrescar listado
        if (this.modalRef) {
          this.closeModal();
        }
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.saving = false;
        this.alertService.error(error.error?.error || 'Error al reordenar correlativos');
      }
    });
  }

  /**
   * NUEVO: Limpiar filtros y resetear
   */
  public limpiarFiltros() {
    sessionStorage.removeItem(this.FILTROS_STORAGE_KEY);
    this.inicializarFiltrosDefault();
    this.filtrarPartidas();
  }

  /**
   * NUEVO: Toggle para mostrar/ocultar anuladas
   */
  public toggleMostrarAnuladas() {
    this.filtros.incluir_anuladas = !this.filtros.incluir_anuladas;
    this.filtrarPartidas();
  }

  /** URL única por descarga para evitar caché HTTP (navegador/CDN) en reportes GET. */
  private buildReportDownloadUrl(relativePath: string): string {
    const token = this.apiService.auth_token();
    return `${this.apiService.baseUrl}${relativePath}?token=${token}&_ts=${Date.now()}`;
  }

  // Métodos existentes sin cambios...
  public imprimirDiarioAux() {
    if (
      this.reporte.fecha_inicio &&
      this.reporte.fecha_fin &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.buildReportDownloadUrl(
          '/api/reportes/libro/diario/' +
            this.reporte.fecha_inicio +
            '/' +
            this.reporte.fecha_fin +
            '/' +
            this.reporte.tipo_cuenta +
            '/' +
            this.reporte.tipo_descarga
        )
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirMayor() {
    if (this.reporte.fecha_inicio && this.reporte.fecha_fin && this.reporte.concepto) {
      window.open(
        this.buildReportDownloadUrl(
          '/api/reportes/libro/diario/mayor/' +
            this.reporte.fecha_inicio +
            '/' +
            this.reporte.fecha_fin +
            '/' +
            this.reporte.tipo_cuenta +
            '/' +
            this.reporte.concepto
        )
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirDiarioMayor() {
    if (
      this.reporte.fecha_inicio &&
      this.reporte.fecha_fin &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.buildReportDownloadUrl(
          '/api/reportes/libro/diario/mayor/' +
            this.reporte.fecha_inicio +
            '/' +
            this.reporte.fecha_fin +
            '/' +
            this.reporte.tipo_cuenta +
            '/' +
            this.reporte.tipo_descarga
        )
      );
    } else {
      console.error('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirMovCuenta() {
    if (this.reporte.fecha_inicio && this.reporte.fecha_fin && this.reporte.cuenta) {
      window.open(
        this.buildReportDownloadUrl(
          '/api/reportes/movimiento/cuenta/' +
            this.reporte.fecha_inicio +
            '/' +
            this.reporte.fecha_fin +
            '/' +
            this.reporte.cuenta
        )
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirBalanceComprobacion() {
    if (
      this.reporte.fecha_inicio &&
      this.reporte.fecha_fin &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.buildReportDownloadUrl(
          '/api/reportes/balance/comprobacion/' +
            this.reporte.fecha_inicio +
            '/' +
            this.reporte.fecha_fin +
            '/' +
            this.reporte.tipo_cuenta +
            '/' +
            this.reporte.tipo_descarga
        )
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirBalanceGeneral() {
    if (
      this.reporte.fecha_inicio &&
      this.reporte.fecha_fin &&
      this.reporte.tipo_descarga
    ) {
      window.open(
        this.buildReportDownloadUrl(
          '/api/reportes/balance/general/' +
            this.reporte.fecha_inicio +
            '/' +
            this.reporte.fecha_fin +
            '/' +
            this.reporte.tipo_descarga
        )
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirEstadoResultados() {
    if (
      this.reporte.fecha_inicio &&
      this.reporte.fecha_fin &&
      this.reporte.tipo_descarga
    ) {
      const base = this.buildReportDownloadUrl(
        '/api/reportes/estado/resultados/' +
          this.reporte.fecha_inicio +
          '/' +
          this.reporte.fecha_fin +
          '/' +
          this.reporte.tipo_descarga
      );
      const url =
        this.reporte.estadoCompararAnterior === true
          ? `${base}&comparar=1`
          : base;
      window.open(url);
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public abrirPartida(partida: any) {
    this.apiService.store('partidas/abrir', { id: partida.id })
      .pipe(this.untilDestroyed())
      .subscribe({
      next: (response) => {
        this.alertService.success('Partida abierta', 'La partida ha sido reabierta exitosamente.');
        this.filtrarPartidas();
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.alertService.error(error.error.error || 'Error al abrir la partida');
      }
    });
  }

  public imprimirPartida(partida: any) {
    window.open(this.buildReportDownloadUrl('/api/partidas/descargar/' + partida.id), '_blank');
  }

  public descargarPartidaExcel(partida: any) {
    this.apiService.download(`partidas/descargar-excel/${partida.id}`).subscribe({
      next: (response: Blob) => {
        const blob = new Blob([response], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `partida-${partida.id}-${partida.correlativo || 'sin-correlativo'}.xlsx`;
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: (error: any) => {
        this.alertService.error('Error al descargar la partida en Excel');
      }
    });
  }

  public reordenarTodosLosCorrelativos() {
    Swal.fire({
      title: '¿Estás seguro?',
      text: 'Esto reordenará TODAS las partidas de la empresa. Esta acción puede tomar varios minutos.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, reordenar todo',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#f39c12'
    }).then((result) => {
      if (result.isConfirmed) {
        this.saving = true;

        this.apiService.store('partidas/reordenar-correlativos', { todos: true })
          .pipe(this.untilDestroyed())
          .subscribe({
          next: (response: any) => {
            this.saving = false;
            this.alertService.success(
              'Reordenamiento completo',
              `Se reordenaron ${response.partidas_reordenadas} partidas de toda la empresa`
            );
            this.filtrarPartidas();
            if (this.modalRef) {
              this.closeModal();
            }
            this.cdr.markForCheck();
          },
          error: (error) => {
            this.saving = false;
            this.alertService.error(error.error?.error || 'Error al reordenar todos los correlativos');
          }
        });
      }
    });
  }

  public toggleAnuladas() {
    this.filtros.incluir_anuladas = !this.filtros.incluir_anuladas;

    if (this.filtros.incluir_anuladas) {
      this.filtros.estado = '';
    }

    this.filtrarPartidas();
  }

}
