import { Component, OnInit, OnDestroy, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-importar-excel',
  templateUrl: './importar-excel.component.html'
})
export class ImportarExcelComponent implements OnInit, OnDestroy {

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

    private readonly destroy$ = new Subject<void>();

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

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    /**
     * Resuelve variante de plantilla según país de la empresa.
     * sv: El Salvador (-format.xlsx)
     * cr: Costa Rica (-format-cr.xlsx)
     * hn: Honduras (-format-hn.xlsx)
     * general: resto (-format-general.xlsx para clientes; -format.xlsx para proveedores)
     */
    private plantillaPorPais(empresa: { cod_pais?: string | null; pais?: string | null }): 'sv' | 'cr' | 'hn' | 'general' {
        const codPais = empresa?.cod_pais;
        const pais = (empresa?.pais ?? '').trim();
        const paisLower = pais.toLowerCase();

        if (codPais === 'SV') {
            return 'sv';
        }
        if (codPais === 'CR') {
            return 'cr';
        }
        if (codPais === 'HN') {
            return 'hn';
        }
        if (codPais && codPais !== 'SV') {
            // Código conocido distinto de SV; si el nombre dice Honduras/Costa Rica aún afinar abajo
            if (paisLower === 'honduras') {
                return 'hn';
            }
            if (paisLower === 'costa rica') {
                return 'cr';
            }
            return 'general';
        }
        if (paisLower === 'el salvador') {
            return 'sv';
        }
        if (paisLower === 'costa rica') {
            return 'cr';
        }
        if (paisLower === 'honduras') {
            return 'hn';
        }
        if (!pais) {
            return 'sv';
        }
        return 'general';
    }

    private sufijoPlantilla(variante: 'sv' | 'cr' | 'hn' | 'general', tipo: 'clientes' | 'proveedores'): string {
        if (variante === 'sv') {
            return '-format.xlsx';
        }
        if (variante === 'cr') {
            return '-format-cr.xlsx';
        }
        if (variante === 'hn') {
            return '-format-hn.xlsx';
        }
        // general
        return tipo === 'clientes' ? '-format-general.xlsx' : '-format.xlsx';
    }

    /**
     * Calcula la URL de la plantilla según el tipo y el país de la empresa.
     * Clientes/proveedores: SV / CR / HN / general.
     * Retrocompatibilidad: sin país → El Salvador.
     */
    calcularPlantillaUrl(): void {
        const nombreArchivo = this.nombre.toLowerCase();

        if (nombreArchivo === 'productos') {
            this.plantillaUrl = '';
            return;
        }

        // Manejo especial para ventas
        if (nombreArchivo === 'ventas') {
            // Las ventas tienen múltiples plantillas, se manejan en el HTML
            this.plantillaUrl = '';
            return;
        }

        const esClientes = nombreArchivo === 'clientes-personas' || nombreArchivo === 'clientes-empresas';
        const esProveedores = nombreArchivo === 'proveedores-personas' || nombreArchivo === 'proveedores-empresas';

        if (esClientes || esProveedores) {
            try {
                const user = this.apiService.auth_user();
                const empresa = user?.empresa;

                if (!empresa) {
                    this.plantillaUrl = `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
                    return;
                }

                const variante = this.plantillaPorPais(empresa);
                // Proveedores: solo HN tiene plantilla dedicada por ahora (CR xlsx existe pero el import aún es SV).
                if (esProveedores) {
                    const sufijoProv = variante === 'hn' ? '-format-hn.xlsx' : '-format.xlsx';
                    this.plantillaUrl = `${this.apiService.baseUrl}/docs/${nombreArchivo}${sufijoProv}`;
                    return;
                }

                const sufijo = this.sufijoPlantilla(variante, 'clientes');
                this.plantillaUrl = `${this.apiService.baseUrl}/docs/${nombreArchivo}${sufijo}`;
            } catch (error) {
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

    public descargarPlantillaImportacionProductos(event: Event): void {
        event.preventDefault();
        this.apiService.download('productos/plantilla-importacion')
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (blob) => {
                    this.apiService.downloadFile(blob, 'plantilla_importacion_productos.xlsx');
                },
                error: () => {
                    this.alertService.error('Error al descargar la plantilla de productos');
                },
            });
    }

    public downloadTemplate() {
        const url = `${this.nombre.toLowerCase()}/plantilla`;
        this.apiService.download(url)
          .pipe(takeUntil(this.destroy$))
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
