import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-importar-excel',
  templateUrl: './importar-excel.component.html'
})
export class ImportarExcelComponent implements OnInit {

    @Input() tipo:string = 'button';
    @Input() nombre:string = '';
    @Output() loadAll = new EventEmitter();
    public loading:boolean = false;
    public file:any = {};

    modalRef!: BsModalRef;

    constructor( 
        private apiService: ApiService, public alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
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

    openModal(template: TemplateRef<any>) {
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


}
