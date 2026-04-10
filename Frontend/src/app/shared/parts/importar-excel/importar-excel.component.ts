import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

@Component({
  selector: 'app-importar-excel',
  templateUrl: './importar-excel.component.html',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    TooltipModule,
    NotificacionesContainerComponent,
    ImportarExcelComponent,
  ],
})
export class ImportarExcelComponent implements OnInit {

    @Input() tipo:string = 'button';
    @Input() nombre:string = '';
    @Output() loadAll = new EventEmitter();
    public loading:boolean = false;
    public file:any = {};
    public plantillaUrl: string = '';
    public importResult: any = null;
    public showResults: boolean = false;
    public validationErrors: string[] = [];
    public businessErrors: string[] = [];

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    /** URL de la plantilla con parámetro de versión para evitar caché del navegador */
    get plantillaUrlConCache(): string {
        return this.plantillaUrl ? `${this.plantillaUrl}?v=${Date.now()}` : '';
    }

    modalRef!: BsModalRef;

    constructor(
        private apiService: ApiService, public alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.calcularPlantillaUrl();
    }

    /**
     * Calcula la URL de la plantilla según el tipo y el país de la empresa
     * Para clientes-personas y clientes-empresas, usa plantillas generales si no es El Salvador
     * Retrocompatibilidad: Si no se puede determinar el país, usa plantilla de El Salvador
     */
    calcularPlantillaUrl(): void {
        const nombreArchivo = this.nombre.toLowerCase();

        // Manejo especial para ventas
        if (nombreArchivo === 'ventas') {
            // Las ventas tienen múltiples plantillas, se manejan en el HTML
            this.plantillaUrl = '';
            return;
        }

        // Para clientes-personas y clientes-empresas, verificar país
        if (nombreArchivo === 'clientes-personas' || nombreArchivo === 'clientes-empresas') {
            try {
                const user = this.apiService.auth_user();
                const empresa = user?.empresa;

                // Si no hay empresa, usar plantilla de El Salvador (retrocompatibilidad)
                if (!empresa) {
                    this.plantillaUrl = `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
                    return;
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
                this.plantillaUrl = `${this.apiService.baseUrl}/docs/${nombreArchivo}${sufijo}`;
            } catch (error) {
                // En caso de error, usar plantilla de El Salvador (retrocompatibilidad)
                this.plantillaUrl = `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
            }
        } else {
            // Para otros tipos, usar formato estándar
            this.plantillaUrl = `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
        }
    }

    openModal(template: TemplateRef<any>) {
        // Recalcular la URL de la plantilla cuando se abre el modal
        // para asegurarnos de que tenemos los datos más recientes de la empresa
        this.calcularPlantillaUrl();
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    setFile(event:any){
        this.file.file = event.target.files[0];
    }

    // onSubmit(event:any) {

    //     console.log(this.file);

    //     let formData:FormData = new FormData();
    //     for (var key in this.file) {
    //         formData.append(key, this.file[key]);
    //     }

    //     console.log(formData);
    //     this.loading = true;
    //     this.apiService.store(this.nombre.toLowerCase() + '/importar', formData).subscribe(data => {
    //         this.loading = false;
    //         this.alertService.success('Importación exitosa', data + ' ' + this.nombre.replace('-', ' ') + ' agregados');
    //         setTimeout(()=>{
    //             this.modalRef.hide();
    //             this.loadAll.emit();
    //             this.alertService.modal = false;
    //         }, 2000);
    //     }, error => {this.alertService.error(error); this.loading = false;});
    // }

    onSubmit(event:any) {
        console.log(this.file);

        let formData:FormData = new FormData();
        for (var key in this.file) {
            formData.append(key, this.file[key]);
        }

        console.log(formData);
        this.loading = true;

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
                            // Cerrar el modal primero para mostrar la alerta fuera
                            this.modalRef.hide();
                            this.alertService.modal = false;

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
                        this.modalRef.hide();
                        this.loadAll.emit();
                        this.alertService.modal = false;
                    }, 1000);
                } else {
                    // Para clientes con mensaje detallado, solo recargar datos
                    setTimeout(() => {
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
        this.modalRef?.hide();
        this.alertService.modal = false;
        this.resetState();
    }

    public downloadTemplate() {
        const url = `${this.nombre.toLowerCase()}/plantilla`;
        this.apiService.download(url)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (response) => {
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
            error: () => {
                this.alertService.error('Error al descargar la plantilla');
            }
          });
    }

    public tryAgain() {
        this.resetState();
        this.showResults = false;
    }
}
