import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-custom-fields',
  templateUrl: './custom-fields.component.html',
})
export class CustomFieldsComponent implements OnInit {
    public customFields:any = [];
    @ViewChild('modalValues') modalValues!: TemplateRef<any>;

    public field:any = {
        name: '',
        field_type: 'select',
        is_required: false,
        values: []
    };
    public filtros:any = {
        buscador: '',
        paginate: 10,
        orden: '',
        direccion: 'asc'
    };
    public loading:boolean = false;
    public newValue:string = '';  // Para el nuevo valor a agregar
    modalRef?: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.loadAll();
    }

    loadAll() {
        this.loading = true;
        this.apiService.getAll('custom-fields', this.filtros).subscribe(customFields => {
            this.customFields = customFields;
            this.loading = false;
        }, error => {
            this.alertService.error(error);
            this.loading = false;
        });
    }

    filtrarCampos() {
        this.loadAll();
    }

    setOrden(orden:string) {
        if (this.filtros.orden === orden) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = orden;
            this.filtros.direccion = 'asc';
        }
        this.filtrarCampos();
    }

    public openModal(template: TemplateRef<any>, field: any = {}) {
        if (!field.id) {
            this.field = {
                name: '',
                field_type: 'select',
                is_required: false,
                values: []
            };
        } else {
            this.field = {...field};
            if (!this.field.values) {
                this.field.values = [];
            }
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }

    addValue(value: string) {
        if (!value?.trim()) return;
        
        if (!this.field.values) {
            this.field.values = [];
        }

        this.field.values.push({
            value: value.trim(),
            id: Date.now()  // Temporal ID para nuevos valores
        });

        this.newValue = '';  // Limpiar el input
    }

    removeValue(valueId: number) {
        if (!this.field.values) return;
        
        this.field.values = this.field.values.filter((v: any) => v.id !== valueId);
    }

    onSubmit() {
        if (this.field.field_type === 'select' && (!this.field.values?.length)) {
            this.alertService.error('Debe agregar al menos un valor para campos tipo lista');
            return;
        }
    
        const dataToSend = {
            name: this.field.name,
            field_type: this.field.field_type,
            is_required: this.field.is_required,
            values: this.field.values.map((v: any) => ({
                value: v.value
            }))
        };
    
        this.loading = true;
        const request = this.field.id ? 
            this.apiService.update('custom-fields', this.field.id, dataToSend) :
            this.apiService.store('custom-fields', dataToSend);
    
        request.subscribe({
            next: () => {
                this.alertService.success(
                    this.field.id ? 'Campo actualizado' : 'Campo creado',
                    this.field.id ? 'Campo actualizado exitosamente' : 'Campo creado exitosamente'
                );
                this.loadAll();
                this.modalRef?.hide();
            },
            error: (error) => this.alertService.error(error),
            complete: () => this.loading = false
        });
    }

    toggleRequired() {
        this.field.is_required = !this.field.is_required;
    }

    openModalValues(field: any) {
        this.field = {...field};
        
        // Cargar los valores actuales del campo
        this.apiService.getAll(`custom-fields/${field.id}/values`).subscribe(
            (values) => {
                this.field.values = values;
                this.modalRef = this.modalService.show(this.modalValues, {
                    class: 'modal-lg',
                    backdrop: 'static'
                });
            },
            error => {
                this.alertService.error(error);
            }
        );
    }

    setPagination(event: any) {
        this.filtros.page = event.page;
        this.loadAll();
    }

    //onSubmit

    
    
    
    
    
}