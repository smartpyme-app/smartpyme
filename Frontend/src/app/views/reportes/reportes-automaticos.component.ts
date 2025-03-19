import { Component, OnInit, TemplateRef, Pipe, PipeTransform } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Pipe({
  name: 'replace'
})
export class ReplacePipe implements PipeTransform {
  transform(value: string, from: string, to: string): string {
    return value.replace(new RegExp(from, 'g'), to);
  }
}

@Component({
  selector: 'app-reportes-automaticos',
  templateUrl: './reportes-automaticos.component.html',
})
export class ReportesAutomaticosComponent implements OnInit {
  public configuraciones: any = [];
  public configuracionActual: any = {};
  public configuracionEliminar: any = {};
  public filtros: any = {
    buscador: '',
    paginate: 10,
    orden: 'created_at',
    direccion: 'desc',
  };
  public loading: boolean = false;
  public saving: boolean = false;
  public enviandoPrueba: boolean = false;
  public eliminando: boolean = false;
  public emailInput: string = '';
  public emailPrueba: string = '';
  public downloading: boolean = false;
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
    {tipo: 'ventas-por-vendedor', nombre: 'Ventas por Vendedor'},
     {tipo: 'ventas-por-categoria-vendedor', nombre: 'Ventas por Categoría y Vendedor'},
     {tipo: 'estado-financiero-consolidado-sucursales', nombre: 'Estado Financiero Consolidado por Sucursales'},
    ];
    public configReporteActual: any = null;
public modalRefFechas!: BsModalRef;
public fechaInicio: string = '';
public fechaFin: string = '';
public fechaHoy: string = new Date().toISOString().split('T')[0];

  modalRef!: BsModalRef;
  modalRefPrueba!: BsModalRef;
  modalRefEliminar!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.loadAll();
    this.obtenerEmpresa();
  }

  public loadAll() {
    this.loading = true;
    this.apiService.getAll('reportes-configuracion', this.filtros).subscribe(
      (configuraciones) => {
        this.configuraciones = configuraciones;
        this.loading = false;

        // Obtener los tipos de reporte activos para validación
        this.actualizarTiposReporteActivos();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
    this.apiService.getAll('categorias/list').subscribe(
      (categorias) => {
        this.categorias = categorias;
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
          this.tiposReporteActivos.push(config.tipo_reporte);
        }
      });
    }
  }

  public filtrarConfiguraciones() {
    this.loadAll();
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.configuraciones.path + '?page=' + event.page, this.filtros)
      .subscribe(
        (configuraciones) => {
          this.configuraciones = configuraciones;
          this.loading = false;
          this.actualizarTiposReporteActivos();
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  public formatHora(hora: string): string {
    try {
      const [hours, minutes] = hora.split(':');
      const date = new Date();
      date.setHours(parseInt(hours, 10));
      date.setMinutes(parseInt(minutes, 10));

      return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch (e) {
      return hora;
    }
  }

  openModalConfigurar(template: TemplateRef<any>) {
    this.configuracionActual = {
      activo: true,
      tipo_reporte: '',
      frecuencia: 'diario',
      destinatarios: [],
      envio_matutino: true,
      hora_matutino: '08:00',
      envio_mediodia: false,
      hora_mediodia: '13:00',
      envio_nocturno: false,
      hora_nocturno: '19:00',
      dia_mes: 1,
      asunto_correo: '',
      configuracion: [],
    };

    // Restablecer los días de la semana seleccionados
    this.diasSemana.forEach((dia) => (dia.seleccionado = false));

    this.categorias.forEach((categoria) => {
      categoria.seleccionada = true;
      categoria.porcentaje = 100;
    });

    this.actualizarCategoriasSeleccionadas();

    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  // openModal(template: TemplateRef<any>, configuracion: any) {
  //   if (!configuracion || configuracion === null) {
  //     this.configuracionActual = {};
  //   } else {
  //     this.configuracionActual = { ...configuracion };

  //     // Si es semanal, configurar los días seleccionados
  //     if (
  //       this.configuracionActual.frecuencia === 'semanal' &&
  //       this.configuracionActual.dias_semana
  //     ) {
  //       // Reiniciar todos los días
  //       this.diasSemana.forEach((dia) => (dia.seleccionado = false));

  //       // Seleccionar los días guardados
  //       this.diasSemana.forEach((dia) => {
  //         if (this.configuracionActual.dias_semana.includes(dia.id)) {
  //           dia.seleccionado = true;
  //         }
  //       });
  //     }
  //   }

  //   this.modalRef = this.modalService.show(template, {
  //     class: 'modal-lg',
  //     backdrop: 'static',
  //   });
  // }

  openModal(template: TemplateRef<any>, configuracion: any) {
    if (!configuracion || configuracion === null) {
      this.configuracionActual = {};
    } else {
      this.configuracionActual = { ...configuracion };
  
      // Reiniciar todas las categorías
      this.categorias.forEach(categoria => {
        categoria.seleccionada = false;
        categoria.porcentaje = 0;
      });
  
      if (this.configuracionActual.tipo_reporte === 'ventas-por-categoria-vendedor') {
        // Configurar las categorías seleccionadas
        if (this.configuracionActual.configuracion) {
          this.configuracionActual.configuracion.forEach((configCat: any) => {
            const categoriaEncontrada = this.categorias.find(cat => cat.id === configCat.id);
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
          if (this.configuracionActual.dias_semana.includes(dia.id)) {
            dia.seleccionado = true;
          }
        });
      }
    }
  
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public guardarConfiguracion() {
    // Validar que haya al menos un horario seleccionado
    console.log(this.configuracionActual);
    //return;
    if (
      !this.configuracionActual.envio_matutino &&
      !this.configuracionActual.envio_mediodia &&
      !this.configuracionActual.envio_nocturno
    ) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Debe seleccionar al menos un horario de envío',
      });
      return;
    }

    // Validar que haya al menos un destinatario
    if (this.configuracionActual.destinatarios.length === 0) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Debe agregar al menos un destinatario',
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
          text: 'Debe seleccionar al menos un día de la semana',
        });
        return;
      }
    }

    // Verificar si ya existe un reporte activo del mismo tipo
    if (this.configuracionActual.activo) {
      const existeReporteActivo =
        this.tiposReporteActivos.includes(
          this.configuracionActual.tipo_reporte
        ) &&
        (!this.configuracionActual.id ||
          this.configuraciones.data.some(
            (c: any) =>
              c.tipo_reporte === this.configuracionActual.tipo_reporte &&
              c.activo &&
              c.id !== this.configuracionActual.id
          ));

      if (existeReporteActivo) {
        Swal.fire({
          icon: 'warning',
          title: 'Advertencia',
          text: 'Ya existe una configuración activa para este tipo de reporte. Solo puede haber una configuración activa por tipo de reporte.',
          showCancelButton: true,
          confirmButtonText: 'Continuar y desactivar la existente',
          cancelButtonText: 'Cancelar',
        }).then((result) => {
          if (result.isConfirmed) {
            // El usuario decidió continuar y desactivar la configuración existente
            this.saving = true;
            this.apiService
              .store('reportes-configuracion', this.configuracionActual)
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
                },
                (error) => {
                  this.alertService.error(error);
                  this.saving = false;
                }
              );
          }
        });
        return;
      }
    }

    this.saving = true;
    this.apiService
      .store('reportes-configuracion', this.configuracionActual)
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
        },
        (error) => {
          this.alertService.error(error);
          this.saving = false;
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
  }

  public eliminarEmail(index: number) {
    this.configuracionActual.destinatarios.splice(index, 1);
  }

  public cambiarEstado(config: any) {
    const nuevoEstado = !config.activo;
    const configId = config.id;

    // Si se está activando, verificar si ya existe otro reporte activo del mismo tipo
    if (nuevoEstado) {
      const existeReporteActivo = this.tiposReporteActivos.includes(
        config.tipo_reporte
      );

      if (existeReporteActivo) {
        Swal.fire({
          icon: 'warning',
          title: 'Advertencia',
          text: 'Ya existe una configuración activa para este tipo de reporte. Solo puede haber una configuración activa por tipo de reporte.',
          showCancelButton: true,
          confirmButtonText: 'Continuar y desactivar la existente',
          cancelButtonText: 'Cancelar',
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
        activo: nuevoEstado,
      })
      .subscribe(
        (response) => {
          this.loadAll();
          this.alertService.success(
            'Estado actualizado',
            `La configuración ha sido ${
              nuevoEstado ? 'activada' : 'desactivada'
            } exitosamente.`
          );
        },
        (error) => {
          this.alertService.error(error);
        }
      );
  }

  public enviarReportePrueba(template: TemplateRef<any>, config?: any) {
    this.configuracionEliminar = config;
    this.emailPrueba = '';

    this.modalRefPrueba = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  // public confirmarEnvioPrueba() {
  //   this.enviandoPrueba = true;

  //   const data = {
  //     id_configuracion: this.configuracionEliminar.id,
  //     email_prueba: this.emailPrueba,
  //   };

  //   this.apiService
  //     .store('reportes-configuracion/enviar-prueba', data)
  //     .subscribe(
  //       (response) => {
  //         this.enviandoPrueba = false;
  //         this.modalRefPrueba?.hide();
  //         this.alertService.success(
  //           'Reporte enviado',
  //           'El reporte de prueba ha sido enviado correctamente.'
  //         );
  //       },
  //       (error) => {
  //         this.enviandoPrueba = false;
  //         this.alertService.error(error);
  //       }
  //     );
  // }

  public confirmarEnvioPrueba() {
    this.enviandoPrueba = true;
  
    // Asegurarnos de que tengamos fechas válidas para la prueba
    if (!this.fechaInicio || !this.fechaFin) {
      // Usar fechas predeterminadas (este mes) si no hay seleccionadas
      this.seleccionarPeriodo('mes');
    }
  
    const data = {
      id_configuracion: this.configuracionEliminar.id,
      email_prueba: this.emailPrueba,
      fecha_inicio: this.fechaInicio,
      fecha_fin: this.fechaFin
    };
  
    this.apiService
      .store('reportes-configuracion/enviar-prueba', data)
      .subscribe(
        (response) => {
          this.enviandoPrueba = false;
          this.modalRefPrueba?.hide();
          this.alertService.success(
            'Reporte enviado',
            `El reporte de prueba para el período ${this.fechaInicio} al ${this.fechaFin} ha sido enviado correctamente.`
          );
        },
        (error) => {
          this.enviandoPrueba = false;
          this.alertService.error(error);
        }
      );
  }

  public eliminarConfiguracion(template: TemplateRef<any>, config: any) {
    this.configuracionEliminar = config;

    this.modalRefEliminar = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public confirmarEliminacion() {
    this.eliminando = true;

    this.apiService
      .delete('reportes-configuracion/', this.configuracionEliminar.id)
      .subscribe(
        (response) => {
          this.eliminando = false;
          this.modalRefEliminar?.hide();
          this.loadAll();
          this.alertService.success(
            'Configuración eliminada',
            'La configuración de reportes ha sido eliminada exitosamente.'
          );
        },
        (error) => {
          this.eliminando = false;
          this.alertService.error(error);
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

    // Verificar si ya existe una configuración activa para este tipo de reporte
    if (this.tiposReporteActivos.includes(value)) {
      setTimeout(() => {
        Swal.fire({
          icon: 'info',
          title: 'Información',
          text: 'Ya existe una configuración activa para este tipo de reporte. Solo puede haber una configuración activa por tipo de reporte. Si continúa, la configuración existente será desactivada.',
        });
      }, 100);
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
        porcentaje: categoria.porcentaje || 100,
      }));
  }

  public seleccionarTodosBtn() {
    // Determinar si hay alguna categoría no seleccionada
    const hayAlgunaNoSeleccionada = this.categorias.some(cat => !cat.seleccionada);
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
  }


  // public descargarReporte(config: any) {
  //   let tipo = config.tipo_reporte;
  //   tipo = this.tiposReporte.find((t: any) => t.tipo === tipo)?.nombre;

  //   this.downloading = true; 
  //   this.saving = true;
  //       this.apiService.exportAcumulado('reportes-configuracion/exportar', config).subscribe((data:Blob) => {
  //           const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  //           const url = window.URL.createObjectURL(blob);
  //           const a = document.createElement('a');
  //           a.href = url;
  //           a.download = `${tipo}.xlsx`;
  //           document.body.appendChild(a);
  //           a.click();
  //           document.body.removeChild(a);
  //           window.URL.revokeObjectURL(url);
  //           this.downloading = false; 
  //           this.saving = false;
  //         }, (error) => {this.alertService.error(error); this.downloading = false; this.saving = false; }
  //       );
  // }


  // Método modificado para abrir el modal de selección de fechas
public descargarReporte(config: any, template: TemplateRef<any>) {
  this.configReporteActual = config;
  this.fechaInicio = '';
  this.fechaFin = '';
  
  // Establecer fechas predeterminadas (este mes)
  this.seleccionarPeriodo('mes');
  
  this.modalRefFechas = this.modalService.show(template, {
    class: 'modal-md',
    backdrop: 'static'
  });
}

// Método para descargar el reporte con las fechas seleccionadas
public descargarReporteConFechas() {
  if (!this.fechaInicio || !this.fechaFin || this.fechaInicio > this.fechaFin) {
    return;
  }
  
  let tipo = this.configReporteActual.tipo_reporte;
  tipo = this.tiposReporte.find((t: any) => t.tipo === tipo)?.nombre || tipo;

  this.downloading = true;
  
  const params = {
    id: this.configReporteActual.id,
    fecha_inicio: this.fechaInicio,
    fecha_fin: this.fechaFin
  };

  this.apiService.exportAcumulado('reportes-configuracion/exportar', params).subscribe(
    (data: Blob) => {
      const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${tipo}_${this.fechaInicio}_al_${this.fechaFin}.xlsx`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      this.downloading = false;
      this.modalRefFechas.hide();
    },
    (error) => {
      this.alertService.error(error);
      this.downloading = false;
    }
  );
}

// Método para seleccionar períodos predefinidos
public seleccionarPeriodo(periodo: string) {
  const hoy = new Date();
  let fechaInicio = new Date();
  let fechaFin = new Date();
  
  switch(periodo) {
    case 'hoy':
      fechaInicio = hoy;
      fechaFin = hoy;
      break;
    
    case 'ayer':
      fechaInicio = new Date(hoy);
      fechaInicio.setDate(hoy.getDate() - 1);
      fechaFin = new Date(hoy);
      fechaFin.setDate(hoy.getDate() - 1);
      break;
    
    case 'semana':
      fechaInicio = new Date(hoy);
      // Establecer al primer día de la semana (lunes)
      const diaSemana = hoy.getDay();
      const diff = diaSemana === 0 ? 6 : diaSemana - 1; // Considerar que el domingo es 0
      fechaInicio.setDate(hoy.getDate() - diff);
      break;
    
    case 'mes':
      fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
      break;
    
    case 'mesAnterior':
      fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
      fechaFin = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
      break;
    
    case 'anio':
      fechaInicio = new Date(hoy.getFullYear(), 0, 1);
      break;
  }
  
  // Convertir las fechas a formato YYYY-MM-DD
  this.fechaInicio = fechaInicio.toISOString().split('T')[0];
  this.fechaFin = fechaFin.toISOString().split('T')[0];
}

  
}
