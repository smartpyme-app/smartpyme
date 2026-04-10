import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Router, ActivatedRoute } from '@angular/router';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-partidas',
  templateUrl: './partidas.component.html',
  styleUrls: ['./partidas.component.scss']
})
export class PartidasComponent implements OnInit {
  public partidas: any = [];
  public partida: any = {};
  public loading: boolean = false;
  public saving: boolean = false;
  public filtros: any = {};
  public reporte = {
    fecha_inicio: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    fecha_fin: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0],
    concepto: '',
    cuenta: '',
    tipo_descarga: 'pdf',
    tipo_cuenta: 'all',
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

  // NUEVO: Para mostrar totales
  public totalesGenerales: any = {
    gran_total_debe: 0,
    gran_total_haber: 0,
    total_registros_filtrados: 0
  };

  modalRef!: BsModalRef;

  /**
   * Catálogo para reportes: "Todas" + cuentas con etiqueta "código — nombre".
   * Se usa con ng-select (mismo patrón que partida-detalles) para búsqueda integrada.
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

  // NUEVO: Clave para persistir filtros
  private readonly FILTROS_STORAGE_KEY = 'partidas_filtros_v1';

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    this.apiService.getAll('catalogo/list').subscribe(
      (catalogo) => {
        this.catalogo = catalogo;
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
      paginate: 10,
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

  public filtrarPartidas() {
    this.loading = true;

    console.log('Filtros enviados al backend:', this.filtros);
    this.guardarFiltros();

    this.apiService.getAll('partidas', this.filtros).subscribe(
      (response) => {
        this.partidas = response;

        // NUEVO: Guardar totales generales
        this.totalesGenerales = response.totales_generales || {
          gran_total_debe: 0,
          gran_total_haber: 0,
          total_registros_filtrados: 0
        };

        this.loading = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public openModal(template: TemplateRef<any>, partida: any) {
    this.partida = partida;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public openFilter(template: TemplateRef<any>) {
    // Configuración específica para el modal de reportes
    this.modalRef = this.modalService.show(template, {
      class: 'modal-xl',
      backdrop: 'static' as 'static',
      keyboard: false
    });
  }

  /**
   * NUEVO: Modal para reordenar correlativos
   */
  public openReordenarModal(template: TemplateRef<any>) {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-md',
      backdrop: 'static',
    });
  }

  public setEstado(partida: any, estado: any) {
    this.apiService.read('partida/', partida.id).subscribe(
      (partidaCompleta) => {
        partidaCompleta.estado = estado;
        this.onSubmit(partidaCompleta);
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public setEstadoChange(partida: any) {
    this.apiService.store('partida', partida).subscribe(
      (producto) => {
        this.alertService.success(
          'Partida actualizada',
          'El estado de la partida fue actualizado.'
        );
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.partidas.path + '?page=' + event.page, this.filtros)
      .subscribe(
        (partidas) => {
          this.partidas = partidas;

          // NUEVO: Actualizar totales al paginar
          this.totalesGenerales = partidas.totales_generales || this.totalesGenerales;

          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  public delete(partida: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.delete('partida/', partida.id).subscribe(
          (data) => {
            for (let i = 0; i < this.partidas.data.length; i++) {
              if (this.partidas.data[i].id == data.id)
                this.partidas.data.splice(i, 1);
            }
          },
          (error) => {
            this.alertService.error(error);
          }
        );
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });
  }

  public onSubmit(partidaData?: any) {
    this.saving = true;
    this.apiService.store('partida', partidaData || this.partida).subscribe(
      (partida) => {
        if (!this.partida.id) {
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
        this.saving = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
        this.alertService.modal = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  /**
   * NUEVO: Reordenar correlativos
   */
  public reordenarCorrelativos() {
    this.saving = true;

    this.apiService.store('partidas/reordenar-correlativos', this.reordenamiento).subscribe({
      next: (response) => {
        this.saving = false;
        this.alertService.success(
          'Correlativos reordenados',
          `Se reordenaron ${response.partidas_reordenadas} partidas exitosamente`
        );
        this.filtrarPartidas(); // Refrescar listado
        this.modalRef?.hide();
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

  /**
   * URL única por descarga para evitar caché HTTP (navegador/CDN) en reportes GET.
   */
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
      window.open(
        this.buildReportDownloadUrl(
          '/api/reportes/estado/resultados/' +
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

  public abrirPartida(partida: any) {
    this.apiService.store('partidas/abrir', { id: partida.id }).subscribe({
      next: (response) => {
        this.alertService.success('Partida abierta', 'La partida ha sido reabierta exitosamente.');
        this.filtrarPartidas();
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

        this.apiService.store('partidas/reordenar-correlativos', { todos: true }).subscribe({
          next: (response) => {
            this.saving = false;
            this.alertService.success(
              'Reordenamiento completo',
              `Se reordenaron ${response.partidas_reordenadas} partidas de toda la empresa`
            );
            this.filtrarPartidas();
            this.modalRef?.hide();
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
