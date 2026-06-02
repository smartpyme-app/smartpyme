import { Component, EventEmitter, Output, TemplateRef, ViewChild } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cliente-nota-modal',
  templateUrl: './cliente-nota-modal.component.html'
})
export class ClienteNotaModalComponent {

  @ViewChild('notaModalTemplate') notaModalTemplate!: TemplateRef<any>;
  @Output() notaGuardada = new EventEmitter<void>();

  public noteForm!: FormGroup;
  public usuarios: any[] = [];
  public isEditingNote = false;
  public editingNoteId: number | null = null;
  public editingNoteType = '';
  public modalErrors: any = {};
  public showModalErrors = false;
  public saving = false;
  public clienteNombre = '';

  private clienteId: number | null = null;
  private modalRef?: BsModalRef;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private formBuilder: FormBuilder
  ) {
    this.initializeNoteForm();
    this.loadUsuarios();
  }

  open(clienteId: number, clienteNombre?: string): void {
    this.clienteId = clienteId;
    this.clienteNombre = clienteNombre || '';
    this.isEditingNote = false;
    this.editingNoteId = null;
    this.editingNoteType = '';
    this.modalErrors = {};
    this.showModalErrors = false;

    this.noteForm.reset({
      tipo: '',
      fecha: new Date().toISOString().split('T')[0],
      hora: new Date().toTimeString().slice(0, 5),
      titulo: '',
      responsable: '',
      prioridad: 'medium',
      estado: 'activo',
      notas: '',
      requiere_seguimiento: false,
      seguimiento: '',
      resolucion: ''
    });

    this.showModal();
  }

  openEdit(clienteId: number, interaction: any, clienteNombre?: string): void {
    this.clienteId = clienteId;
    this.clienteNombre = clienteNombre || '';
    this.isEditingNote = true;
    this.editingNoteId = interaction.id;
    this.editingNoteType = interaction.type;
    this.modalErrors = {};
    this.showModalErrors = false;

    this.noteForm.patchValue({
      tipo: interaction.type === 'visita' ? 'visita' : interaction.tipo,
      fecha: interaction.fecha,
      hora: interaction.hora,
      titulo: interaction.titulo,
      responsable: interaction.responsable,
      prioridad: interaction.prioridad,
      estado: interaction.estado || 'activo',
      notas: interaction.type === 'visita' ? interaction.descripcion : interaction.contenido,
      requiere_seguimiento: interaction.requiere_seguimiento || false,
      seguimiento: interaction.fecha_seguimiento || '',
      resolucion: interaction.resolucion || ''
    });

    this.showModal();
  }

  close(): void {
    this.modalRef?.hide();
    this.noteForm.reset();
    this.isEditingNote = false;
    this.editingNoteId = null;
    this.editingNoteType = '';
    this.clienteId = null;
    this.clienteNombre = '';
    this.modalErrors = {};
    this.showModalErrors = false;
    this.saving = false;
  }

  saveNote(): void {
    this.modalErrors = {};
    this.showModalErrors = false;

    if (this.noteForm.invalid) {
      this.markFormGroupTouched();
      this.modalErrors = { general: 'Por favor completa todos los campos obligatorios correctamente' };
      this.showModalErrors = true;
      this.alertService.error('Por favor completa todos los campos obligatorios correctamente');
      return;
    }

    if (!this.clienteId) {
      this.alertService.error('Cliente no válido');
      return;
    }

    const formValue = this.noteForm.value;
    this.saving = true;

    if (this.isEditingNote && this.editingNoteId) {
      const updateData = {
        tipo: formValue.tipo,
        titulo: formValue.titulo,
        contenido: formValue.notas,
        responsable: formValue.responsable,
        prioridad: formValue.prioridad,
        estado: formValue.estado,
        requiere_seguimiento: formValue.requiere_seguimiento || false,
        fecha_interaccion: formValue.fecha,
        hora_interaccion: formValue.hora,
        fecha_seguimiento: formValue.seguimiento || null,
        resolucion: formValue.resolucion || null
      };

      const endpoint = this.editingNoteType === 'visita'
        ? 'cliente-notas/visitas'
        : 'cliente-notas/notas';

      this.apiService.update(endpoint, this.editingNoteId, updateData).subscribe({
        next: (response) => {
          this.saving = false;
          if (response.success) {
            this.alertService.success('success', 'Nota actualizada exitosamente');
            this.close();
            this.notaGuardada.emit();
          } else {
            this.modalErrors = { general: 'Error al actualizar la nota' };
            this.showModalErrors = true;
            this.alertService.error('Error al actualizar la nota');
          }
        },
        error: (error) => {
          this.saving = false;
          this.handleModalError(error);
        }
      });
      return;
    }

    const notaData = {
      cliente_id: this.clienteId,
      tipo: formValue.tipo,
      titulo: formValue.titulo,
      contenido: formValue.notas,
      responsable: formValue.responsable,
      prioridad: formValue.prioridad,
      estado: formValue.estado,
      requiere_seguimiento: formValue.requiere_seguimiento || false,
      fecha_interaccion: formValue.fecha,
      hora_interaccion: formValue.hora,
      fecha_seguimiento: formValue.seguimiento || null,
      resolucion: formValue.resolucion || null
    };

    this.apiService.store('cliente-notas/notas', notaData).subscribe({
      next: (response) => {
        this.saving = false;
        if (response.success) {
          this.alertService.success('success', 'Nota guardada exitosamente');
          this.close();
          this.notaGuardada.emit();
        } else {
          this.modalErrors = { general: 'Error al guardar la nota' };
          this.showModalErrors = true;
          this.alertService.error('Error al guardar la nota');
        }
      },
      error: (error) => {
        this.saving = false;
        this.handleModalError(error);
      }
    });
  }

  getErrorFields(): string[] {
    return Object.keys(this.modalErrors).filter(field => field !== 'general');
  }

  getFieldLabel(field: string): string {
    const labels: Record<string, string> = {
      fecha_seguimiento: 'Fecha de Seguimiento',
      fecha: 'Fecha',
      hora: 'Hora',
      titulo: 'Título',
      tipo: 'Tipo de Interacción',
      responsable: 'Responsable',
      prioridad: 'Prioridad',
      estado: 'Estado',
      notas: 'Notas',
      contenido: 'Contenido'
    };
    return labels[field] || field;
  }

  private showModal(): void {
    this.modalRef = this.modalService.show(this.notaModalTemplate, {
      class: 'modal-lg',
      backdrop: 'static'
    });
  }

  private initializeNoteForm(): void {
    this.noteForm = this.formBuilder.group({
      tipo: ['', Validators.required],
      fecha: [new Date().toISOString().split('T')[0], Validators.required],
      hora: ['14:30', Validators.required],
      titulo: ['', [Validators.required, Validators.minLength(5)]],
      responsable: ['', Validators.required],
      prioridad: ['medium', Validators.required],
      estado: ['activo', Validators.required],
      requiere_seguimiento: [false],
      notas: ['', [Validators.required, Validators.minLength(10)]],
      seguimiento: [''],
      resolucion: ['']
    });
  }

  private loadUsuarios(): void {
    this.apiService.getAll('usuarios/list').subscribe({
      next: (response) => {
        this.usuarios = Array.isArray(response) ? response : [];
      },
      error: () => {
        this.usuarios = [];
      }
    });
  }

  private markFormGroupTouched(): void {
    Object.keys(this.noteForm.controls).forEach(key => {
      this.noteForm.get(key)?.markAsTouched();
    });
  }

  private handleModalError(error: any): void {
    if (error.status === 422 && error.error?.errors) {
      this.modalErrors = error.error.errors;
      this.showModalErrors = true;
      const errorMessages = Object.values(error.error.errors).flat();
      this.alertService.error({
        status: 422,
        error: { error: (errorMessages as string[]).join(', ') }
      });
    } else if (error.status === 400 && error.error?.message) {
      this.modalErrors = { general: error.error.message };
      this.showModalErrors = true;
      this.alertService.error(error.error.message);
    } else {
      this.modalErrors = { general: 'Ha ocurrido un error inesperado. Por favor, intenta nuevamente.' };
      this.showModalErrors = true;
      this.alertService.error('Ha ocurrido un error inesperado. Por favor, intenta nuevamente.');
    }
  }
}
