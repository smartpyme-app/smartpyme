import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

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
    imports: [CommonModule, FormsModule, NotificacionesContainerComponent]
})
export class ImportarExcelComponent implements OnInit {

    @Input() tipo: string = 'button';
    @Input() nombre: string = '';
    @Output() loadAll = new EventEmitter();

    public loading: boolean = false;
    public file: any = {};
    public importResult: any = null;
    public showResults: boolean = false;
    public validationErrors: ValidationError[] = [];
    public businessErrors: string[] = [];

    modalRef!: BsModalRef;

    constructor(
        private apiService: ApiService,
        public alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.resetState();
    }

    openModal(template: TemplateRef<any>) {
        this.resetState();
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
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

        this.apiService.store(this.nombre.toLowerCase() + '/importar', formData).subscribe(
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
                                this.modalRef.hide();
                                this.alertService.modal = false;
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
                            this.modalRef.hide();
                            this.alertService.modal = false;
                        }
                        this.loadAll.emit();
                    }, 1000);
                } else {
                    // Para clientes con mensaje detallado, solo recargar datos
                    setTimeout(() => {
                        // Solo cerrar modal si existe (modo button/text)
                        if (this.modalRef) {
                            this.modalRef.hide();
                            this.alertService.modal = false;
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

    public closeModal() {
        this.modalRef.hide();
        this.alertService.modal = false;
        this.resetState();
    }

    public downloadTemplate() {
        const url = `${this.nombre.toLowerCase()}/plantilla`;
        this.apiService.download(url).subscribe(
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
