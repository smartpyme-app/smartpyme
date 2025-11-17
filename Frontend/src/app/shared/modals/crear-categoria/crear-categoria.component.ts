import { Component, OnInit, TemplateRef, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-categoria',
    templateUrl: './crear-categoria.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearCategoriaComponent extends BaseModalComponent implements OnInit {
  public categoria: any = {};
  public categorias: any = [];
  public override loading = false;
  
  @Output() update = new EventEmitter();

  constructor(
    private apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {
    this.loadCategorias();
  }

  loadCategorias() {
    this.apiService.getAll('categorias/list').subscribe(
      categorias => {
        // Cuando es subcategoría, solo mostrar categorías principales
        if (this.categoria.subcategoria) {
          this.categorias = categorias.filter((cat: any) => !cat.subcategoria);
        } else {
          this.categorias = categorias;
        }
      }, 
      error => {
        this.alertService.error(error);
      }
    );
  }

  override openModal(template: TemplateRef<any>) {
    this.categoria = {};
    this.categoria.enable = true;
    this.categoria.subcategoria = false;
    this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
    super.openModal(template, { class: 'modal-sm', backdrop: 'static' });
  }

  onSubcategoriaChange() {
    // Recargar categorías cuando cambia el estado de subcategoría
    this.loadCategorias();
    // Limpiar la categoría padre si se desactiva subcategoría
    if (!this.categoria.subcategoria) {
      this.categoria.id_cate_padre = null;
    }
  }

  public onSubmit() {
    this.loading = true;
    
    this.apiService.store('categoria', this.categoria).subscribe(
      categoria => {
        this.update.emit(categoria);
        this.closeModal();
        this.loading = false;
        this.alertService.success('Categoria creada', 'La categoria ha sido agregada.');
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }
}
