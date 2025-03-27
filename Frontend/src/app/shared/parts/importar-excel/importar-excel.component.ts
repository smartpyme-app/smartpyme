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
                
                // Manejo especial para importación de ventas
                if (this.nombre.toLowerCase() === 'ventas') {
                    // Verificar si la respuesta es un objeto con propiedades específicas para ventas
                    if (data && typeof data === 'object' && data.message) {
                        this.alertService.success('Importación de ventas', data.message);
                        
                        // Mostrar detalles adicionales sobre productos no encontrados
                        if (data.productos_faltantes && data.productos_faltantes.length > 0) {
                            setTimeout(() => {
                                this.alertService.info(
                                    'Productos no encontrados', 
                                    'Estos productos deben ser creados en el sistema: ' + 
                                    data.productos_faltantes.join(", ")
                                );
                            }, 1000);
                        }
                    } else if (typeof data === 'number') {
                        // Si la respuesta es solo un número (cantidad de ventas procesadas)
                        this.alertService.success('Importación exitosa', data + ' ventas procesadas correctamente');
                    } else {
                        // Manejar cualquier otro tipo de respuesta
                        this.alertService.success('Importación exitosa', 'Las ventas fueron procesadas correctamente');
                    }
                } else {
                    // Para otros tipos de importaciones, mantener el comportamiento original
                    this.alertService.success('Importación exitosa', data + ' ' + this.nombre.replace('-', ' ') + ' agregados');
                }
                
                setTimeout(() => {
                    this.modalRef.hide();
                    this.loadAll.emit();
                    this.alertService.modal = false;
                }, 2000);
            }, 
            error => {
                this.loading = false;
                
                // Manejo especial de errores para ventas
                if (this.nombre.toLowerCase() === 'ventas' && error.error && error.error.error) {
                    this.alertService.error(error.error.error);
                } else {
                    this.alertService.error(error);
                }
            }
        );
    }


}
