import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TemplateRef, ChangeDetectorRef } from '@angular/core';
import { PlanillaConstants } from '../../../constants/planilla.constants';
import { createDuration } from '@fullcalendar/core/internal';

@Component({
  selector: 'app-administrar-empleado',
  styles: [
    `
      .cursor-pointer {
        cursor: pointer;
      }
    `,
  ],
  templateUrl: './administrar-empleado.component.html',
})
export class AdministrarEmpleadoComponent implements OnInit {
  private eventListener: any;
  public empleado: any = {};
  public loading = false;
  public saving = false;
  public departamentos: any = [];
  public cargos: any = [];
  public paises: any = [];
  public departamentosSV: any = [];
  public distritos: any = [];
  public municipios: any = [];
  public departamento: any = {};
  public cargo: any = {};
  public tiposContrato: any[] = [];
  public tiposJornada: any[] = [];
  public estadosEmpleado: any[] = [];
  public id_empresa: any;
  public id_sucursal: any;
  public cargosFiltrados: any[] = [];

  public activeTab: string = 'informacion';
  public historialContratos: any[] = [];
  public historialBajas: any[] = [];
  public nuevoDocumento: any = {
    tipo_documento: '',
    fecha_documento: '',
    fecha_vencimiento: null,
  };
  public archivoSeleccionado: File | null = null;

  public tiposDocumento = PlanillaConstants.TIPO_DOCUMENTO;

  public documentos: any = {
    data: [],
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 10
  };
  public documentosFiltrados: any[] = [];
  public filtrosDocumentos = {
    tipo: '',
    orden: 'fecha_documento',
    direccion: 'desc',
    pagina: 1
  };

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private route: ActivatedRoute,
    private router: Router,
    private changeDetectorRef: ChangeDetectorRef,
    
  ) {
    this.eventListener = () => {
      this.setActiveTab('historiales');
    };
    window.addEventListener('cambiarTabHistorial', this.eventListener);

    // ngOnDestroy() {
    //   // Importante: remover el listener cuando el componente se destruye
    //   window.removeEventListener('cambiarTabHistorial', this.eventListener);
    // }

  }

  ngOnInit() {
    this.loadAll();
    this.loadCatalogos();

    // Cargar catálogos de localStorage
    this.paises = JSON.parse(localStorage.getItem('paises')!);
    this.departamentosSV = JSON.parse(localStorage.getItem('departamentos')!);
    this.distritos = JSON.parse(localStorage.getItem('distritos')!);
    this.municipios = JSON.parse(localStorage.getItem('municipios')!);
    this.empleado = {
      estado: PlanillaConstants.ESTADOS_EMPLEADO.ACTIVO,
      tipo_contrato: PlanillaConstants.TIPOS_CONTRATO.PERMANENTE,
      tipo_jornada: PlanillaConstants.TIPOS_JORNADA.TIEMPO_COMPLETO,
      contacto_emergencia: {
        nombre: '',
        relacion: '',
        telefono: '',
        direccion: '',
      },
    };
    this.id_empresa = JSON.parse(
      localStorage.getItem('SP_auth_user')!
    ).id_empresa;
    this.id_sucursal = JSON.parse(
      localStorage.getItem('SP_auth_user')!
    ).id_sucursal;

    this.departamentos = this.departamentos.map((dept: any) => ({
      ...dept,
      id: typeof dept.id === 'string' ? parseInt(dept.id) : dept.id,
    }));

    this.tiposContrato = Object.entries(PlanillaConstants.LISTAS.TIPOS_CONTRATO).map(([id, nombre]) => ({
      id: parseInt(id),
      nombre: nombre
    }));

    this.tiposJornada = Object.entries(PlanillaConstants.LISTAS.TIPOS_JORNADA || {}).map(([id, nombre]) => ({
      id: parseInt(id),
      nombre: nombre
    }));
  
    this.estadosEmpleado = Object.entries(PlanillaConstants.LISTAS.ESTADOS_EMPLEADO || {}).map(([id, nombre]) => ({
      id: parseInt(id),
      nombre: nombre
    }));

    this.inicializarEmpleado();
  }

  public getTipoDocumento(tipo: number): string {
    const TIPO_DOCUMENTO_RENUNCIA = 1;
    const TIPO_DOCUMENTO_DESPIDO = 2;
    const TIPO_DOCUMENTO_TERMINACION = 3;
    const TIPO_DOCUMENTO_ALTA = 4;
    const TIPO_DOCUMENTO_DUI = 5;
    const TIPO_DOCUMENTO_NIT = 6;
    const TIPO_DOCUMENTO_ISSS = 7;
    const TIPO_DOCUMENTO_AFP = 8;
    const TIPO_DOCUMENTO_TITULO = 9;
    const TIPO_DOCUMENTO_CERTIFICACIONES = 10;
    const TIPO_DOCUMENTO_OTRO = 11;

    switch (tipo) {
      case TIPO_DOCUMENTO_RENUNCIA:
        return 'Renuncia';
      case TIPO_DOCUMENTO_DESPIDO:
        return 'Despido';
      case TIPO_DOCUMENTO_TERMINACION:
        return 'Terminación de contrato';
      case TIPO_DOCUMENTO_ALTA:
        return 'Alta';
      case TIPO_DOCUMENTO_DUI:
        return 'DUI';
      case TIPO_DOCUMENTO_NIT:
        return 'NIT';
      case TIPO_DOCUMENTO_ISSS:
        return 'ISSS';
      case TIPO_DOCUMENTO_AFP:
        return 'AFP';
      case TIPO_DOCUMENTO_TITULO:
        return 'Título';
      case TIPO_DOCUMENTO_CERTIFICACIONES:
        return 'Certificaciones';
      case TIPO_DOCUMENTO_OTRO:
        return 'Otro';
      default:
        return 'Desconocido';
    }
  }
  public openModalDocumento(template: TemplateRef<any>) {
    this.nuevoDocumento = {
      tipo_documento: '',
      fecha_documento: new Date().toISOString().split('T')[0],
      fecha_vencimiento: null,
    };
    this.archivoSeleccionado = null;
    this.modalRef = this.modalService.show(template);
  }

  public onFileSelected(event: any) {
    const file = event.target.files[0];
    if (file) {
      if (file.size > 2 * 1024 * 1024) {
        // 2MB
        this.alertService.error('El archivo no puede ser mayor a 2MB');
        event.target.value = '';
        return;
      }
      this.archivoSeleccionado = file;
    }
  }

  public guardarDocumento() {
    if (!this.archivoSeleccionado || !this.nuevoDocumento.tipo_documento) {
      this.alertService.error('Por favor complete todos los campos requeridos');
      return;
    }

    this.saving = true;
    const formData = new FormData();
    formData.append('archivo', this.archivoSeleccionado);
    formData.append('tipo_documento', this.nuevoDocumento.tipo_documento);
    formData.append('fecha_documento', this.nuevoDocumento.fecha_documento);

    if (this.nuevoDocumento.fecha_vencimiento) {
      formData.append(
        'fecha_vencimiento',
        this.nuevoDocumento.fecha_vencimiento
      );
    }

    this.apiService
      .store(`empleados/${this.empleado.id}/documentos`, formData)
      .subscribe({
        next: (response) => {
          this.alertService.success('Exito','Documento guardado exitosamente');
          this.modalRef?.hide();
          // Recargar documentos
          this.loadDocumentos();
          this.saving = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
        },
      });
  }

  public loadDocumentos() {
    if (!this.empleado?.id) return;
  
    this.loading = true;
    this.apiService
      .getAll(`empleados/${this.empleado.id}/documentos`, this.filtrosDocumentos)
      .subscribe({
        next: (response) => {
          this.documentos = response;
          this.filtrarDocumentos();
          this.loading = false;
        },
        error: (error) => {
          this.alertService.error('Error al cargar los documentos');
          this.loading = false;
        },
      });
  }

  public setOrdenDocumentos(campo: string) {
    if (this.filtrosDocumentos.orden === campo) {
      this.filtrosDocumentos.direccion = this.filtrosDocumentos.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtrosDocumentos.orden = campo;
      this.filtrosDocumentos.direccion = 'asc';
    }
    
    this.loadDocumentos();
  }
  
  // Método para manejar la paginación
  public onPageChange(page: number) {
    this.filtrosDocumentos.pagina = page;
    this.loadDocumentos();
  }

  private ordenarDocumentos() {
    this.documentosFiltrados.sort((a, b) => {
      const valorA = a[this.filtrosDocumentos.orden];
      const valorB = b[this.filtrosDocumentos.orden];
      
      if (!valorA && !valorB) return 0;
      if (!valorA) return 1;
      if (!valorB) return -1;
      
      const comparacion = valorA > valorB ? 1 : -1;
      return this.filtrosDocumentos.direccion === 'asc' ? comparacion : -comparacion;
    });
  }

  public isDocumentoVencido(doc: any): boolean {
    if (!doc.fecha_vencimiento) {
      return false;
    }
    
    const fechaVencimiento = new Date(doc.fecha_vencimiento);
    const hoy = new Date();
    
    // Remover la hora para comparar solo fechas
    fechaVencimiento.setHours(0, 0, 0, 0);
    hoy.setHours(0, 0, 0, 0);
    
    return fechaVencimiento < hoy;
  }

  public filtrarDocumentos() {
    if (!this.documentos?.data) return;
    
    this.documentosFiltrados = [...this.documentos.data];
    
    if (this.filtrosDocumentos.tipo) {
      this.documentosFiltrados = this.documentosFiltrados.filter(
        doc => doc.tipo_documento === parseInt(this.filtrosDocumentos.tipo)
      );
    }
    
    this.ordenarDocumentos();
  }
  
  public getOrdenClass(campo: string): string {
    if (this.filtrosDocumentos.orden !== campo) {
      return 'fa-sort';
    }
    return this.filtrosDocumentos.direccion === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
  }

  public descargarDocumento(documento: any, isContract: boolean = false) {
    if (!documento) {
      this.alertService.error('No se proporcionó información del documento');
      return;
    }

    // Obtenemos el ID del documento según la estructura
    const documentoId = documento.id || documento;
    let ruta = isContract
      ? `empleados/contratos/${documentoId}/descargar`
      : `empleados/documentos/${documentoId}/descargar`;

    this.apiService.download(ruta).subscribe(
      (response: any) => {
        // Crear un blob con la respuesta
        const blob = new Blob([response], { type: response.type });

        // Crear URL temporal
        const url = window.URL.createObjectURL(blob);

        // Crear elemento a temporal para la descarga
        const link = document.createElement('a');
        link.href = url;
        link.download = documento.nombre_archivo || 'documento';

        // Simular click y limpiar
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      },
      (error) => {
        this.alertService.error('Error al descargar el documento');
        console.error('Error descargando documento:', error);
      }
    );
  }

  public getNombreTipoBaja(tipo: number): string {
    const tipos: { [key: number]: string } = {
      1: 'Renuncia',
      2: 'Despido',
      3: 'Terminación de contrato',
      4: 'Fallecimiento',
      5: 'Jubilación',
    };
    return tipos[tipo] || 'Desconocido';
  }

  public setActiveTab(tab: string) {
    this.activeTab = tab;

    if (tab === 'historiales' && this.empleado.id) {
      this.loadHistorialesContratos();
      this.loadHistorialesBajas();
    }

    if (tab === 'documentos' && this.empleado.id) {
      this.loadDocumentos();
    }
  }

  private inicializarEmpleado() {
    this.empleado = {
      estado: PlanillaConstants.ESTADOS_EMPLEADO.ACTIVO,
      tipo_contrato: PlanillaConstants.TIPOS_CONTRATO.PERMANENTE,
      tipo_jornada: PlanillaConstants.TIPOS_JORNADA.TIEMPO_COMPLETO,
      contacto_emergencia: {
        nombre: '',
        relacion: '',
        telefono: '',
        direccion: '',
      },
    };
  }

  public openModal(template: TemplateRef<any>) {
    this.departamento = {};
    this.cargo = {};
    this.modalRef = this.modalService.show(template);
  }

  public openModalCargo(template: TemplateRef<any>) {
    
    const departamentoId = this.empleado.id_departamento ? 
      (typeof this.empleado.id_departamento === 'string' ? 
        parseInt(this.empleado.id_departamento) : 
        this.empleado.id_departamento) : 
      null;

    this.cargo = {
      id_departamento: departamentoId,
      nombre: '',
      descripcion: '',
      salario_base: 0,
      activo: true,
    };

    this.modalRef = this.modalService.show(template);
  }

  public getNombreTipoContrato(id: number): string {
    return PlanillaConstants.getNombreTipoContrato(id);
  }

  public guardarDepartamento() {
    this.saving = true;
    this.departamento.activo = true;

    this.apiService.store('departamentosPlanilla', this.departamento).subscribe(
      (response: any) => {
        this.alertService.success(
          'Exito',
          'Departamento guardado exitosamente'
        );
        this.loadCatalogos(); 

        this.empleado.id_departamento = response.id;

        this.onDepartamentoChange(response.id);

        this.modalRef?.hide();
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  public guardarCargo() {
    this.saving = true;
    this.cargo.activo = true;
  
    this.apiService.store('cargos', this.cargo).subscribe(
      (response: any) => {
        // Guardar temporalmente los IDs
        const departamentoId = response.id_departamento;
        const nuevoCargoId = response.id;
        
        this.alertService.success('Éxito', 'Cargo guardado exitosamente');
        
        // Recargar catálogos
        this.loadCatalogos().then(() => {
          // Después de recargar los catálogos, actualizar la selección
          this.empleado.id_departamento = departamentoId;
          this.onDepartamentoChange(departamentoId);
          
          // Pequeña espera para asegurar que los cargos filtrados estén actualizados
          setTimeout(() => {
            this.empleado.id_cargo = nuevoCargoId;
            // Forzar detección de cambios si es necesario
            this.changeDetectorRef.detectChanges();
          }, 100);
        });
        
        this.modalRef?.hide();
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  public onDepartamentoChange(departamentoId: any) {
    if (departamentoId) {
      // Convertir a número si es necesario
      const depId =
        typeof departamentoId === 'string'
          ? parseInt(departamentoId)
          : departamentoId;

      this.cargosFiltrados = this.cargos.filter(
        (cargo: any) => cargo.id_departamento == depId
      );

      // Si estamos en modo edición y ya hay un cargo seleccionado,
      // verificar que esté en los cargos filtrados
      if (this.empleado.id_cargo) {
        const cargoExiste = this.cargosFiltrados.some(
          (cargo: any) => cargo.id == this.empleado.id_cargo
        );

        if (!cargoExiste) {
          this.empleado.id_cargo = ''; // Resetear si el cargo no pertenece al departamento
        }
      }
    } else {
      this.cargosFiltrados = [];
      this.empleado.id_cargo = '';
    }
  }

  

  public loadAll() {
    // Cargar catálogos primero
    this.loadCatalogos().then(() => {
      this.route.params.subscribe((params: any) => {
        if (params.id) {
          this.loading = true;
          this.apiService.read('empleados/', params.id).subscribe(
            (empleado) => {
              this.empleado = {
                ...empleado,
                id_departamento: empleado.departamento?.id,
                id_cargo: empleado.cargo?.id,
                contacto_emergencia: empleado.contacto_emergencia || {
                  nombre: '',
                  relacion: '',
                  telefono: '',
                  direccion: '',
                },
              };

              if (this.empleado.id_departamento) {
                this.onDepartamentoChange(this.empleado.id_departamento);
              }

              // Si estamos en la pestaña de historiales, cargar los datos
              if (this.activeTab === 'historiales') {
                this.loadHistorialesContratos();
                this.loadHistorialesBajas();
              }

              this.loading = false;
            },
            (error) => {
              this.alertService.error(error);
              this.loading = false;
            }
          );
        } else {
          // Modo creación - inicializar con valores por defecto
          this.empleado = {
            estado: PlanillaConstants.ESTADOS_EMPLEADO.ACTIVO,
            tipo_contrato: PlanillaConstants.TIPOS_CONTRATO.PERMANENTE,
            tipo_jornada: PlanillaConstants.TIPOS_JORNADA.TIEMPO_COMPLETO,
            id_empresa: this.id_empresa,
            id_sucursal: this.id_sucursal,
            contacto_emergencia: {
              nombre: '',
              relacion: '',
              telefono: '',
              direccion: '',
            },
          };
        }
      });
    });
  }

  public loadCatalogos(): Promise<void> {
    return new Promise((resolve) => {
      Promise.all([
        this.apiService.getAll('departamentosPlanilla/list').toPromise(),
        this.apiService.getAll('cargos/list').toPromise(),
      ])
        .then(([departamentos, cargos]) => {
          this.departamentos = departamentos;
          this.cargos = cargos;
          resolve();
        })
        .catch((error) => {
          this.alertService.error(error);
          resolve();
        });
    });
  }

  public loadHistorialesContratos() {
    if (!this.empleado?.id) {
      console.warn('No hay ID de empleado disponible');
      return;
    }

    this.loading = true;
    this.apiService
      .getAll(`empleados/${this.empleado.id}/historialesContratos`)
      .subscribe(
        (response) => {
          this.historialContratos = response;
          this.loading = false;
        },
        (error) => {
          this.alertService.error('Error al cargar el historial de contratos');
          this.loading = false;
        }
      );
  }

  public loadHistorialesBajas() {
    if (!this.empleado?.id) {
      console.warn('No hay ID de empleado disponible');
      return;
    }

    this.loading = true;
    this.apiService
      .getAll(`empleados/${this.empleado.id}/historialesBajas`)
      .subscribe(
        (response) => {
          this.historialBajas = response;
          this.loading = false;
        },
        (error) => {
          this.alertService.error('Error al cargar el historial de bajas');
          this.loading = false;
        }
      );
  }

  setPais() {
    this.empleado.pais = this.paises.find(
      (item: any) => item.cod == this.empleado.cod_pais
    ).nombre;
  }

  setDistrito() {
    let distrito = this.distritos.find(
      (item: any) =>
        item.cod == this.empleado.cod_distrito &&
        item.cod_departamento == this.empleado.cod_departamento
    );
    if (distrito) {
      this.empleado.cod_municipio = distrito.cod_municipio;
      this.setMunicipio();
      this.empleado.distrito = distrito.nombre;
      this.empleado.cod_distrito = distrito.cod;
    }
  }

  setMunicipio() {
    let municipio = this.municipios.find(
      (item: any) =>
        item.cod == this.empleado.cod_municipio &&
        item.cod_departamento == this.empleado.cod_departamento
    );
    if (municipio) {
      this.empleado.municipio = municipio.nombre;
      this.empleado.cod_municipio = municipio.cod;
      this.empleado.distrito = '';
      this.empleado.cod_distrito = '';
    }
  }

  setDepartamento() {
    let departamento = this.departamentosSV.find(
      (item: any) => item.cod == this.empleado.cod_departamento
    );
    if (departamento) {
      this.empleado.departamento = departamento.nombre;
      this.empleado.cod_departamento = departamento.cod;
    }
    this.empleado.municipio = '';
    this.empleado.cod_municipio = '';
    this.empleado.distrito = '';
    this.empleado.cod_distrito = '';
  }

  public onSubmit() {
    this.saving = true;

    // Asegurar que tenemos los IDs de empresa y sucursal
    this.empleado.id_empresa = this.id_empresa;
    this.empleado.id_sucursal = this.id_sucursal;

    this.apiService.store('empleados', this.empleado).subscribe(
      (response) => {
        const mensaje = this.empleado.id
          ? 'Empleado actualizado exitosamente'
          : 'Empleado creado exitosamente';

        this.alertService.success('Éxito', mensaje);
        this.router.navigate(['/planilla/empleados']);
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  public verificarSiExiste() {
    if (this.empleado.nombres && this.empleado.apellidos) {
      this.apiService
        .getAll('empleados', {
          nombres: this.empleado.nombres,
          apellidos: this.empleado.apellidos,
          estado: 'Activo',
        })
        .subscribe(
          (empleados) => {
            if (empleados.data[0]) {
              this.alertService.warning(
                '🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                'Por favor, verifica la información. Puedes ignorar esta alerta si consideras que no estas duplicando el registro.'
              );
            }
          },
          (error) => {
            this.alertService.error(error);
          }
        );
    }
  }

  public formatearDUI() {
    if (this.empleado.dui) {
      let dui = this.empleado.dui.replace(/\D/g, '');
      if (dui.length >= 9) {
        this.empleado.dui = dui.substr(0, 8) + '-' + dui.substr(8, 1);
      }
    }
  }

  public formatearNIT() {
    if (this.empleado.nit) {
      let nit = this.empleado.nit.replace(/\D/g, '');
      if (nit.length >= 14) {
        this.empleado.nit =
          nit.substr(0, 4) +
          '-' +
          nit.substr(4, 6) +
          '-' +
          nit.substr(10, 3) +
          '-' +
          nit.substr(13, 1);
      }
    }
  }

  public getNombreDepartamento(id: any): string {
    // Asegurar que ambos valores sean números para la comparación
    const departamentoId = typeof id === 'string' ? parseInt(id) : id;

    const departamento = this.departamentos.find(
      (d: any) => d.id === departamentoId
    );
    return departamento ? departamento.nombre : '';
  }

  eliminarDocumento(documento: any) {
    const index = this.documentos.data.findIndex((doc: any) => doc.id === documento.id);
    if (index !== -1) {
      this.documentos.data.splice(index, 1);
      this.documentosFiltrados = [...this.documentos.data];
    }
  }

  public verHistorial(empleado: any) {
    this.router.navigate(['/planilla/empleado/editar', empleado.id]).then(() => {
      this.setActiveTab('historiales');
    });
  }
}