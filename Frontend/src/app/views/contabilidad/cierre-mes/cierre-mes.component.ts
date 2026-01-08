import { Component, OnInit, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-cierre-mes',
    templateUrl: './cierre-mes.component.html',
    styleUrls: ['./cierre-mes.component.scss'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class CierreMesComponent implements OnInit {

  // Propiedades para el período seleccionado
  public selectedMonth: number = new Date().getMonth() + 1;
  public selectedYear: number = new Date().getFullYear();
  public months: Array<{ value: number; label: string }> = [];
  public years: number[] = [];

  // Estados del proceso de cierre
  public estadoPeriodo: any = null;
  public balanceComprobacion: any = null;
  public validacionesPrevias: any = {
    partidasPendientes: 0,
    balanceCuadra: false,
    balanceCuadraConTolerancia: false,
    periodoAnteriorCerrado: false,
    requiereConfirmacionDiferencia: false,
    diferenciaBalance: 0
  };
  public procesandoCierre: boolean = false;
  public mostrandoBalance: boolean = false;
  public confirmacionFinal: boolean = false;

  // Simulación de cierre
  public simulacionActiva: boolean = false;
  public resultadoSimulacion: any = null;
  public cargandoSimulacion: boolean = false;
  public mostrandoSimulacion: boolean = false;

  // Estados de carga
  public cargandoPeriodo: boolean = false;
  public cargandoBalance: boolean = false;
  public cargandoValidaciones: boolean = false;

  // Datos del catálogo
  public catalogo: any = [];

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.initializeDates();
    this.loadCatalogo();
    this.onCambiarPeriodo();
  }

  /**
   * Inicializar fechas disponibles
   */
  private initializeDates(): void {
    // Generar meses
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

    // Generar años (5 años hacia atrás y 2 hacia adelante)
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 5; i <= currentYear + 2; i++) {
      this.years.push(i);
    }
  }

  /**
   * Cargar catálogo de cuentas
   */
  private loadCatalogo(): void {
    this.apiService.getAll('catalogo/cuentas')
      .pipe(this.untilDestroyed())
      .subscribe(
      (catalogo: any) => {
        this.catalogo = catalogo;
        this.cdr.markForCheck();
      },
      (error: any) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );
  }

  /**
   * Al cambiar el período seleccionado
   */
  public onCambiarPeriodo(): void {
    if (this.selectedMonth && this.selectedYear) {
      this.resetearEstados();
      this.cargarEstadoPeriodo();
    }
  }

  /**
   * Resetear todos los estados
   */
  private resetearEstados(): void {
    this.estadoPeriodo = null;
    this.balanceComprobacion = null;
    this.validacionesPrevias = {
      partidasPendientes: 0,
      balanceCuadra: false,
      balanceCuadraConTolerancia: false,
      periodoAnteriorCerrado: false,
      requiereConfirmacionDiferencia: false,
      diferenciaBalance: 0
    };
    this.simulacionActiva = false;
    this.resultadoSimulacion = null;
    this.mostrandoSimulacion = false;
    this.mostrandoBalance = false;
    this.confirmacionFinal = false;
  }

  /**
   * Cargar estado del período seleccionado
   */
  private cargarEstadoPeriodo(): void {
    this.cargandoPeriodo = true;

    this.apiService.getAll(`partidas/estado-periodo?year=${this.selectedYear}&month=${this.selectedMonth}`)
      .pipe(this.untilDestroyed())
      .subscribe(
      (estado: any) => {
        this.estadoPeriodo = estado;
        this.cargandoPeriodo = false;

        // Si el período no está cerrado, cargar validaciones
        if (!estado.cerrado) {
          this.cargarValidacionesPrevias();
        }
        this.cdr.markForCheck();
      },
      (error: any) => {
        this.alertService.error(error);
        this.cargandoPeriodo = false;
        this.cdr.markForCheck();
      }
    );
  }

  /**
   * Cargar validaciones previas al cierre
   */
  private cargarValidacionesPrevias(): void {
    this.cargandoValidaciones = true;

    // Las validaciones se obtienen de la simulación de cierre
    this.apiService.getAll(`partidas/simular-cierre?year=${this.selectedYear}&month=${this.selectedMonth}`)
      .pipe(this.untilDestroyed())
      .subscribe(
      (resultado: any) => {
        if (resultado.validaciones) {
          this.validacionesPrevias = {
            partidasPendientes: resultado.validaciones.partidas_pendientes || 0,
            balanceCuadra: resultado.validaciones.balance_cuadra || false,
            balanceCuadraConTolerancia: resultado.validaciones.balance_cuadra_con_tolerancia || false,
            periodoAnteriorCerrado: resultado.validaciones.periodo_anterior_cerrado || false,
            requiereConfirmacionDiferencia: resultado.validaciones.requiere_confirmacion_diferencia || false,
            diferenciaBalance: resultado.validaciones.diferencia_balance || 0
          };
        }
        this.cargandoValidaciones = false;
        this.cdr.markForCheck();
      },
      (error: any) => {
        this.alertService.error(error);
        this.cargandoValidaciones = false;
        this.cdr.markForCheck();
      }
    );
  }

  /**
   * Simular cierre de mes
   */
  public simularCierre(): void {
    this.cargandoSimulacion = true;
    this.simulacionActiva = true;

    this.apiService.getAll(`partidas/simular-cierre?year=${this.selectedYear}&month=${this.selectedMonth}`)
      .pipe(this.untilDestroyed())
      .subscribe(
      (simulacion: any) => {
        this.resultadoSimulacion = simulacion;
        this.mostrandoSimulacion = true;
        this.cargandoSimulacion = false;

        if (simulacion.simulacion_exitosa) {
          this.alertService.success(
            '✅ Simulación Exitosa',
            'El cierre puede realizarse sin problemas'
          );
        } else {
          this.alertService.warning(
            '⚠️ Simulación con Advertencias',
            'Revise las observaciones antes de continuar'
          );
        }
        this.cdr.markForCheck();
      },
      (error: any) => {
        this.alertService.error(error);
        this.cargandoSimulacion = false;
        this.simulacionActiva = false;
        this.cdr.markForCheck();
      }
    );
  }

  /**
   * Mostrar balance de comprobación
   */
  public mostrarBalance(): void {
    this.cargandoBalance = true;
    this.mostrandoBalance = true;

    this.apiService.getAll(`partidas/balance-comprobacion?year=${this.selectedYear}&month=${this.selectedMonth}`)
      .pipe(this.untilDestroyed())
      .subscribe(
      (balance: any) => {
        this.balanceComprobacion = balance;
        this.cargandoBalance = false;
        this.cdr.markForCheck();
      },
      (error: any) => {
        this.alertService.error(error);
        this.cargandoBalance = false;
        this.mostrandoBalance = false;
        this.cdr.markForCheck();
      }
    );
  }

  /**
   * Ocultar balance de comprobación
   */
  public ocultarBalance(): void {
    this.mostrandoBalance = false;
    this.balanceComprobacion = null;
    this.cdr.markForCheck();
  }

  /**
   * Preparar confirmación final
   */
  public prepararConfirmacion(): void {
    if (!this.puedeRealizarCierre()) {
      this.alertService.warning(
        'Cierre no disponible',
        'Debe resolver todas las validaciones antes de continuar'
      );
      return;
    }

    this.confirmacionFinal = true;
    this.cdr.markForCheck();
  }

  /**
   * Cancelar confirmación
   */
  public cancelarConfirmacion(): void {
    this.confirmacionFinal = false;
    this.cdr.markForCheck();
  }

  /**
   * Realizar cierre definitivo
   */
  public realizarCierre(): void {
    // Preparar mensaje diferente si hay diferencia menor
    let tituloModal = '🔒 Confirmar Cierre de Mes';
    let iconoModal = 'warning';
    let mensajeAdicional = '';

    if (this.requiereConfirmacionPorDiferencia()) {
      tituloModal = '⚠️ Cierre con Diferencia Menor';
      iconoModal = 'question';
      mensajeAdicional = `
        <div class="alert alert-warning" style="text-align: left; margin: 10px 0;">
          <h6><i class="fa fa-exclamation-triangle"></i> Diferencia Detectada</h6>
          <p><strong>Diferencia en balance:</strong> $${this.validacionesPrevias.diferenciaBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
          <p>Esta diferencia es menor a $1.00 y está dentro del rango permitido.</p>
          <p><strong>¿Desea proceder con el cierre?</strong></p>
        </div>
      `;
    }

    Swal.fire({
      title: tituloModal,
      html: `
        <div class="text-start">
          <p><strong>Período:</strong> ${this.getMonthName(this.selectedMonth)} ${this.selectedYear}</p>
          ${mensajeAdicional}
          <p><strong>⚠️ Esta acción no se puede deshacer</strong></p>
          <p>El sistema:</p>
          <ul class="text-start">
            <li>✅ Cerrará todas las partidas del período</li>
            <li>✅ Calculará saldos finales</li>
            <li>✅ Actualizará saldos iniciales del siguiente período</li>
            <li>✅ Generará respaldo automático</li>
          </ul>
        </div>
      `,
      icon: iconoModal as any,
      showCancelButton: true,
      confirmButtonText: this.requiereConfirmacionPorDiferencia() ? 'Sí, Cerrar con Diferencia' : 'Sí, Cerrar Período',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: this.requiereConfirmacionPorDiferencia() ? '#ff9800' : '#d33',
      cancelButtonColor: '#3085d6'
    }).then((result) => {
      if (result.isConfirmed) {
        this.ejecutarCierre();
      }
    });
  }

  /**
   * Ejecutar el cierre definitivo
   */
  private ejecutarCierre(): void {
    this.procesandoCierre = true;

    const params = {
      year: this.selectedYear,
      month: this.selectedMonth
    };

    this.apiService.store('partidas/cerrar', params)
      .pipe(this.untilDestroyed())
      .subscribe(
      (resultado: any) => {
        this.procesandoCierre = false;

        if (resultado.success) {
          // Obtener balance final para mostrar en el mensaje de éxito
          this.apiService.getAll(`partidas/balance-comprobacion?year=${this.selectedYear}&month=${this.selectedMonth}`)
            .pipe(this.untilDestroyed())
            .subscribe(
            (balanceFinal: any) => {
              Swal.fire({
                title: '🎉 Cierre Completado',
                html: `
                  <div class="text-start">
                    <p><strong>Período:</strong> ${resultado.periodo}</p>
                    <p><strong>Cuentas procesadas:</strong> ${resultado.cuentas_procesadas}</p>
                    <p><strong>Fecha:</strong> ${new Date(resultado.fecha_cierre).toLocaleString()}</p>
                    <hr>
                    <h6 class="text-success">
                      <i class="fa fa-check-circle me-2"></i>
                      Balance de Movimientos:
                    </h6>
                    <div class="bg-light p-3 rounded">
                      <p><strong>✅ Total Debe:</strong> ${balanceFinal.totales?.debe.toLocaleString('en-US', { style: 'currency', currency: 'USD' })}</p>
                      <p><strong>✅ Total Haber:</strong> ${balanceFinal.totales?.haber.toLocaleString('en-US', { style: 'currency', currency: 'USD' })}</p>
                      <p><strong>✅ Diferencia:</strong>
                        <span class="${balanceFinal.totales?.cuadra_movimientos ? 'text-success' : balanceFinal.totales?.cuadra_movimientos_con_tolerancia ? 'text-warning' : 'text-danger'}">
                          ${balanceFinal.totales?.diferencia_movimientos.toLocaleString('en-US', { style: 'currency', currency: 'USD' })}
                        </span>
                        ${balanceFinal.totales?.cuadra_movimientos ?
                          '<i class="fa fa-check-circle text-success ms-2"></i>' :
                          balanceFinal.totales?.cuadra_movimientos_con_tolerancia ?
                          '<i class="fa fa-exclamation-triangle text-warning ms-2"></i>' :
                          '<i class="fa fa-times-circle text-danger ms-2"></i>'
                        }
                      </p>
                    </div>
                    <p class="text-success mt-3">
                      <i class="fa fa-shield-alt me-2"></i>
                      <strong>Balance cuadrado correctamente</strong>
                    </p>
                  </div>
                `,
                icon: 'success',
                confirmButtonText: 'Continuar'
              }).then(() => {
                // Recargar estado del período
                this.cargarEstadoPeriodo();
                this.confirmacionFinal = false;
                this.cdr.markForCheck();
              });
            },
            (error: any) => {
              // Si no se puede obtener el balance, mostrar mensaje básico
              Swal.fire({
                title: '🎉 Cierre Completado',
                html: `
                  <div class="text-start">
                    <p><strong>Período:</strong> ${resultado.periodo}</p>
                    <p><strong>Cuentas procesadas:</strong> ${resultado.cuentas_procesadas}</p>
                    <p><strong>Fecha:</strong> ${new Date(resultado.fecha_cierre).toLocaleString()}</p>
                  </div>
                `,
                icon: 'success',
                confirmButtonText: 'Continuar'
              }).then(() => {
                // Recargar estado del período
                this.cargarEstadoPeriodo();
                this.confirmacionFinal = false;
                this.cdr.markForCheck();
              });
            }
          );
        } else {
          this.alertService.error(resultado.message || 'Error desconocido en el cierre');
        }
        this.cdr.markForCheck();
      },
      (error: any) => {
        this.alertService.error(error);
        this.procesandoCierre = false;
        this.cdr.markForCheck();
      }
    );
  }

  /**
   * Reabrir período cerrado
   */
  public reabrirPeriodo(): void {
    Swal.fire({
      title: 'Reabrir Período',
      html: `
        <div class="text-start">
          <p><strong>Período:</strong> ${this.getMonthName(this.selectedMonth)} ${this.selectedYear}</p>
          <p><strong>⚠️ Esta acción permitirá modificar partidas del período</strong></p>
          <p>¿Está seguro de continuar?</p>
        </div>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, Reabrir',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d'
    }).then((result) => {
      if (result.isConfirmed) {
        this.ejecutarReapertura();
      }
    });
  }

  /**
   * Ejecutar reapertura del período
   */
  private ejecutarReapertura(): void {
    this.procesandoCierre = true;

    const params = {
      year: this.selectedYear,
      month: this.selectedMonth
    };

    this.apiService.store('partidas/reabrir', params)
      .pipe(this.untilDestroyed())
      .subscribe(
      (resultado: any) => {
        this.procesandoCierre = false;

        if (resultado.success) {
          this.alertService.success('Período reabierto', resultado.message);
          this.cargarEstadoPeriodo();
        } else {
          this.alertService.error(resultado.message || 'Error desconocido en la reapertura');
        }
        this.cdr.markForCheck();
      },
      (error: any) => {
        this.alertService.error(error);
        this.procesandoCierre = false;
        this.cdr.markForCheck();
      }
    );
  }

  /**
   * Verificar si se puede realizar el cierre
   */
  public puedeRealizarCierre(): boolean {
    return this.validacionesPrevias.periodoAnteriorCerrado &&
           (this.validacionesPrevias.balanceCuadra || this.validacionesPrevias.balanceCuadraConTolerancia) &&
           this.validacionesPrevias.partidasPendientes === 0;
  }

  /**
   * Verificar si requiere confirmación por diferencia menor
   */
  public requiereConfirmacionPorDiferencia(): boolean {
    return this.validacionesPrevias.requiereConfirmacionDiferencia || false;
  }

  /**
   * Verificar si hay advertencias
   */
  public hayAdvertencias(): boolean {
    return this.resultadoSimulacion?.advertencias?.length > 0;
  }

  /**
   * Obtener nombre del mes
   */
  public getMonthName(month: number | string): string {
    // Convertir a número para asegurar compatibilidad
    const monthNumber = Number(month);
    const monthObj = this.months.find(m => m.value === monthNumber);
    return monthObj ? monthObj.label : monthNumber.toString();
  }

  /**
   * Volver a partidas
   */
  public volverAPartidas(): void {
    this.router.navigate(['/contabilidad/partidas']);
  }

  /**
   * Navegar a catálogo de cuentas
   */
  public irACatalogo(): void {
    this.router.navigate(['/catalogo/cuentas']);
  }

  /**
   * Navegar a partidas
   */
  public irAPartidas(): void {
    this.router.navigate(['/contabilidad/partidas']);
  }


}
