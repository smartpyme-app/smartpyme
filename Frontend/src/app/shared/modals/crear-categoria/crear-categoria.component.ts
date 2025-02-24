import { Component, OnInit, TemplateRef, Output, EventEmitter } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-categoria',
  templateUrl: './crear-categoria.component.html'
})
export class CrearCategoriaComponent implements OnInit {
  public categoria: any = {};
  public categorias: any = [];
  public loading = false;
  
  @Output() update = new EventEmitter();
  
  modalRef?: BsModalRef;

  constructor(
    private apiService: ApiService, 
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

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

  openModal(template: TemplateRef<any>) {
    this.categoria = {};
    this.categoria.enable = true;
    this.categoria.subcategoria = false;
    this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
    this.modalRef = this.modalService.show(template, { class: 'modal-sm', backdrop: 'static' });
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
        this.modalRef?.hide();
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