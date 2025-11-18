import { Component, OnInit, TemplateRef, Input, ViewChild, Output, EventEmitter, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-subcategorias',
    templateUrl: './subcategorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})

export class SubCategoriasComponent implements OnInit {

    public loading:boolean = false;

    @Input() subcategorias:any = [];
    @Input() categoria:any = {};
    @Output() update = new EventEmitter();
    public subcategoria:any = {};
    public categorias:any = [];
    public cambio:any = {};

    modalRef?: BsModalRef;

    // Img Upload
    public file?:File;
    public preview = false;
    public url_img_preview:string = '';

    @ViewChild('mcategorias')
    public categoriasTemplate!: TemplateRef<any>;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

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
        }, error => {this.alertService.error(error); });
    }


    openModal(template: TemplateRef<any>, subcategoria:any) {
        this.subcategoria = subcategoria;
        this.modalRef = this.modalService.show(template, {class: 'modal-sm', backdrop: 'static'});
    }

    slug(){
        this.subcategoria.slug = this.apiService.slug(this.subcategoria.nombre);
    }

    public setTipoComision(){
        if (this.subcategoria.tipo_comision == 'Ninguna') {
            this.subcategoria.comision = 0.0;
        }
    }

    onSubmit(){

        this.subcategoria.categoria_id = this.categoria.id;
        
        let formData:FormData = new FormData();
        for (var key in this.subcategoria) {
            formData.append(key, this.subcategoria[key] ? this.subcategoria[key] : '');
        }

        this.loading = true;
        this.apiService.store('subcategoria', formData)
          .pipe(this.untilDestroyed())
          .subscribe(subcategoria => {
            if(!this.subcategoria.id){
                this.categoria.subcategorias.push(subcategoria);
            }
            this.loading = false;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    onNameChange(subcategoria:any, name:string):void{
        this.subcategoria = subcategoria;
        this.subcategoria.nombre = name;
        this.onSubmit();
    }


    delete(subcategoria:any) {

        if (subcategoria.total_productos > 0) {
            alert('Hay productos asignados, primero cambie los productos a otra categoria.');
            this.openModalCategorias(subcategoria);
        }
        else{
            if (confirm('¿Desea eliminar el Registro?')) {
                this.apiService.delete('subcategoria/', subcategoria.id)
                  .pipe(this.untilDestroyed())
                  .subscribe(data => {
                    for (let i = 0; i < this.subcategorias.length; i++) { 
                        if (this.subcategorias[i].id == data.id )
                            this.subcategorias.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
                       
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
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(this.categoriasTemplate);

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
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});
    }




}
