import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { retry, catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-aguinaldo-detalle',
  templateUrl: './aguinaldo-detalle.component.html',
})
export class AguinaldoDetalleComponent implements OnInit {
  public aguinaldo: any = {};
  public detalles: any[] = [];
  public loading: boolean = false;
  public saving: boolean = false;
  public procesando: boolean = false;
  public editandoMonto: { [key: number]: boolean } = {};
  public montoTemporal: { [key: number]: number } = {};

  public empleadosDisponibles: any[] = [];
  public empleadoSeleccionado: any = null;
  public montoBrutoNuevo: number = 0;
  public notasNuevo: string = '';
  public sugerenciaAguinaldo: number = 0;
  public mesesTrabajados: number = 0;
  public cargandoSugerencia: boolean = false;
  public previewCalculo: any = {
    monto_bruto: 0,
    monto_exento: 0,
    monto_gravado: 0,
    retencion_renta: 0,
    aguinaldo_neto: 0
  };
  public cargandoPreview: boolean = false;

  public ESTADOS_AGUINALDO = {
    BORRADOR: PlanillaConstants.AGUINALDO?.ESTADOS?.BORRADOR || 1,
    PAGADO: PlanillaConstants.AGUINALDO?.ESTADOS?.PAGADO || 2,
  };

  modalRef!: BsModalRef;
  @ViewChild('mAgregarEmpleado') mAgregarEmpleado!: TemplateRef<any>;

  private apiUrl = environment.API_URL + '/api/';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private http: HttpClient
  ) {}

  ngOnInit() {
    this.route.params.subscribe((params) => {
      if (params['id']) {
        this.loadAguinaldo(params['id']);
      }
    });
  }

  public loadAguinaldo(id: number) {
    this.loading = true;
    this.apiService.read('aguinaldos/', id).subscribe({
      next: (response: any) => {
        this.aguinaldo = response.aguinaldo;
        this.detalles = response.detalles || [];
        this.loading = false;
      },
      error: (error: any) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  public formatearMoneda(valor: number): string {
    return new Intl.NumberFormat('es-SV', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
    }).format(valor || 0);
  }

  public editarMonto(detalle: any) {
    this.editandoMonto[detalle.id] = true;
    this.montoTemporal[detalle.id] = detalle.monto_aguinaldo_bruto;
  }

  public cancelarEdicion(detalleId: number) {
    this.editandoMonto[detalleId] = false;
    delete this.montoTemporal[detalleId];
  }

  public guardarMonto(detalle: any) {
    const nuevoMonto = this.montoTemporal[detalle.id];
    
    if (!nuevoMonto || nuevoMonto <= 0) {
      this.alertService.error('El monto debe ser mayor a cero');
      return;
    }

    this.saving = true;
    this.apiService
      .update('aguinaldo-detalles/', detalle.id, {
        monto_aguinaldo_bruto: nuevoMonto,
        notas: detalle.notas || '',
      })
      .subscribe({
        next: (response: any) => {
          this.alertService.success('Éxito', 'Monto actualizado exitosamente');
          this.editandoMonto[detalle.id] = false;
          delete this.montoTemporal[detalle.id];
          this.loadAguinaldo(this.aguinaldo.id);
          this.saving = false;
        },
        error: (error: any) => {
          this.alertService.error(error);
          this.saving = false;
        },
      });
  }

  public openAgregarEmpleado(template: TemplateRef<any>) {
    this.empleadoSeleccionado = null;
    this.montoBrutoNuevo = 0;
    this.notasNuevo = '';
    this.sugerenciaAguinaldo = 0;
    this.mesesTrabajados = 0;
    this.previewCalculo = {
      monto_bruto: 0,
      monto_exento: 0,
      monto_gravado: 0,
      retencion_renta: 0,
      aguinaldo_neto: 0
    };
    this.cargarEmpleadosDisponibles();
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public empleadoInfo: any = null;

  public onEmpleadoSeleccionado() {
    if (!this.empleadoSeleccionado) {
      this.sugerenciaAguinaldo = 0;
      this.mesesTrabajados = 0;
      this.empleadoInfo = null;
      this.calcularPreview();
      return;
    }

    // Guardar información del empleado seleccionado
    this.empleadoInfo = this.empleadosDisponibles.find((e: any) => e.id === this.empleadoSeleccionado);

    this.cargandoSugerencia = true;
    this.apiService.store('aguinaldos/sugerencia', {
      id_empleado: this.empleadoSeleccionado,
      anio: this.aguinaldo.anio,
      fecha_calculo: this.aguinaldo.fecha_calculo || (this.aguinaldo.anio + '-12-12')
    }).subscribe({
      next: (response: any) => {
        this.sugerenciaAguinaldo = response.sugerencia || 0;
        this.mesesTrabajados = response.meses_trabajados || 0;
        this.empleadoInfo = {
          ...this.empleadoInfo,
          tipo_contrato: response.tipo_contrato,
          salario_base: response.salario_base
        };
        this.cargandoSugerencia = false;
        // Si no hay monto ingresado, usar la sugerencia
        if (!this.montoBrutoNuevo && this.sugerenciaAguinaldo > 0) {
          this.montoBrutoNuevo = this.sugerenciaAguinaldo;
          this.calcularPreview();
        } else if (this.montoBrutoNuevo) {
          this.calcularPreview();
        }
      },
      error: (error: any) => {
        this.alertService.error('Error al obtener sugerencia: ' + (error.error?.error || error.message));
        this.cargandoSugerencia = false;
      }
    });
  }

  public usarSugerencia() {
    if (this.sugerenciaAguinaldo > 0) {
      this.montoBrutoNuevo = this.sugerenciaAguinaldo;
      this.calcularPreview();
    }
  }

  public calcularPreview() {
    if (!this.montoBrutoNuevo || this.montoBrutoNuevo <= 0) {
      this.previewCalculo = {
        monto_bruto: 0,
        monto_exento: 0,
        monto_gravado: 0,
        retencion_renta: 0,
        aguinaldo_neto: 0
      };
      return;
    }

    if (!this.empleadoSeleccionado || !this.empleadoInfo) {
      return;
    }

    // Usar tipo de contrato del empleado
    const tipoContrato = this.empleadoInfo.tipo_contrato || null;

    this.cargandoPreview = true;
    this.apiService.store('aguinaldos/preview', {
      monto_bruto: this.montoBrutoNuevo,
      anio: this.aguinaldo.anio,
      tipo_contrato: tipoContrato,
      fecha_calculo: this.aguinaldo.fecha_calculo || (this.aguinaldo.anio + '-12-12')
    }).subscribe({
      next: (response: any) => {
        this.previewCalculo = response;
        this.cargandoPreview = false;
      },
      error: (error: any) => {
        // No mostrar error si es solo preview, solo loguear
        console.error('Error al calcular preview:', error);
        this.cargandoPreview = false;
      }
    });
  }

  public cargarEmpleadosDisponibles() {
    this.loading = true;
    // Cargar empleados activos que no estén ya en el aguinaldo
    // Usar un número alto para obtener todos los empleados
    this.apiService.getAll('empleados', { estado: 1, paginate: 1000 }).subscribe({
      next: (response: any) => {
        // La respuesta puede ser un array directo o un objeto con paginación
        let empleados: any[] = [];
        if (Array.isArray(response)) {
          empleados = response;
        } else if (response?.data && Array.isArray(response.data)) {
          empleados = response.data;
        } else {
          console.error('Formato de respuesta inesperado:', response);
          this.alertService.error('Error al cargar empleados: formato de respuesta inválido');
          this.loading = false;
          return;
        }

        // Asegurarse de que empleados es un array antes de filtrar
        if (!Array.isArray(empleados)) {
          console.error('Los empleados no son un array:', empleados);
          this.alertService.error('Error al cargar empleados: los datos no son un array');
          this.loading = false;
          return;
        }

        // Filtrar empleados que ya están en el aguinaldo
        const idsEnAguinaldo = this.detalles.map((d) => d.id_empleado);
        
        // Obtener constantes de tipos de contrato
        const tiposContrato = PlanillaConstants.TIPOS_CONTRATO || {};
        const TIPO_PERMANENTE = tiposContrato.PERMANENTE || 1;
        const TIPO_TEMPORAL = tiposContrato.TEMPORAL || 2;
        
        // Filtrar: solo permanentes y temporales, y que no estén ya agregados
        this.empleadosDisponibles = empleados.filter(
          (emp: any) => {
            // Excluir si ya está en el aguinaldo
            if (idsEnAguinaldo.includes(emp.id)) {
              return false;
            }
            
            // Solo incluir contratos permanentes y temporales
            // Excluir: Por obra (3) y Servicios Profesionales (4)
            return emp.tipo_contrato === TIPO_PERMANENTE || 
                   emp.tipo_contrato === TIPO_TEMPORAL;
          }
        );
        this.loading = false;
      },
      error: (error: any) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  public onMontoBrutoChange() {
    // Calcular preview cuando cambia el monto
    this.calcularPreview();
  }

  public agregarEmpleado() {
    if (!this.empleadoSeleccionado) {
      this.alertService.error('Debe seleccionar un empleado');
      return;
    }

    if (!this.montoBrutoNuevo || this.montoBrutoNuevo <= 0) {
      this.alertService.error('El monto bruto debe ser mayor a cero');
      return;
    }

    this.saving = true;
    this.apiService
      .store('aguinaldos/' + this.aguinaldo.id + '/agregar-empleado', {
        id_empleado: this.empleadoSeleccionado,
        monto_aguinaldo_bruto: this.montoBrutoNuevo,
        notas: this.notasNuevo || '',
      })
      .subscribe({
        next: (response: any) => {
          this.alertService.success('Éxito', 'Empleado agregado exitosamente');
          this.modalRef.hide();
          this.loadAguinaldo(this.aguinaldo.id);
          this.saving = false;
        },
        error: (error: any) => {
          this.alertService.error(error);
          this.saving = false;
        },
      });
  }

  public eliminarEmpleado(detalle: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: 'Esta acción eliminará el empleado del aguinaldo',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.loading = true;
        this.apiService.delete('aguinaldo-detalles/', detalle.id).subscribe({
          next: () => {
            this.alertService.success('Éxito', 'Empleado eliminado exitosamente');
            this.loadAguinaldo(this.aguinaldo.id);
          },
          error: (error: any) => {
            this.alertService.error(error);
            this.loading = false;
          },
        });
      }
    });
  }

  public procesarPago() {
    Swal.fire({
      title: '¿Procesar pago del aguinaldo?',
      text: 'Esta acción marcará el aguinaldo como pagado',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sí, procesar pago',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.procesando = true;
        this.apiService
          .store('aguinaldos/' + this.aguinaldo.id + '/pagar', {})
          .subscribe({
            next: (response: any) => {
              this.alertService.success('Éxito', 'Pago procesado exitosamente');
              this.loadAguinaldo(this.aguinaldo.id);
              this.procesando = false;
            },
            error: (error: any) => {
              this.alertService.error(error);
              this.procesando = false;
            },
          });
      }
    });
  }

  public getTotalAguinaldos(): number {
    if (!this.detalles || this.detalles.length === 0) {
      return 0;
    }
    return this.detalles.reduce(
      (sum, detalle) => sum + (parseFloat(detalle.monto_aguinaldo_bruto) || 0),
      0
    );
  }

  public getTotalExento(): number {
    if (!this.detalles || this.detalles.length === 0) {
      return 0;
    }
    return this.detalles.reduce(
      (sum, detalle) => sum + (parseFloat(detalle.monto_exento) || 0),
      0
    );
  }

  public getTotalGravado(): number {
    if (!this.detalles || this.detalles.length === 0) {
      return 0;
    }
    return this.detalles.reduce(
      (sum, detalle) => sum + (parseFloat(detalle.monto_gravado) || 0),
      0
    );
  }

  public getTotalRetenciones(): number {
    if (!this.detalles || this.detalles.length === 0) {
      return 0;
    }
    return this.detalles.reduce(
      (sum, detalle) => sum + (parseFloat(detalle.retencion_renta) || 0),
      0
    );
  }

  public getTotalNeto(): number {
    if (!this.detalles || this.detalles.length === 0) {
      return 0;
    }
    return this.detalles.reduce(
      (sum, detalle) => sum + (parseFloat(detalle.aguinaldo_neto) || 0),
      0
    );
  }

  public esBorrador(): boolean {
    return this.aguinaldo.estado === this.ESTADOS_AGUINALDO.BORRADOR;
  }

  public estaPagado(): boolean {
    return this.aguinaldo.estado === this.ESTADOS_AGUINALDO.PAGADO;
  }

  public getEstadoNombre(estado: number): string {
    if (estado === this.ESTADOS_AGUINALDO.BORRADOR) {
      return 'Borrador';
    } else if (estado === this.ESTADOS_AGUINALDO.PAGADO) {
      return 'Pagado';
    }
    return 'Desconocido';
  }

  public getEstadoBadgeClass(estado: number): string {
    if (estado === this.ESTADOS_AGUINALDO.BORRADOR) {
      return 'bg-warning';
    } else if (estado === this.ESTADOS_AGUINALDO.PAGADO) {
      return 'bg-success';
    }
    return 'bg-secondary';
  }

  public actualizarFechaCalculo(event: any) {
    const nuevaFecha = event.target.value;
    if (!nuevaFecha) {
      return;
    }

    this.saving = true;
    // Usar la ruta específica para actualizar fecha de cálculo
    // La ruta es: PUT /aguinaldos/{id}/fecha-calculo
    const url = this.apiUrl + 'aguinaldos/' + this.aguinaldo.id + '/fecha-calculo';
    
    this.http.put<any>(url, { fecha_calculo: nuevaFecha })
      .pipe(
        retry(0),
        catchError((error) => {
          return throwError(() => error);
        })
      )
      .subscribe({
        next: (response: any) => {
          // Si la respuesta incluye el aguinaldo actualizado, usarlo
          if (response.aguinaldo) {
            this.aguinaldo.fecha_calculo = response.aguinaldo.fecha_calculo || nuevaFecha;
          } else {
            this.aguinaldo.fecha_calculo = nuevaFecha;
          }
          this.alertService.success('Éxito', 'Fecha de cálculo actualizada exitosamente');
          this.saving = false;
          // Si hay un empleado seleccionado, recalcular sugerencia con la nueva fecha
          if (this.empleadoSeleccionado) {
            this.onEmpleadoSeleccionado();
          }
        },
        error: (error: any) => {
          this.alertService.error(error);
          this.saving = false;
          // Restaurar fecha anterior
          event.target.value = this.aguinaldo.fecha_calculo || (this.aguinaldo.anio + '-12-12');
        },
      });
  }
}
