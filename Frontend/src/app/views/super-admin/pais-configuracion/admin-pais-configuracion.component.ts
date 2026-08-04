import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-admin-pais-configuracion',
  templateUrl: './admin-pais-configuracion.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminPaisConfiguracionComponent implements OnInit {
  rows: any[] = [];
  filtered: any[] = [];
  filtros = { pais: '', modulo: '', buscador: '' };
  loading = false;
  saving = false;
  editando = false;
  form: any = {};
  jsonText = '';
  jsonError = '';
  modalRef!: BsModalRef;

  readonly paises = ['SV', 'CR', 'HN'];
  readonly modulosSugeridos = ['documentos', 'impuestos', 'retenciones'];

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.loading = true;
    this.apiService.getAll('pais-configuraciones').subscribe({
      next: (data) => {
        this.rows = Array.isArray(data) ? data : [];
        this.applyFilter();
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
        this.cdr.markForCheck();
      },
    });
  }

  applyFilter(): void {
    const pais = (this.filtros.pais || '').toUpperCase();
    const modulo = (this.filtros.modulo || '').toLowerCase();
    const q = (this.filtros.buscador || '').toLowerCase();

    this.filtered = this.rows.filter((r) => {
      if (pais && String(r.pais).toUpperCase() !== pais) return false;
      if (modulo && String(r.modulo).toLowerCase() !== modulo) return false;
      if (!q) return true;
      const blob = `${r.pais} ${r.modulo} ${JSON.stringify(r.configuracion)}`.toLowerCase();
      return blob.includes(q);
    });
    this.cdr.markForCheck();
  }

  openCrear(template: TemplateRef<any>): void {
    this.editando = false;
    this.form = { pais: 'SV', modulo: 'documentos' };
    this.jsonText = JSON.stringify({ nombres: [], seed: [] }, null, 2);
    this.jsonError = '';
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  openEditar(row: any, template: TemplateRef<any>): void {
    this.editando = true;
    this.form = { id: row.id, pais: row.pais, modulo: row.modulo };
    this.jsonText = JSON.stringify(row.configuracion ?? {}, null, 2);
    this.jsonError = '';
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  parseJson(): any | null {
    try {
      const parsed = JSON.parse(this.jsonText);
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        this.jsonError = 'La configuración debe ser un objeto JSON.';
        return null;
      }
      this.jsonError = '';
      return parsed;
    } catch {
      this.jsonError = 'JSON inválido.';
      return null;
    }
  }

  guardar(): void {
    const configuracion = this.parseJson();
    if (!configuracion) {
      this.cdr.markForCheck();
      return;
    }

    const payload = {
      pais: String(this.form.pais || '').trim().toUpperCase(),
      modulo: String(this.form.modulo || '').trim().toLowerCase(),
      configuracion,
    };

    if (!payload.pais || !payload.modulo) {
      this.alertService.error('País y módulo son obligatorios.');
      return;
    }

    this.saving = true;
    const req = this.editando
      ? this.apiService.update('pais-configuracion', this.form.id, payload)
      : this.apiService.store('pais-configuracion', payload);

    req.subscribe({
      next: () => {
        this.saving = false;
        this.modalRef?.hide();
        this.alertService.success(
          this.editando ? 'Configuración actualizada.' : 'Configuración creada.'
        );
        this.loadAll();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
        this.cdr.markForCheck();
      },
    });
  }

  eliminar(row: any): void {
    Swal.fire({
      title: '¿Eliminar configuración?',
      text: `${row.pais} / ${row.modulo}`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (!result.isConfirmed) return;
      this.apiService.delete('pais-configuracion/', row.id).subscribe({
        next: () => {
          this.alertService.success('Configuración eliminada.');
          this.loadAll();
        },
        error: (err) => this.alertService.error(err),
      });
    });
  }
}
