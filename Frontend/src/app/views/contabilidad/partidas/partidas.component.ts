import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

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
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear(),
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

  // Cierre de mes avanzado
  public estadoPeriodo: any = null;
  public balanceComprobacion: any = null;
  public validacionesPrevias: any = {
    partidasPendientes: 0,
    balanceCuadra: false,
    periodoAnteriorCerrado: false
  };
  public procesandoCierre: boolean = false;
  public mostrandoBalance: boolean = false;
  public confirmacionFinal: boolean = false;

  // Simulación de cierre
  public simulacionActiva: boolean = false;
  public resultadoSimulacion: any = null;
  public cargandoSimulacion: boolean = false;
  public mostrandoSimulacion: boolean = false;

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
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
    this.years = Array.from({length: 5}, (_, i) => currentYear - 2 + i);
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.filtrarPartidas();
  }

  public loadAll() {
    this.filtros.tipo = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'id';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 10;
    this.filtros.estado = '';
    this.filtrarPartidas();

    this.reporte.month = new Date().getMonth() + 1;
    this.reporte.year = new Date().getFullYear();
    this.reporte.tipo_descarga = 'pdf';
    this.reporte.tipo_cuenta = 'all';
    this.reporte.concepto = '';
  }

  public filtrarPartidas() {
    this.loading = true;
    this.apiService.getAll('partidas', this.filtros).subscribe(
      (partidas) => {
        this.partidas = partidas;
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
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

    public openFilter(template: TemplateRef<any>) {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public openCierreModal(template: TemplateRef<any>) {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-xl',
      backdrop: 'static',
    });

    // Inicializar modal de cierre
    this.inicializarModalCierre();
  }

  private inicializarModalCierre() {
    // Resetear formulario
    this.resetearFormularioCierre();

    // Verificar estado del período actual si ya está seleccionado
    if (this.selectedMonth && this.selectedYear) {
      setTimeout(() => {
        this.verificarEstadoPeriodo();
      }, 500);
    }
  }

  public setEstado(partida: any, estado: any) {
    this.partida = partida;
    this.partida.estado = estado;
    this.onSubmit();
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
        4;
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });
  }

  public onSubmit() {
    this.saving = true;
    this.apiService.store('partida', this.partida).subscribe(
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

  public imprimirDiarioAux() {
    if (
      this.reporte.month &&
      this.reporte.year &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/libro/diario/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.tipo_descarga +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirMayor() {
    if (this.reporte.month && this.reporte.year && this.reporte.concepto) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/libro/diario/mayor/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.concepto +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirDiarioMayor() {
    if (
      this.reporte.month &&
      this.reporte.year &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/libro/diario/mayor/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.tipo_descarga +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      console.error('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirMovCuenta() {
    if (this.reporte.month && this.reporte.year && this.reporte.cuenta) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/movimiento/cuenta/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.cuenta +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  public imprimirBalanceComprobacion() {
    if (
      this.reporte.month &&
      this.reporte.year &&
      this.reporte.tipo_descarga &&
      this.reporte.tipo_cuenta
    ) {
      window.open(
        this.apiService.baseUrl +
          '/api/reportes/balance/comprobacion/' +
          this.reporte.month +
          '/' +
          this.reporte.year +
          '/' +
          this.reporte.tipo_cuenta +
          '/' +
          this.reporte.tipo_descarga +
          '?token=' +
          this.apiService.auth_token()
      );
    } else {
      alert('Por favor, llenar los campos requeridos.');
    }
  }

  // ============ MÉTODOS MEJORADOS DE CIERRE DE MES ============

  public verificarEstadoPeriodo() {
    if (!this.selectedMonth || !this.selectedYear) {
      this.alertService.error('Por favor seleccione un mes y año válidos');
      return;
    }

    this.loading = true;
    this.apiService.getAll('partidas/estado-periodo', {
      month: this.selectedMonth,
      year: this.selectedYear
    }).subscribe({
      next: (response) => {
        this.estadoPeriodo = response;
        this.loading = false;

        if (response.cerrado) {
          this.alertService.info('Información', `El período ${response.periodo} ya está cerrado`);
        }
      },
      error: (error) => {
        this.loading = false;
        this.alertService.error(error.error?.error || 'Error al verificar estado del período');
      }
    });
  }

  public verificarValidacionesPrevias() {
    if (!this.selectedMonth || !this.selectedYear) {
      this.alertService.error('Por favor seleccione un mes y año válidos');
      return;
    }

    this.loading = true;

    // Obtener balance de comprobación para validaciones
    this.apiService.getAll('partidas/balance-comprobacion', {
      month: this.selectedMonth,
      year: this.selectedYear
    }).subscribe({
      next: (response) => {
        this.balanceComprobacion = response;
        this.validacionesPrevias.balanceCuadra = response.totales?.cuadra || false;
        this.loading = false;
        this.mostrandoBalance = true;
      },
      error: (error) => {
        this.loading = false;
        this.alertService.error(error.error?.error || 'Error al obtener balance de comprobación');
      }
    });
  }

  public confirmarCierreMes() {
    if (!this.validacionesPrevias.balanceCuadra) {
      Swal.fire({
        title: '⚠️ Balance Descuadrado',
        text: 'El balance de comprobación no cuadra. ¿Desea continuar de todos modos?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33'
      }).then((result) => {
        if (result.isConfirmed) {
          this.ejecutarCierreMes();
        }
      });
    } else {
      Swal.fire({
        title: '🔒 Confirmar Cierre de Mes',
        html: `
          <div class="text-start">
            <p><strong>Período:</strong> ${this.selectedMonth}/${this.selectedYear}</p>
            <p><strong>Balance:</strong> <span class="text-success">✅ Cuadrado</span></p>
            <hr>
            <p class="text-warning"><strong>⚠️ Esta acción:</strong></p>
            <ul class="text-start">
              <li>Cerrará todas las partidas del período</li>
              <li>Calculará saldos finales</li>
              <li>Actualizará saldos iniciales del siguiente período</li>
              <li><strong>No se puede deshacer fácilmente</strong></li>
            </ul>
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '🔒 Confirmar Cierre',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745'
      }).then((result) => {
        if (result.isConfirmed) {
          this.ejecutarCierreMes();
        }
      });
    }
  }

  public ejecutarCierreMes() {
    this.procesandoCierre = true;
    this.saving = true;

    this.apiService.store('partidas/cerrar', {
      month: this.selectedMonth,
      year: this.selectedYear
    }).subscribe({
      next: (response) => {
        this.procesandoCierre = false;
        this.saving = false;

        Swal.fire({
          title: '✅ Cierre Exitoso',
          html: `
            <div class="text-start">
              <p><strong>Período:</strong> ${response.periodo}</p>
              <p><strong>Cuentas procesadas:</strong> ${response.cuentas_procesadas}</p>
              <p><strong>Fecha de cierre:</strong> ${new Date(response.fecha_cierre).toLocaleString()}</p>
            </div>
          `,
          icon: 'success',
          confirmButtonText: 'Entendido'
        });

        this.modalRef.hide();
        this.filtrarPartidas();
        this.resetearFormularioCierre();
      },
      error: (error) => {
        this.procesandoCierre = false;
        this.saving = false;

        Swal.fire({
          title: '❌ Error en el Cierre',
          text: error.error?.error || 'Error al ejecutar el cierre de mes',
          icon: 'error',
          confirmButtonText: 'Entendido'
        });
      }
    });
  }

  public reabrirPeriodo() {
    Swal.fire({
      title: '🔓 Reabrir Período',
      html: `
        <div class="text-start">
          <p><strong>Período:</strong> ${this.selectedMonth}/${this.selectedYear}</p>
          <hr>
          <p class="text-warning"><strong>⚠️ Esta acción:</strong></p>
          <ul class="text-start">
            <li>Reabrirá el período cerrado</li>
            <li>Permitirá modificar partidas</li>
            <li>Requiere volver a cerrar el período</li>
          </ul>
        </div>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: '🔓 Reabrir',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#ffc107'
    }).then((result) => {
      if (result.isConfirmed) {
        this.ejecutarReapertura();
      }
    });
  }

  public ejecutarReapertura() {
    this.saving = true;

    this.apiService.store('partidas/reabrir', {
      month: this.selectedMonth,
      year: this.selectedYear
    }).subscribe({
      next: (response) => {
        this.saving = false;

        Swal.fire({
          title: '✅ Período Reabierto',
          text: `El período ${response.periodo} ha sido reabierto exitosamente`,
          icon: 'success',
          confirmButtonText: 'Entendido'
        });

        this.verificarEstadoPeriodo();
        this.filtrarPartidas();
      },
      error: (error) => {
        this.saving = false;
        this.alertService.error(error.error?.error || 'Error al reabrir el período');
      }
    });
  }

  public resetearFormularioCierre() {
    this.estadoPeriodo = null;
    this.balanceComprobacion = null;
    this.mostrandoBalance = false;
    this.confirmacionFinal = false;
    this.validacionesPrevias = {
      partidasPendientes: 0,
      balanceCuadra: false,
      periodoAnteriorCerrado: false
    };
    // Limpiar simulación
    this.limpiarSimulacion();
  }

  public onCambiarPeriodo() {
    this.resetearFormularioCierre();
    if (this.selectedMonth && this.selectedYear) {
      this.verificarEstadoPeriodo();
    }
  }

  public descargarBalanceComprobacion() {
    if (this.balanceComprobacion) {
      this.apiService.export(`reportes/balance/comprobacion/${this.selectedMonth}/${this.selectedYear}/all/excel`, {}).subscribe((data: Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `balance_comprobacion_${this.selectedMonth}_${this.selectedYear}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
      }, (error) => {
        this.alertService.error(error);
      });
    }
  }

  // ============ MÉTODOS DE SIMULACIÓN ============

  public ejecutarSimulacion() {
    if (!this.selectedMonth || !this.selectedYear) {
      this.alertService.error('Por favor seleccione un mes y año válidos');
      return;
    }

    this.cargandoSimulacion = true;
    this.simulacionActiva = true;

    this.apiService.getAll('partidas/simular-cierre', {
      month: this.selectedMonth,
      year: this.selectedYear
    }).subscribe({
      next: (response) => {
        this.resultadoSimulacion = response;
        this.cargandoSimulacion = false;
        this.mostrandoSimulacion = true;

        if (response.simulacion_exitosa) {
          this.alertService.success('Simulación completa', 'La simulación se ejecutó exitosamente');
        } else {
          this.alertService.error({ status: 400, error: { error: response.error } });
        }
      },
      error: (error) => {
        this.cargandoSimulacion = false;
        this.simulacionActiva = false;
        this.alertService.error(error.error?.error || 'Error al ejecutar la simulación');
      }
    });
  }

  public limpiarSimulacion() {
    this.simulacionActiva = false;
    this.resultadoSimulacion = null;
    this.mostrandoSimulacion = false;
    this.cargandoSimulacion = false;
  }

  public descargarReporteSimulacion() {
    if (this.resultadoSimulacion) {
      // Generar reporte de la simulación
      const reporteData = {
        periodo: this.resultadoSimulacion.periodo,
        fecha_simulacion: this.resultadoSimulacion.fecha_simulacion,
        validaciones: this.resultadoSimulacion.validaciones,
        balance: this.resultadoSimulacion.balance_proyectado,
        metricas: this.resultadoSimulacion.metricas,
        advertencias: this.resultadoSimulacion.advertencias,
        recomendaciones: this.resultadoSimulacion.recomendaciones
      };

      // Crear y descargar archivo JSON
      const blob = new Blob([JSON.stringify(reporteData, null, 2)], {
        type: 'application/json'
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `simulacion_cierre_${this.selectedMonth}_${this.selectedYear}.json`;
      link.click();
      window.URL.revokeObjectURL(url);
    }
  }

  public ejecutarCierreDespuesDeSimulacion() {
    if (!this.resultadoSimulacion?.simulacion_exitosa) {
      this.alertService.error({ status: 400, error: { error: 'No se puede proceder: La simulación no fue exitosa' } });
      return;
    }

    Swal.fire({
      title: '🚀 Ejecutar Cierre Real',
      html: `
        <div class="text-start">
          <p><strong>Período:</strong> ${this.resultadoSimulacion.periodo}</p>
          <p><strong>Simulación:</strong> <span class="text-success">✅ Exitosa</span></p>
          <p><strong>Puntuación de calidad:</strong> ${this.resultadoSimulacion.metricas?.puntuacion_calidad || 'N/A'}/100</p>
          <hr>
          <p class="text-danger"><strong>⚠️ ATENCIÓN:</strong></p>
          <p>Está a punto de ejecutar el cierre real basado en la simulación. Esta acción no se puede deshacer fácilmente.</p>
        </div>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: '🔒 Ejecutar Cierre Real',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#28a745'
    }).then((result) => {
      if (result.isConfirmed) {
        this.ejecutarCierreMes();
      }
    });
  }

  // Método anterior mantenido para compatibilidad
  public cerrarPartidas() {
    this.confirmarCierreMes();
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
    window.open(
      this.apiService.baseUrl + '/api/partidas/descargar/' + partida.id + '?token=' + this.apiService.auth_token(),
      '_blank'
    );
  }
}
