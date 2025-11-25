import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
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
export class CustomFieldsComponent extends BaseCrudComponent<CustomField> implements OnInit {
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

    public override filtros = {
        buscador: '',
        paginate: 10,
        orden: '',
        direccion: 'asc',
        page: 1
    };

    public newValue = '';

    constructor(
        protected override apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'custom-fields',
            itemsProperty: 'customFields',
            itemProperty: 'field',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'Campo creado exitosamente',
                updated: 'Campo actualizado exitosamente',
                deleted: 'Campo eliminado exitosamente',
                createTitle: 'Éxito',
                updateTitle: 'Éxito',
                deleteTitle: 'Éxito',
                deleteConfirm: '¿Está seguro de eliminar este campo?'
            },
            beforeSave: async (item) => {
                // Validaciones antes de guardar
                if (item.field_type === 'select' && !item.values?.length) {
                    throw new Error('Debe agregar al menos un valor para campos tipo lista');
                }

                if (this.originalField.in_use) {
                    if (item.field_type !== this.originalField.field_type) {
                        throw new Error('No se puede cambiar el tipo de un campo que está en uso');
                    }

                    const hasDeletedUsedValues = this.originalField.values
                        .filter(v => v.in_use)
                        .some(v => !item.values.find((nv: any) => nv.id === v.id));

                    if (hasDeletedUsedValues) {
                        throw new Error('No se pueden eliminar valores que están en uso');
                    }
                }

                // Transformar datos para enviar
                return {
                    ...item,
                    name: item.name,
                    field_type: item.field_type,
                    is_required: item.is_required,
                    values: item.values.map((v: any) => ({
                        id: v.id,
                        value: v.value,
                        is_active: v.is_active
                    }))
                } as any;
            },
            afterSave: () => {
                this.originalField = {
                    name: '',
                    field_type: 'select',
                    is_required: true,
                    values: []
                };
            }
        });
    }

    ngOnInit() {
        this.loadAll();
    }

    protected aplicarFiltros(): void {
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

    override async openModal(template: TemplateRef<any>, field?: CustomField, modalConfig?: any) {
        if (!field?.id) {
            // Nuevo campo
            this.field = {
                name: '',
                field_type: 'select',
                is_required: true,
                values: [],
                in_use: false
            };
            this.originalField = {
                name: '',
                field_type: 'select',
                is_required: true,
                values: []
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
    
        super.openModal(template, this.field as CustomField, { class: 'modal-lg', backdrop: 'static', ...modalConfig });
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
    }).then(async (result) => {
            if (result.isConfirmed && field.id) {
                this.delete(field.id);
        }
    });
    }

    // La paginación estándar se gestiona desde BaseCrudComponent
}