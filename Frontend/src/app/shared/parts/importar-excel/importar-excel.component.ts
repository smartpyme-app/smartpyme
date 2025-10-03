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
