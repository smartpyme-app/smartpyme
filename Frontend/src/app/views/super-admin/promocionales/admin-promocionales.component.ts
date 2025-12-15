import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { EncryptService } from '@services/encryption/encrypt.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-admin-promocionales',
  templateUrl: './admin-promocionales.component.html',
})
export class AdminPromocionalesComponent implements OnInit {
  public promocionales: any = [];
  public promocional: any = {};
  public usuario: any = {};
  public filtros: any = {
    paginate: 25,
    orden: 'codigo',
    direccion: 'asc',
    buscador: '',
    activo: '',
  };
  public loading: boolean = false;
  public saving: boolean = false;
  public editando: boolean = false;
  public downloading: boolean = false;

  public planesDisponibles = ['Mensual', 'Trimestral', 'Anual'];

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private encryptService: EncryptService
  ) {}

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
    this.loadAll();
  }

  public loadAll() {
    this.loading = true;
    this.filtrarPromocionales();
  }

  public filtrarPromocionales() {
    this.loading = true;
    
    // Limpiar filtros vacíos antes de enviar
    const filtrosLimpios: any = { ...this.filtros };
    if (filtrosLimpios.activo === '') {
      delete filtrosLimpios.activo;
    }
    if (filtrosLimpios.buscador === '') {
      delete filtrosLimpios.buscador;
    }
    
    this.apiService.getAll('promocionales/list', filtrosLimpios).subscribe(
      (data) => {
        this.promocionales = data;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public setPagination(event: any) {
    this.filtros.page = event.page;
    this.filtrarPromocionales();
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.filtrarPromocionales();
  }

  public openCrear(template: TemplateRef<any>) {
    this.editando = false;
    this.promocional = {
      codigo: '',
      descuento: 0,
      tipo: 'porcentaje',
      activo: true,
      campania: '',
      descripcion: '',
      planes_permitidos: [],
      opciones: {
        uso_maximo: null,
        uso_por_usuario: 1,
        fecha_inicio: '',
        fecha_expiracion: '',
        monto_minimo: null,
        monto_maximo: null,
        combinable: false,
      },
    };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  public generarCodigoAutomatico() {
    // Intentar usar el prefijo de la campaña si existe
    let prefix = '';
    
    if (this.promocional.campania && this.promocional.campania.trim() !== '') {
      // Limpiar y usar la campaña como prefijo (máximo 10 caracteres)
      prefix = this.promocional.campania.trim().toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
    }
    
    // Si no hay prefijo de campaña, intentar usar el código actual como base
    if (!prefix && this.promocional.codigo && this.promocional.codigo.trim() !== '') {
      const codigoActual = this.promocional.codigo.trim().toUpperCase();
      // Extraer el prefijo del código actual (parte antes de números aleatorios)
      const match = codigoActual.match(/^([A-Z]+)/);
      if (match && match[1].length > 0) {
        prefix = match[1].substring(0, 10);
      }
    }
    
    // Si aún no hay prefijo, usar 'SMARTPYME' por defecto
    if (!prefix) {
      prefix = 'SMARTPYME';
    }
    
    // Generar código promocional dinámico con el prefijo
    const codigoGenerado = this.encryptService.generatePromoCode(prefix, 6);
    this.promocional.codigo = codigoGenerado;
  }

  public openEditar(template: TemplateRef<any>, promocional: any) {
    this.editando = true;
    this.promocional = {
      id: promocional.id,
      codigo: promocional.codigo,
      descuento: promocional.descuento,
      tipo: promocional.tipo,
      activo: promocional.activo,
      campania: promocional.campania || '',
      descripcion: promocional.descripcion || '',
      planes_permitidos: promocional.planes_permitidos || [],
      opciones: promocional.opciones || {
        uso_maximo: null,
        uso_por_usuario: 1,
        fecha_inicio: '',
        fecha_expiracion: '',
        monto_minimo: null,
        monto_maximo: null,
        combinable: false,
      },
    };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  public onSubmit() {
    if (!this.promocional.codigo || !this.promocional.descuento) {
      this.alertService.error('Por favor complete todos los campos requeridos');
      return;
    }

    this.saving = true;

    const data = {
      ...this.promocional,
      codigo: this.promocional.codigo.toUpperCase().trim(),
    };

    if (this.editando) {
      const updateData = { ...data, id: this.promocional.id };
      this.apiService
        .store('promocional/edit', updateData)
        .subscribe(
        (response) => {
          this.alertService.success('Éxito', 'Código promocional actualizado correctamente');
          this.modalRef.hide();
          this.loadAll();
          this.saving = false;
        },
          (error) => {
            this.alertService.error(error);
            this.saving = false;
          }
        );
    } else {
      this.apiService.store('promocional/create', data).subscribe(
        (response) => {
          this.alertService.success('Éxito', 'Código promocional creado correctamente');
          this.modalRef.hide();
          this.loadAll();
          this.saving = false;
        },
        (error) => {
          this.alertService.error(error);
          this.saving = false;
        }
      );
    }
  }

  public eliminar(promocional: any) {
    Swal.fire({
      title: '¿Está seguro?',
      text: `¿Desea eliminar el código promocional "${promocional.codigo}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.delete('promocional/', promocional.id).subscribe(
          (response) => {
            this.alertService.success('Éxito', 'Código promocional eliminado correctamente');
            this.loadAll();
          },
          (error) => {
            this.alertService.error(error);
          }
        );
      }
    });
  }

  public toggleActivo(promocional: any) {
    const nuevoEstado = !promocional.activo;
    this.apiService
      .store('promocional/edit', {
        ...promocional,
        id: promocional.id,
        activo: nuevoEstado,
      })
      .subscribe(
        (response) => {
          this.alertService.success(
            'Éxito',
            `Código promocional ${nuevoEstado ? 'activado' : 'desactivado'} correctamente`
          );
          this.loadAll();
        },
        (error) => {
          this.alertService.error(error);
        }
      );
  }

  public togglePlanPermitido(plan: string) {
    if (!this.promocional.planes_permitidos) {
      this.promocional.planes_permitidos = [];
    }

    const index = this.promocional.planes_permitidos.indexOf(plan);
    if (index > -1) {
      this.promocional.planes_permitidos.splice(index, 1);
    } else {
      this.promocional.planes_permitidos.push(plan);
    }
  }

  public isPlanPermitido(plan: string): boolean {
    return (
      this.promocional.planes_permitidos &&
      this.promocional.planes_permitidos.includes(plan)
    );
  }

  public formatearFecha(fecha: string | null): string {
    if (!fecha) return '';
    const date = new Date(fecha);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  public descargar() {
    this.downloading = true;
    // Implementar exportación si es necesaria
    this.downloading = false;
  }
}

