import {
  Component,
  OnInit,
  TemplateRef,
  Pipe,
  PipeTransform,
  ChangeDetectionStrategy,
  ChangeDetectorRef
} from '@angular/core';
import { ConfiguracionReporte, crearConfiguracionDefault, TIPOS_REPORTE } from '../../models/configuracion-reporte.interface';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import Swal from 'sweetalert2';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Pipe({
    name: 'replace',
    standalone: true
})
export class ReplacePipe implements PipeTransform {
  transform(value: string, from: string, to: string): string {
    return value.replace(new RegExp(from, 'g'), to);
  }
}

@Component({
    selector: 'app-reportes-automaticos',
    templateUrl: './reportes-automaticos.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class ReportesAutomaticosComponent extends BasePaginatedModalComponent implements OnInit {
  public configuraciones: PaginatedResponse<any> = {} as PaginatedResponse;
  public configuracionActual: ConfiguracionReporte = crearConfiguracionDefault();
  public configuracionEliminar: ConfiguracionReporte = crearConfiguracionDefault();
  public configReporteActual: ConfiguracionReporte | null = null;
  public override filtros: any = {
    buscador: '',
    paginate: 10,
    orden: 'created_at',
    direccion: 'desc'
  };
  public enviandoPrueba: boolean = false;
  public eliminando: boolean = false;
  public emailInput: string = '';
  public emailPrueba: string = '';
  public downloading: boolean = false;
  public periodosExpandidos: boolean = false;
  public diasSemana: any[] = [
    { id: 1, nombre: 'Lunes', seleccionado: false },
    { id: 2, nombre: 'Martes', seleccionado: false },
    { id: 3, nombre: 'Miércoles', seleccionado: false },
    { id: 4, nombre: 'Jueves', seleccionado: false },
    { id: 5, nombre: 'Viernes', seleccionado: false },
    { id: 6, nombre: 'Sábado', seleccionado: false },
    { id: 7, nombre: 'Domingo', seleccionado: false },
  ];
  public diasMes: number[] = Array.from({ length: 31 }, (_, i) => i + 1);
  public tiposReporteActivos: string[] = [];
  public categorias: any[] = [];
  //Estado Financiero Consolidado por Sucursales
  public tiposReporte: any[] = [
    { tipo: 'ventas-por-vendedor', nombre: 'Ventas por Vendedor' },
    {
      tipo: 'ventas-por-categoria-vendedor',
      nombre: 'Ventas por Categoría y Vendedor'
    },
    {
      tipo: 'estado-financiero-consolidado-sucursales',
      nombre: 'Estado Financiero Consolidado por Sucursales'
    },
    {
      tipo: 'detalle-ventas-vendedor',
      nombre: 'Detalle de Ventas por Vendedor'
    },
    {
      tipo: 'inventario-por-sucursal',
      nombre: 'Inventario por Sucursal'
    },
    {
      tipo: 'ventas-por-utilidades',
      nombre: 'Ventas por Utilidades',
    },
    {
      tipo: 'cobros-por-vendedor',
      nombre: 'Cobros por Vendedor',
    },
  ];
  public modalRefFechas!: BsModalRef;
  public fechaInicio: string = '';
  public fechaFin: string = '';
  public fechaHoy: string = new Date().toISOString().split('T')[0];
  public sucursales: any[] = [];
  public tipoReporte: string = '';
  public reportesDisponiblesPdf: string[] = [
    'ventas-por-categoria-vendedor',
    'detalle-ventas-vendedor',
  ];

  modalRefPrueba!: BsModalRef;
  modalRefEliminar!: BsModalRef;

  constructor(
    apiService: ApiService,
    alertService: AlertService,
    modalManager: ModalManagerService,
    private cdr: ChangeDetectorRef
  ) {
    super(apiService, alertService, modalManager);
  }

  protected getPaginatedData(): PaginatedResponse | null {
    return this.configuraciones;
  }

  protected setPaginatedData(data: PaginatedResponse): void {
    this.configuraciones = data;
  }

  ngOnInit() {
    this.loadAll();
    this.obtenerEmpresa();
  }

  public loadAll() {
    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.getAll('reportes-configuracion', this.filtros).pipe(this.untilDestroyed()).subscribe(
      (configuraciones) => {
        this.configuraciones = configuraciones;
        this.loading = false;

        // Obtener los tipos de reporte activos para validación
        this.actualizarTiposReporteActivos();
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );
    this.apiService.getAll('categorias/list').pipe(this.untilDestroyed()).subscribe(
      (categorias) => {
        this.categorias = categorias;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
      }
    );
    this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  private actualizarTiposReporteActivos() {
    this.tiposReporteActivos = [];
    if (this.configuraciones && this.configuraciones.data) {
      this.configuraciones.data.forEach((config: any) => {
        if (config.activo) {
          // Para tipos de reporte diferentes a "ventas-por-categoria-vendedor", seguimos validando solo por tipo
          if (config.tipo_reporte !== 'ventas-por-categoria-vendedor') {
            this.tiposReporteActivos.push(config.tipo_reporte);
          } else {
            // Para "ventas-por-categoria-vendedor", registramos combinación de tipo y sucursales
            const sucursalesStr = [...(config.sucursales || [])]
              .sort()
              .join(',');
            this.tiposReporteActivos.push(
              `${config.tipo_reporte}|${sucursalesStr}`
            );
          }
        }
      });
    }
  }

  public filtrarConfiguraciones() {
    this.loadAll();
    this.cdr.markForCheck();
  }

  // setPagination() ahora se hereda de BasePaginatedComponent
  
  protected override onPaginateSuccess(response: PaginatedResponse): void {
    this.actualizarTiposReporteActivos();
  }

  public formatHora(hora: string): string {
    try {
      const [hours, minutes] = hora.split(':');
      const date = new Date();
      date.setHours(parseInt(hours, 10));
      date.setMinutes(parseInt(minutes, 10));

      return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      return hora;
    }
  }

  openModalConfigurar(template: TemplateRef<any>) {
    this.configuracionActual = crearConfiguracionDefault();

    // Restablecer los días de la semana seleccionados
    this.diasSemana.forEach((dia) => (dia.seleccionado = false));

    this.categorias.forEach((categoria) => {
      categoria.seleccionada = true;
      categoria.porcentaje = 100;
    });

    this.actualizarCategoriasSeleccionadas();

    // Seleccionar automáticamente todas las sucursales al crear una nueva configuración
    if (this.sucursales && this.sucursales.length > 0) {
      this.configuracionActual.sucursales = this.sucursales.map((s) => s.id);
    }

    super.openLargeModal(template);
  }

  override openModal(template: TemplateRef<any>, configuracion: any) {
    if (!configuracion || configuracion === null) {
      this.configuracionActual = crearConfiguracionDefault();
    } else {
      this.configuracionActual = { ...configuracion };

      // Reiniciar todas las categorías
      this.categorias.forEach((categoria) => {
        categoria.seleccionada = false;
        categoria.porcentaje = 0;
      });

      if (
        this.configuracionActual.tipo_reporte ===
        'ventas-por-categoria-vendedor'
      ) {
        // Configurar las categorías seleccionadas
        if (this.configuracionActual.configuracion) {
          this.configuracionActual.configuracion.forEach((configCat: any) => {
            const categoriaEncontrada = this.categorias.find(
              (cat) => cat.id === configCat.id
            );
            if (categoriaEncontrada) {
              categoriaEncontrada.seleccionada = true;
              categoriaEncontrada.porcentaje = configCat.porcentaje || 100;
            }
          });
        }
      }

      // Si es semanal, configurar los días seleccionados
      if (
        this.configuracionActual.frecuencia === 'semanal' &&
        this.configuracionActual.dias_semana
      ) {
        // Reiniciar todos los días
        this.diasSemana.forEach((dia) => (dia.seleccionado = false));

        // Seleccionar los días guardados
        this.diasSemana.forEach((dia) => {
          if (this.configuracionActual.dias_semana?.includes(dia.id)) {
            dia.seleccionado = true;
          }
        });
      }
    }

    super.openLargeModal(template);
  }

  public guardarConfiguracion() {
    this.cdr.markForCheck();
    // Validar que haya al menos un horario seleccionado
    if (
      !this.configuracionActual.envio_matutino &&
      !this.configuracionActual.envio_mediodia &&
      !this.configuracionActual.envio_nocturno
    ) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Debe seleccionar al menos un horario de envío'
      });
      return;
    }

    // Validar que haya al menos un destinatario
    if (this.configuracionActual.destinatarios.length === 0) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Debe agregar al menos un destinatario'
      });
      return;
    }

    // Recopilar los días de la semana seleccionados si la frecuencia es semanal
    if (this.configuracionActual.frecuencia === 'semanal') {
      this.configuracionActual.dias_semana = this.diasSemana
        .filter((dia) => dia.seleccionado)
        .map((dia) => dia.id);

      // Validar que haya al menos un día seleccionado
      if (this.configuracionActual.dias_semana.length === 0) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Debe seleccionar al menos un día de la semana'
        });
        return;
      }
    }

    // Validar que haya al menos una sucursal seleccionada
    if (
      !this.configuracionActual.sucursales ||
      this.configuracionActual.sucursales.length === 0
    ) {
      // Si no hay sucursales seleccionadas, seleccionar todas
      if (this.sucursales && this.sucursales.length > 0) {
        this.configuracionActual.sucursales = this.sucursales.map((s) => s.id);
      }
    }

    // Ordenar las sucursales seleccionadas para facilitar la comparación
    const sucursalesOrdenadas = [...this.configuracionActual.sucursales]
      .sort()
      .join(',');

    // Verificar si ya existe un reporte activo del mismo tipo y con las mismas sucursales
    if (this.configuracionActual.activo) {
      const existeReporteActivo = this.configuraciones.data.some(
        (c: any) =>
          c.tipo_reporte === this.configuracionActual.tipo_reporte &&
          c.activo &&
          c.id !== this.configuracionActual.id &&
          [...(c.sucursales || [])].sort().join(',') === sucursalesOrdenadas
      );

      if (existeReporteActivo) {
        Swal.fire({
          icon: 'warning',
          title: 'Advertencia',
          text: 'Ya existe una configuración activa para este tipo de reporte con las mismas sucursales seleccionadas.',
          showCancelButton: true,
          confirmButtonText: 'Continuar y desactivar la existente',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            // El usuario decidió continuar y desactivar la configuración existente
            this.saving = true;
            this.cdr.markForCheck();
            this.apiService
              .store('reportes-configuracion', this.configuracionActual)
              .pipe(this.untilDestroyed())
              .subscribe(
                (response) => {
                  this.saving = false;
                  this.loadAll();
                  this.closeModal();
                  this.alertService.success(
                    this.configuracionActual.id
                      ? 'Configuración actualizada'
                      : 'Configuración creada',
                    'La configuración de reportes ha sido guardada exitosamente.'
                  );
                  this.cdr.markForCheck();
                },
                (error) => {
                  this.alertService.error(error);
                  this.saving = false;
                  this.cdr.markForCheck();
                }
              );
          }
        });
        return;
      }
    }

    this.saving = true;
    this.cdr.markForCheck();
    this.apiService
      .store('reportes-configuracion', this.configuracionActual)
      .pipe(this.untilDestroyed())
      .subscribe(
        (response) => {
          this.saving = false;
          this.loadAll();
          this.modalRef?.hide();
          this.alertService.success(
            this.configuracionActual.id
              ? 'Configuración actualizada'
              : 'Configuración creada',
            'La configuración de reportes ha sido guardada exitosamente.'
          );
          this.cdr.markForCheck();
        },
        (error) => {
          this.alertService.error(error);
          this.saving = false;
          this.cdr.markForCheck();
        }
      );
  }

  public agregarEmail() {
    if (!this.emailInput) return;

    // Validar formato de email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(this.emailInput)) {
      this.alertService.warning(
        'Error',
        'Ingrese un correo electrónico válido'
      );
      return;
    }

    // Comprobar si ya existe
    if (this.configuracionActual.destinatarios.includes(this.emailInput)) {
      this.alertService.warning('Error', 'Este correo ya ha sido agregado');
      return;
    }

    this.configuracionActual.destinatarios.push(this.emailInput);
    this.emailInput = '';
    this.cdr.markForCheck();
  }

  public eliminarEmail(index: number) {
    this.configuracionActual.destinatarios.splice(index, 1);
    this.cdr.markForCheck();
  }

  public cambiarEstado(config: any) {
    const nuevoEstado = !config.activo;
    const configId = config.id;


    if (nuevoEstado) {
      let existeReporteActivo = false;

      if (config.tipo_reporte !== 'ventas-por-categoria-vendedor') {

        existeReporteActivo = this.tiposReporteActivos.includes(
          config.tipo_reporte
        );
      } else {
        const sucursalesStr = [...(config.sucursales || [])].sort().join(',');
        existeReporteActivo = this.tiposReporteActivos.includes(
          `${config.tipo_reporte}|${sucursalesStr}`
        );
      }

      if (existeReporteActivo) {
        Swal.fire({
          icon: 'warning',
          title: 'Advertencia',
          text:
            config.tipo_reporte === 'ventas-por-categoria-vendedor'
              ? 'Ya existe una configuración activa para este tipo de reporte con las mismas sucursales seleccionadas.'
              : 'Ya existe una configuración activa para este tipo de reporte. Solo puede haber una configuración activa por tipo de reporte.',
          showCancelButton: true,
          confirmButtonText: 'Continuar y desactivar la existente',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            // El usuario decidió continuar y desactivar la configuración existente
            this.ejecutarCambioEstado(configId, nuevoEstado);
          }
        });
        return;
      }
    }

    // Si no hay conflicto o se está desactivando, proceder normalmente
    this.ejecutarCambioEstado(configId, nuevoEstado);
  }

  private ejecutarCambioEstado(configId: number, nuevoEstado: boolean) {
    this.apiService
      .update('reportes-configuracion/estado', configId, {
        activo: nuevoEstado
      })
      .pipe(this.untilDestroyed())
      .subscribe(
        (response) => {
          this.loadAll();
          this.alertService.success(
            'Estado actualizado',
            `La configuración ha sido ${nuevoEstado ? 'activada' : 'desactivada'
            } exitosamente.`
          );
          this.cdr.markForCheck();
        },
        (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
  }

  public enviarReportePrueba(template: TemplateRef<any>, config?: any) {
    this.configuracionEliminar = config;
    this.emailPrueba = '';

    this.modalRefPrueba = this.modalManager.openModal(template, {
      class: 'modal-lg',
      backdrop: 'static'
    });
  }


  public confirmarEnvioPrueba() {
    this.enviandoPrueba = true;
    this.cdr.markForCheck();


    if (!this.fechaInicio || !this.fechaFin) {
      this.seleccionarPeriodo('mes');
    }

    const data = {
      id_configuracion: this.configuracionEliminar.id,
      email_prueba: this.emailPrueba,
      fecha_inicio: this.fechaInicio,
      fecha_fin: this.fechaFin,
      sucursales: this.configuracionEliminar.sucursales || []
    };

    this.apiService
      .store('reportes-configuracion/enviar-prueba', data)
      .pipe(this.untilDestroyed())
      .subscribe(
        (response) => {
          this.enviandoPrueba = false;
          this.modalRefPrueba?.hide();
          this.alertService.success(
            'Reporte enviado',
            `El reporte de prueba para el período ${this.fechaInicio} al ${this.fechaFin} ha sido enviado correctamente.`
          );
          this.cdr.markForCheck();
        },
        (error) => {
          this.enviandoPrueba = false;
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
  }

  public eliminarConfiguracion(template: TemplateRef<any>, config: any) {
    this.configuracionEliminar = config;

    this.modalRefEliminar = this.modalManager.openModal(template, {
      class: 'modal-lg',
      backdrop: 'static'
    });
  }

  public confirmarEliminacion() {
    this.eliminando = true;
    this.cdr.markForCheck();

    this.apiService
      .delete('reportes-configuracion/', this.configuracionEliminar.id!)
      .pipe(this.untilDestroyed())
      .subscribe(
        (response) => {
          this.eliminando = false;
          this.modalRefEliminar?.hide();
          this.loadAll();
          this.alertService.success(
            'Configuración eliminada',
            'La configuración de reportes ha sido eliminada exitosamente.'
          );
          this.cdr.markForCheck();
        },
        (error) => {
          this.eliminando = false;
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
  }

  private obtenerEmpresa(): number {
    const empresa = localStorage.getItem('SP_auth_user');
    if (empresa) {
      const empresaObj = JSON.parse(empresa);
      return empresaObj.empresa.nombre;
    }
    return 0;
  }

  public tipoReporteChanged(value: string) {
    value = value.replace(/-/g, ' ');
    value = value.replace(/\s+/g, ' ');
    value = value.trim();
    const empresa = this.obtenerEmpresa();

    this.configuracionActual.asunto_correo =
      `Reporte diario de ${value}` + ' ' + empresa;

    if (value !== 'ventas por categoria vendedor') {
      // Verificar si ya existe una configuración activa para este tipo de reporte
      if (this.tiposReporteActivos.includes(value.replace(/\s+/g, '-'))) {
        setTimeout(() => {
          Swal.fire({
            icon: 'info',
            title: 'Información',
            text: 'Ya existe una configuración activa para este tipo de reporte. Solo puede haber una configuración activa por tipo de reporte. Si continúa, la configuración existente será desactivada.'
          });
        }, 100);
      }
    }
  }

  public seleccionarTodos(event: any) {
    const checked = event.target.checked;
    this.categorias.forEach((categoria) => {
      categoria.seleccionada = checked;
      if (checked) {
        categoria.porcentaje = 100;
      } else {
        categoria.porcentaje = 0;
      }
    });
    this.actualizarCategoriasSeleccionadas();
    this.cdr.markForCheck();
  }

  public actualizarPorcentaje(categoria: any) {
    if (categoria.seleccionada) {
      const index = this.configuracionActual.configuracion.findIndex(
        (cat: any) => cat.id === categoria.id
      );

      if (index !== -1) {
        this.configuracionActual.configuracion[index].porcentaje =
          categoria.porcentaje;
      }
    }
    this.cdr.markForCheck();
  }

  public actualizarCategoriasSeleccionadas() {
    this.configuracionActual.configuracion = this.categorias
      .filter((categoria) => {
        if (
          categoria.seleccionada &&
          (!categoria.porcentaje || categoria.porcentaje === 0)
        ) {
          categoria.porcentaje = 100;
        }
        return categoria.seleccionada;
      })
      .map((categoria) => ({
        id: categoria.id,
        nombre: categoria.nombre,
        porcentaje: categoria.porcentaje || 100
      }));
  }

  public seleccionarTodosBtn() {
    // Determinar si hay alguna categoría no seleccionada
    const hayAlgunaNoSeleccionada = this.categorias.some(
      (cat) => !cat.seleccionada
    );
    const nuevoEstado = hayAlgunaNoSeleccionada;

    this.categorias.forEach((categoria) => {
      categoria.seleccionada = nuevoEstado;
      if (nuevoEstado) {
        categoria.porcentaje = 100;
      } else {
        categoria.porcentaje = 0;
      }
    });

    this.actualizarCategoriasSeleccionadas();
    this.cdr.markForCheck();
  }


  public descargarReporte(config: any, template: TemplateRef<any>, tipo: string = 'excel') {
    this.configReporteActual = config;
    this.tipoReporte = tipo;

    if (config.tipo_reporte === 'inventario-por-sucursal') {
      this.seleccionarPeriodo('mes');
      this.descargarReporteDirecto();
    } else {
      this.fechaInicio = '';
      this.fechaFin = '';

      this.seleccionarPeriodo('mes');

      this.modalRefFechas = this.modalManager.openModal(template, {
        class: 'modal-lg',
        backdrop: 'static'
      });
    }
  }

  public descargarReporteDirecto() {
    let tipo = this.configReporteActual?.tipo_reporte;
    tipo = this.tiposReporte.find((t: any) => t.tipo === tipo)?.nombre || tipo;

    this.downloading = true;
    this.cdr.markForCheck();

    // Preparar parámetros para la petición
    const params = {
      id: this.configReporteActual?.id,
      fecha_inicio: this.fechaInicio,
      fecha_fin: this.fechaFin,
      sucursales: this.configReporteActual?.sucursales || []
    };

    // Determinar la ruta y tipo de archivo según el tipo de reporte
    let route = 'reportes-configuracion/exportar';

    if (this.tipoReporte === 'pdf') {
      route = 'reportes-configuracion/exportar-pdf';
    }

    // Realizar la petición al servidor
    this.apiService.exportAcumuladoReportes(route, params)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response: any) => {
          // Crear nombre de archivo
          let nombreArchivo = `inventario_por_sucursal_`;

          // Añadir información de sucursales al nombre
          if (params.sucursales && params.sucursales.length > 0) {
            if (params.sucursales.length === this.sucursales.length) {
              nombreArchivo += '_todas_sucursales';
            } else if (params.sucursales.length <= 3) {
              // Solo incluir nombres si son pocas sucursales
              const sucursalesNombres = params.sucursales
                .map((id: number) => {
                  return this.sucursales.find((s) => s.id == id)?.nombre || id;
                })
                .join('-');
              nombreArchivo += `_${sucursalesNombres}`;
            } else {
              // Si son muchas, solo indicar el número
              nombreArchivo += `_${params.sucursales.length}_sucursales`;
            }
          }

          if (this.tipoReporte === 'pdf') {
            // Para detectar el tipo real sin descargar múltiples archivos
            const fileReader = new FileReader();
            const blob = new Blob([response]);

            fileReader.onload = () => {
              const arrayBuffer = fileReader.result as ArrayBuffer;
              const headerBytes = new Uint8Array(arrayBuffer.slice(0, 4));

              // Determinar el tipo de archivo basado en los primeros bytes
              let fileType = 'pdf';
              let mimeType = 'application/pdf';

              // 50 4b 03 04 es la firma de ZIP (PK..)
              if (headerBytes[0] === 0x50 && headerBytes[1] === 0x4B) {
                fileType = 'zip';
                mimeType = 'application/zip';
                this.alertService.info('warning', 'El reporte es muy grande y se ha descargado como un archivo ZIP que contiene múltiples PDFs.');
              }

              // Crear un nuevo blob con el tipo MIME correcto
              const tipoCorrectoBlob = new Blob([response], { type: mimeType });

              // Verificar si hay contenido
              if (tipoCorrectoBlob.size === 0) {
                this.alertService.error(`El archivo generado está vacío`);
                this.downloading = false;
                return;
              }

              // Ahora sí descargamos el archivo con el tipo correcto
              this.procesarDescarga(tipoCorrectoBlob, nombreArchivo, fileType);

              this.downloading = false;
              this.cdr.markForCheck();

              // Mostrar mensaje de éxito
              this.alertService.success(
                'Descarga completada',
                `El reporte se ha descargado correctamente.`
              );
            };

            // Leer solo los primeros bytes para detectar la firma
            fileReader.readAsArrayBuffer(blob.slice(0, 4));
          } else {
            // Para Excel, mantenemos el comportamiento original
            const fileType = 'xlsx';
            const mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            const blob = new Blob([response], { type: mimeType });

            // Verificar si hay contenido
            if (blob.size === 0) {
              this.alertService.error(`El archivo Excel generado está vacío`);
              this.downloading = false;
              this.cdr.markForCheck();
              return;
            }

            // Crear URL y elemento para descarga
            this.procesarDescarga(blob, nombreArchivo, fileType);

            this.downloading = false;
            this.cdr.markForCheck();

            // Mostrar mensaje de éxito
            this.alertService.success(
              'Descarga completada',
              `El reporte se ha descargado correctamente.`
            );
          }
        },
        error: (error) => {
          console.error('Error al descargar el reporte:', error);
          this.alertService.error('Error al generar el reporte. Por favor intente nuevamente');
          this.downloading = false;
          this.cdr.markForCheck();
        }
      });
  }

  public descargarReporteConFechas() {
    // Validar fechas
    if (!this.fechaInicio || !this.fechaFin || this.fechaInicio > this.fechaFin) {
      this.alertService.error('Por favor seleccione un rango de fechas válido');
      return;
    }

    let tipo = this.configReporteActual?.tipo_reporte;
    tipo = this.tiposReporte.find((t: any) => t.tipo === tipo)?.nombre || tipo;

    this.downloading = true;
    this.cdr.markForCheck();

    // Preparar parámetros para la petición
    const params = {
      id: this.configReporteActual?.id,
      fecha_inicio: this.fechaInicio,
      fecha_fin: this.fechaFin,
      sucursales: this.configReporteActual?.sucursales || []
    };

    // Determinar la ruta y tipo de archivo según el tipo de reporte
    let route = 'reportes-configuracion/exportar';

    if (this.tipoReporte === 'pdf') {
      route = 'reportes-configuracion/exportar-pdf';
    }

    // Realizar la petición al servidor
    this.apiService.exportAcumuladoReportes(route, params)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response: any) => {
          // Crear nombre de archivo
          let nombreArchivo = `${tipo}_${this.fechaInicio}_al_${this.fechaFin}`;

          // Añadir información de sucursales al nombre
          if (params.sucursales && params.sucursales.length > 0) {
            if (params.sucursales.length === this.sucursales.length) {
              nombreArchivo += '_todas_sucursales';
            } else if (params.sucursales.length <= 3) {
              // Solo incluir nombres si son pocas sucursales
              const sucursalesNombres = params.sucursales
                .map((id: number) => {
                  return this.sucursales.find((s) => s.id == id)?.nombre || id;
                })
                .join('-');
              nombreArchivo += `_${sucursalesNombres}`;
            } else {
              // Si son muchas, solo indicar el número
              nombreArchivo += `_${params.sucursales.length}_sucursales`;
            }
          }

          if (this.tipoReporte === 'pdf') {
            // Para detectar el tipo real sin descargar múltiples archivos
            const fileReader = new FileReader();
            const blob = new Blob([response]);

            fileReader.onload = () => {
              const arrayBuffer = fileReader.result as ArrayBuffer;
              const headerBytes = new Uint8Array(arrayBuffer.slice(0, 4));

              console.log('Primeros bytes:', Array.from(headerBytes).map(b => b.toString(16).padStart(2, '0')).join(' '));

              // Determinar el tipo de archivo basado en los primeros bytes
              let fileType = 'pdf';
              let mimeType = 'application/pdf';

              // 50 4b 03 04 es la firma de ZIP (PK..)
              if (headerBytes[0] === 0x50 && headerBytes[1] === 0x4B) {
                console.log('¡Detectado archivo ZIP!');
                fileType = 'zip';
                mimeType = 'application/zip';
                this.alertService.info('warning', 'El reporte es muy grande y se ha descargado como un archivo ZIP que contiene múltiples PDFs.');
              }

              // Crear un nuevo blob con el tipo MIME correcto
              const tipoCorrectoBlob = new Blob([response], { type: mimeType });

              // Verificar si hay contenido
              if (tipoCorrectoBlob.size === 0) {
                this.alertService.error(`El archivo generado está vacío`);
                this.downloading = false;
                return;
              }

              // Ahora sí descargamos el archivo con el tipo correcto
              this.procesarDescarga(tipoCorrectoBlob, nombreArchivo, fileType);

              this.downloading = false;
              this.modalRefFechas.hide();
              this.cdr.markForCheck();
            };

            // Leer solo los primeros bytes para detectar la firma
            fileReader.readAsArrayBuffer(blob.slice(0, 4));
          } else {
            // Para Excel, mantenemos el comportamiento original
            const fileType = 'xlsx';
            const mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            const blob = new Blob([response], { type: mimeType });

            // Verificar si hay contenido
            if (blob.size === 0) {
              this.alertService.error(`El archivo Excel generado está vacío`);
              this.downloading = false;
              this.cdr.markForCheck();
              return;
            }

            // Crear URL y elemento para descarga
            this.procesarDescarga(blob, nombreArchivo, fileType);

            this.downloading = false;
            this.modalRefFechas.hide();
            this.cdr.markForCheck();
          }
        },
        error: (error) => {
          console.error('Error al descargar el reporte:', error);
          this.alertService.error('Error al generar el reporte. Por favor intente nuevamente');
          this.downloading = false;
          this.cdr.markForCheck();
        }
      });
  }

  // Método auxiliar para procesar la descarga del archivo
  private procesarDescarga(blob: Blob, nombreArchivo: string, extension: string) {
    // Crear objeto URL para el blob
    const url = window.URL.createObjectURL(blob);

    // Crear elemento <a> para la descarga
    const a = document.createElement('a');
    a.href = url;
    a.download = `${nombreArchivo}.${extension}`;

    // Añadir al DOM, hacer clic y remover
    document.body.appendChild(a);
    a.click();

    // Limpiar recursos
    setTimeout(() => {
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    }, 100);
  }


  public toggleMostrarPeriodos(): void {
    this.periodosExpandidos = !this.periodosExpandidos;
    this.cdr.markForCheck();
  }

  // Método para seleccionar períodos predefinidos
  public seleccionarPeriodo(periodo: string) {
    const hoy = new Date();
    let fechaInicio = new Date();
    let fechaFin = new Date();

    switch (periodo) {
      // Días
      case 'hoy':
        fechaInicio = new Date(hoy);
        fechaFin = new Date(hoy);
        break;

      case 'ayer':
        fechaInicio = new Date(hoy);
        fechaInicio.setDate(hoy.getDate() - 1);
        fechaFin = new Date(fechaInicio);
        break;

      case 'ultimos3':
        fechaInicio = new Date(hoy);
        fechaInicio.setDate(hoy.getDate() - 2);
        break;

      case 'ultimos7':
        fechaInicio = new Date(hoy);
        fechaInicio.setDate(hoy.getDate() - 6);
        break;

      // Semanas
      case 'semana':
        fechaInicio = new Date(hoy);
        // Establecer al primer día de la semana (lunes)
        const diaSemana = hoy.getDay();
        const diff = diaSemana === 0 ? 6 : diaSemana - 1; // Considerar que el domingo es 0
        fechaInicio.setDate(hoy.getDate() - diff);
        break;

      case 'semanaAnterior':
        fechaInicio = new Date(hoy);
        const diffInicio = hoy.getDay() === 0 ? 6 : hoy.getDay() - 1;
        fechaInicio.setDate(hoy.getDate() - diffInicio - 7);
        fechaFin = new Date(fechaInicio);
        fechaFin.setDate(fechaInicio.getDate() + 6);
        break;

      case 'ultimas2Semanas':
        fechaInicio = new Date(hoy);
        fechaInicio.setDate(hoy.getDate() - 13);
        break;

      // Meses
      case 'mes':
        fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        break;

      case 'mesAnterior':
        fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
        fechaFin = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
        break;

      case 'ultimos3Meses':
        fechaInicio = new Date(hoy);
        fechaInicio.setMonth(hoy.getMonth() - 2);
        fechaInicio.setDate(1);
        break;

      case 'ultimos6Meses':
        fechaInicio = new Date(hoy);
        fechaInicio.setMonth(hoy.getMonth() - 5);
        fechaInicio.setDate(1);
        break;

      // Trimestres y Año
      case 'trimestre':
        const trimestreActual = Math.floor(hoy.getMonth() / 3);
        fechaInicio = new Date(hoy.getFullYear(), trimestreActual * 3, 1);
        fechaFin = new Date(hoy.getFullYear(), trimestreActual * 3 + 3, 0);
        break;

      case 'trimestreAnterior':
        const trimestreAnteriorMes = Math.floor((hoy.getMonth() - 3) / 3) * 3;
        const anioTrimestreAnterior =
          hoy.getFullYear() + Math.floor(trimestreAnteriorMes / 12);
        const mesTrimestreAnterior = ((trimestreAnteriorMes % 12) + 12) % 12;
        fechaInicio = new Date(anioTrimestreAnterior, mesTrimestreAnterior, 1);
        fechaFin = new Date(anioTrimestreAnterior, mesTrimestreAnterior + 3, 0);
        break;

      case 'anio':
        fechaInicio = new Date(hoy.getFullYear(), 0, 1);
        fechaFin = new Date(hoy.getFullYear(), 11, 31);
        break;

      case 'anioAnterior':
        fechaInicio = new Date(hoy.getFullYear() - 1, 0, 1);
        fechaFin = new Date(hoy.getFullYear() - 1, 11, 31);
        break;
    }

    // Convertir las fechas a formato YYYY-MM-DD
    this.fechaInicio = fechaInicio.toISOString().split('T')[0];
    this.fechaFin = fechaFin.toISOString().split('T')[0];
    this.cdr.markForCheck();
  }

  public isAllSucursalesSelected(): boolean {
    return (
      this.configuracionActual.sucursales &&
      this.sucursales.length > 0 &&
      this.configuracionActual.sucursales.length === this.sucursales.length
    );
  }
  public toggleSelectAllSucursales(): void {
    if (this.isAllSucursalesSelected()) {
      this.configuracionActual.sucursales = [];
    } else {
      this.configuracionActual.sucursales = this.sucursales.map((s) => s.id);
    }
    this.cdr.markForCheck();
  }

  public getNombresSucursales(sucursalesIds: any[]): string {
    if (!sucursalesIds || sucursalesIds.length === 0) {
      return 'Todas';
    }

    if (sucursalesIds.length === this.sucursales.length) {
      return 'Todas';
    }

    if (sucursalesIds.length <= 2) {
      return sucursalesIds
        .map((id) => {
          const sucursal = this.sucursales.find((s) => s.id == id);
          return sucursal?.nombre || 'N/A';
        })
        .join(', ');
    }

    return `${sucursalesIds.length} sucursales seleccionadas`;
  }

  public getColumnasTabla() {
    return [
      { name: 'Tipo de Reporte', prop: 'tipo_reporte', flexGrow: 2 },
      { name: 'Frecuencia', prop: 'frecuencia', flexGrow: 1 },
      { name: 'Horarios', prop: 'horarios', flexGrow: 1 },
      { name: 'Destinatarios', prop: 'destinatarios', flexGrow: 1 },
      { name: 'Sucursales', prop: 'sucursales', flexGrow: 1 },
      { name: 'Estado', prop: 'activo', flexGrow: 1 },
      { name: 'Acciones', prop: 'acciones', flexGrow: 1 },
    ];
  }

  public getPermissionsPDf(tipoReporte: string): boolean {
    return this.reportesDisponiblesPdf.includes(tipoReporte);
  }
}
