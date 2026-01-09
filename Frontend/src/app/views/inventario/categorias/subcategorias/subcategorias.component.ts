import { Component, OnInit, TemplateRef, Input, ViewChild, Output, EventEmitter, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-subcategorias',
    templateUrl: './subcategorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class SubCategoriasComponent extends BaseModalComponent implements OnInit {

    @Input() subcategorias:any = [];
    @Input() categoria:any = {};
    @Output() update = new EventEmitter();
    public subcategoria:any = {};
    public categorias:any = [];
    public cambio:any = {};

    // Img Upload
    public file?:File;
    public preview = false;
    public url_img_preview:string = '';

    @ViewChild('mcategorias')
    public categoriasTemplate!: TemplateRef<any>;

    constructor(
        public apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {
        // this.loadAll(this.categoria_id);
    }

    public loadAll(id:number) {
        this.loading = true;
        this.apiService.getAll('categoria/' + id + '/subcategorias')
          .pipe(this.untilDestroyed())
          .subscribe(subcategorias => { 
            this.subcategorias = subcategorias;
            this.file = null!;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    override openModal(template: TemplateRef<any>, subcategoria:any) {
        this.subcategoria = subcategoria;
        super.openModal(template, {class: 'modal-sm', backdrop: 'static'});
    }

    slug(){
        this.subcategoria.slug = this.apiService.slug(this.subcategoria.nombre);
        this.cdr.markForCheck();
    }

    public setTipoComision(){
        if (this.subcategoria.tipo_comision == 'Ninguna') {
            this.subcategoria.comision = 0.0;
        }
        this.cdr.markForCheck();
    }

    async onSubmit(){
        this.subcategoria.categoria_id = this.categoria.id;
        
        const formData: FormData = new FormData();
        for (const key in this.subcategoria) {
            formData.append(key, this.subcategoria[key] ? this.subcategoria[key] : '');
        }

        this.loading = true;
        try {
            const subcategoriaGuardada = await this.apiService.store('subcategoria', formData)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            if (!this.subcategoria.id) {
                this.categoria.subcategorias.push(subcategoriaGuardada);
            }
            this.closeModal();
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

    onNameChange(subcategoria:any, name:string):void{
        this.subcategoria = subcategoria;
        this.subcategoria.nombre = name;
        this.onSubmit();
    }

    async delete(subcategoria:any) {
        if (subcategoria.total_productos > 0) {
            alert('Hay productos asignados, primero cambie los productos a otra categoria.');
            this.openModalCategorias(subcategoria);
        } else {
            if (confirm('¿Desea eliminar el Registro?')) {
                try {
                    const data = await this.apiService.delete('subcategoria/', subcategoria.id)
                        .pipe(this.untilDestroyed())
                        .toPromise();
                    
                    for (let i = 0; i < this.subcategorias.length; i++) { 
                        if (this.subcategorias[i].id == data.id )
                            this.subcategorias.splice(i, 1);
                    }
                    this.cdr.markForCheck();
                } catch (error: any) {
                    this.alertService.error(error);
                    this.cdr.markForCheck();
                }
            }
        }
    }

    setFile(event:any){
        this.file = event.target.files[0];
        this.subcategoria.file = this.file;
        var reader = new FileReader();
        reader.onload = ()=> {
            var url:any;
            url = reader.result;
            this.url_img_preview = url;
            this.preview = true;
            this.cdr.markForCheck();
           };
        reader.readAsDataURL(this.file!);
    }

    openModalCategorias(subcategoria:any) {
        this.subcategoria = subcategoria;
        if(!this.categorias.length){
            this.apiService.getAll('categorias')
              .pipe(this.untilDestroyed())
              .subscribe(categorias => { 
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        }
        super.openModal(this.categoriasTemplate);

    }

    onChangeCategoria(){
        this.cambio.subcategoria_anterior = this.subcategoria.id;
        this.loading = true;
        this.apiService.store('subcategoria/cambio', this.cambio)
          .pipe(this.untilDestroyed())
          .subscribe(subcategoria => {
            this.subcategoria.total_productos = 0;
            this.update.emit();
            this.loading = false;
            this.closeModal();
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

}
