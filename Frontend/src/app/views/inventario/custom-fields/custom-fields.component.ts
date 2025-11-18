import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import Swal from 'sweetalert2';

interface CustomFieldValue {
    id?: number;
    temp_id?: number;
    value: string;
    in_use?: boolean;
    is_active?: boolean;
}

interface CustomField {
    id?: number;
    name: string;
    field_type: 'select' | 'text' | 'number';
    is_required: boolean;
    values: CustomFieldValue[];
    in_use?: boolean;
}

@Component({
    selector: 'app-custom-fields',
    templateUrl: './custom-fields.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent, PopoverModule, TooltipModule],

})
export class CustomFieldsComponent extends BaseModalComponent implements OnInit {
    public customFields: any = {
        data: [],
        total: 0
    };

    public originalField: CustomField = {
        name: '',
        field_type: 'select',
        is_required: true,
        values: []
    };

    public field: CustomField = {
        name: '',
        field_type: 'select',
        is_required: true,
        values: []
    };

    public filtros = {
        buscador: '',
        paginate: 10,
        orden: '',
        direccion: 'asc',
        page: 1
    };

    public newValue = '';

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loadAll();
    }

    loadAll() {
        this.loading = true;
        this.apiService.getAll('custom-fields', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (response) => {
                this.customFields = response;
                this.loading = false;
                this.customFields.data.forEach((field: any) => {
                    if (field.field_type === 'select') {
                        field.in_use = field.values.some((value: any) => value.product_custom_fields.length > 0);
                    } else {
                        field.in_use = field.product_custom_fields.length > 0;
                    }
                });

            },
            error: (error) => {
                this.alertService.error('Error');
                this.loading = false;
            }
        });
    }

    filtrarCampos() {
        this.filtros.page = 1;
        this.loadAll();
    }

    setOrden(orden: string) {
        if (this.filtros.orden === orden) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = orden;
            this.filtros.direccion = 'asc';
        }
        this.filtrarCampos();
    }

    override async openModal(template: TemplateRef<any>, field: Partial<CustomField> = {}) {
        if (!field.id) {
            // Nuevo campo
            this.field = {
                name: '',
                field_type: 'select',
                is_required: true,
                values: [],
                in_use: false
            };
        } else {
            try {
                // Obtener el campo con sus valores y usos
                const fieldData = await this.apiService.getAll(`custom-fields/${field.id}/usage`)
                  .pipe(this.untilDestroyed())
                  .toPromise();
                
                const values = fieldData.values.map((value: any) => ({
                    id: value.id,
                    value: value.value,
                    custom_field_id: value.custom_field_id,
                    in_use: value.product_custom_fields.length > 0
                }));
    
         
                this.field = {
                    id: fieldData.id,
                    name: fieldData.name,
                    field_type: fieldData.field_type,
                    is_required: fieldData.is_required,
                    values: values,
           
                    in_use: values.some((v: any) => v.in_use)
                };

                this.originalField = { ...this.field };
            } catch (error) {
                this.alertService.error('No se pudo cargar la información del campo');
                return;
            }
        }
    
        super.openLargeModal(template);
    }

    addValue(value: string) {
        if (!value?.trim()) return;

        // Verificar duplicados
        if (this.field.values.some(v => v.value.toLowerCase() === value.trim().toLowerCase())) {
            this.alertService.warning('Valor duplicado', 'Este valor ya existe');
            return;
        }

        this.field.values.push({
            temp_id: Date.now(),
            value: value.trim(),
            in_use: false,
            is_active: true
        });

        this.newValue = '';
    }

    removeValue(valueId: number) {
        const value = this.field.values.find(v => (v.id || v.temp_id) === valueId);
        
        if (value?.in_use) {
            this.alertService.warning(
                'Valor en uso',
                'No se puede eliminar este valor porque está siendo usado en cotizaciones existentes'
            );
            return;
        }

        this.field.values = this.field.values.filter(v => (v.id || v.temp_id) !== valueId);
    }

    async deleteField(field: CustomField) {
        if (field.in_use) {
            this.alertService.warning(
                'Campo en uso',
                'No se puede eliminar este campo porque está siendo usado en cotizaciones existentes'
            );
            return;
        }

       Swal.fire({
        title: '¿Está seguro de eliminar este campo?',
        text: 'No se podrá recuperar una vez eliminado',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            this.loading = true;
        try {
            if (field.id) {
                 this.apiService.delete('custom-fields/',field.id)
                   .pipe(this.untilDestroyed())
                   .subscribe({
                    next: () => {
                        this.alertService.success('Éxito', 'Campo eliminado exitosamente');
                        this.loadAll();
                    },
                    error: (error) => this.alertService.error(error)
                });
            }
        } catch (error) {
            this.alertService.error( error);
        } finally {
            this.loading = false;
            }
        }
    });
    }

    onSubmit() {
        if (this.field.field_type === 'select' && !this.field.values?.length) {
            this.alertService.error( 'Debe agregar al menos un valor para campos tipo lista');
            return;
        }

   
        if (this.field.in_use) {
            if (this.field.field_type !== this.originalField.field_type) {
                this.alertService.error('No se puede cambiar el tipo de un campo que está en uso');
                return;
            }

        
            const hasDeletedUsedValues = this.originalField.values
                .filter(v => v.in_use)
                .some(v => !this.field.values.find(nv => nv.id === v.id));

            if (hasDeletedUsedValues) {
                this.alertService.error('No se pueden eliminar valores que están en uso');
                return;
            }
        }

        const dataToSend = {
            name: this.field.name,
            field_type: this.field.field_type,
            is_required: this.field.is_required,
            values: this.field.values.map(v => ({
                id: v.id,
                value: v.value,
                is_active: v.is_active
            }))
        };

        this.loading = true;
        const request = this.field.id ?
            this.apiService.update('custom-fields', this.field.id, dataToSend) :
            this.apiService.store('custom-fields', dataToSend);

        request.pipe(this.untilDestroyed()).subscribe({
            next: () => {
                this.alertService.success(
                    'Éxito',
                    this.field.id ? 'Campo actualizado exitosamente' : 'Campo creado exitosamente'
                );
                this.loadAll();
                this.closeModal();
            },
            error: (error) => this.alertService.error(error),
            complete: () => this.loading = false
        });
    }

    setPagination(event: { page: number }) {
        this.filtros.page = event.page;
        this.loadAll();
    }
}