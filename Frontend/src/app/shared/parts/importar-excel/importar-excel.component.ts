import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

interface ImportResponse {
    success: boolean;
    message: string;
    filas_procesadas?: number;
    empresa_id?: number;
    error?: string;
    errores?: any[];
    errores_adicionales?: string[];
}

interface ValidationError {
    fila: number;
    columna: string;
    errores: string[];
    valores: any;
}

@Component({
    selector: 'app-importar-excel',
    templateUrl: './importar-excel.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, NotificacionesContainerComponent, LazyImageDirective]
})
export class ImportarExcelComponent extends BaseModalComponent implements OnInit {

    @Input() tipo: string = 'button';
    @Input() nombre: string = '';
    @Output() loadAll = new EventEmitter();

    public override loading: boolean = false;
    public file: any = {};
    public importResult: any = null;
    public showResults: boolean = false;
    public validationErrors: ValidationError[] = [];
    public businessErrors: string[] = [];

    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.resetState();
    }

  /**
   * Obtiene la URL de la plantilla según el tipo y el país de la empresa
   * Para clientes-personas y clientes-empresas, usa plantillas generales si no es El Salvador
   * Retrocompatibilidad: Si no se puede determinar el país, usa plantilla de El Salvador
   */
  get plantillaUrl(): string {
    const nombreArchivo = this.nombre.toLowerCase();

    // Manejo especial para ventas
    if (nombreArchivo === 'ventas') {
      // Las ventas tienen múltiples plantillas, se manejan en el HTML
      return '';
    }

    // Para clientes-personas y clientes-empresas, verificar país
    if (nombreArchivo === 'clientes-personas' || nombreArchivo === 'clientes-empresas') {
      try {
        const user = this.apiService.auth_user();
        const empresa = user?.empresa;

        // Si no hay empresa, usar plantilla de El Salvador (retrocompatibilidad)
        if (!empresa) {
          return `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
        }

        // Verificar si es El Salvador
        const codPais = empresa?.cod_pais;
        const pais = empresa?.pais?.trim() || '';

        let esElSalvador = false;

        // Si tiene código 'SV', es El Salvador
        if (codPais === 'SV') {
          esElSalvador = true;
        }
        // Si cod_pais es diferente a 'SV' y no es null/undefined, no es El Salvador
        else if (codPais && codPais !== 'SV') {
          esElSalvador = false;
        }
        // Si cod_pais es null/undefined, verificar campo pais
        else {
          if (pais.toLowerCase() === 'el salvador') {
            esElSalvador = true;
          }
          // Si pais está vacío, asumir El Salvador (retrocompatibilidad)
          else if (!pais) {
            esElSalvador = true;
          }
          // Si tiene otro valor, no es El Salvador
          else {
            esElSalvador = false;
          }
        }

        // Si es El Salvador, usar plantilla específica, sino usar general
        const sufijo = esElSalvador ? '-format.xlsx' : '-format-general.xlsx';
        return `${this.apiService.baseUrl}/docs/${nombreArchivo}${sufijo}`;
      } catch (error) {
        // En caso de error, usar plantilla de El Salvador (retrocompatibilidad)
        console.warn('Error al determinar país de empresa, usando plantilla de El Salvador:', error);
        return `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
      }
    }

    // Para otros tipos, usar formato estándar
    return `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
  }

    override openModal(template: TemplateRef<any>) {
        this.resetState();
        super.openModal(template, { class: 'modal-md' });
    }

    setFile(event: any) {
        this.file.file = event.target.files[0];
        this.resetState();
    }

    onSubmit(event: any) {
        if (!this.file.file) {
            this.alertService.error('Por favor seleccione un archivo');
            return;
        }

        console.log('Iniciando importación:', this.file);

        let formData: FormData = new FormData();
        for (var key in this.file) {
            formData.append(key, this.file[key]);
        }

        this.loading = true;
        this.resetState();

        this.apiService.store(this.nombre.toLowerCase() + '/importar', formData)
          .pipe(this.untilDestroyed())
          .subscribe(
          (data: any) => {
            this.loading = false;

          if (this.nombre.toLowerCase() === 'ventas') {

                    if (data && typeof data === 'object' && data.message) {
                        this.alertService.success('Importación de ventas', data.message);


                        if (data.productos_faltantes && data.productos_faltantes.length > 0) {
                            setTimeout(() => {
                                this.alertService.info(
                                    'Productos no encontrados',
                                    'Estos productos deben ser creados en el sistema: ' +
                                    data.productos_faltantes.join(", ")
                                );
                            }, 4000);
                        }
                    } else if (typeof data === 'number') {

                        this.alertService.success('Importación exitosa', data + ' ventas procesadas correctamente');
                    } else {

                        this.alertService.success('Importación exitosa', 'Las ventas fueron procesadas correctamente');
                    }
                } else {
                    // Manejo específico para importación de clientes
                    if (this.nombre.toLowerCase().includes('clientes')) {
                        if (data && typeof data === 'object' && data.message) {
                            // Cerrar el modal primero para mostrar la alerta fuera (solo si existe)
                            if (this.modalRef) {
                                this.closeModal();
                            }

                            // Mostrar mensaje con detalles de procesados y fallidos
                            let mensaje = data.message;
                            if (data.procesados !== undefined && data.fallidos !== undefined) {
                                mensaje += `\n\n📊 Resumen: ${data.procesados} procesados, ${data.fallidos} fallidos`;
                            }

                            // Mostrar alerta después de cerrar el modal
                            setTimeout(() => {
                                this.alertService.success('Importación de clientes', mensaje);
                            }, 300);

                        } else if (typeof data === 'number') {
                            this.alertService.success('Importación exitosa', data + ' ' + this.nombre.replace('-', ' ') + ' agregados');
                        } else {
                            this.alertService.success('Importación exitosa', 'Los clientes fueron procesados correctamente');
                        }
                    } else {
                        // Para otros tipos de importación
                        this.alertService.success('Importación exitosa', data + ' ' + this.nombre.replace('-', ' ') + ' agregados');
                    }
                }

                // Solo cerrar modal y recargar si no es importación de clientes con mensaje detallado
                if (!(this.nombre.toLowerCase().includes('clientes') && data && typeof data === 'object' && data.message)) {
                    setTimeout(() => {
                        // Solo cerrar modal si existe (modo button/text)
                        if (this.modalRef) {
                            this.closeModal();
                        }
                        this.loadAll.emit();
                    }, 1000);
                } else {
                    // Para clientes con mensaje detallado, solo recargar datos
                    setTimeout(() => {
                        // Solo cerrar modal si existe (modo button/text)
                        if (this.modalRef) {
                            this.closeModal();
                        }
                        this.loadAll.emit();
                    }, 500);
                }
            },
            error => {
                this.loading = false;


                if (this.nombre.toLowerCase() === 'ventas' && error.error && error.error.error) {
                    this.alertService.error(error.error.error);
                } else {
                    this.alertService.error(error);
                }


                this.alertService.modal = true;
            }
        );
    }

  private resetState() {
    this.importResult = null;
    this.showResults = false;
    this.validationErrors = [];
    this.businessErrors = [];
  }

    public override closeModal() {
        super.closeModal();
        this.resetState();
    }

    public downloadTemplate() {
        const url = `${this.nombre.toLowerCase()}/plantilla`;
        this.apiService.download(url)
          .pipe(this.untilDestroyed())
          .subscribe(
            (response: Blob) => {
                const blob = new Blob([response], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                });
                const urlDownload = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = urlDownload;
                link.download = `plantilla_${this.nombre.toLowerCase()}.xlsx`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(urlDownload);
            },
            (error) => {
                this.alertService.error('Error al descargar la plantilla');
            }
        );
    }

    public tryAgain() {
        this.resetState();
        this.showResults = false;
    }
}
