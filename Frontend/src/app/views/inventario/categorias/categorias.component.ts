import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-categorias',
  templateUrl: './categorias.component.html'
})

export class CategoriasComponent implements OnInit {

    public categorias:any = [];
    public categoria:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    modalRef?: BsModalRef;
    // Img Upload
    public file!:File;
    public preview = false;
    public url_img_preview:string = '';

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('categorias').subscribe(categorias => { 
            this.categorias = categorias;
            this.file = null!;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }


    openModal(template: TemplateRef<any>, categoria:any) {
        this.categoria = categoria;
        this.modalRef = this.modalService.show(template, {class: 'modal-sm', backdrop: 'static'});
    }


    slug(){
        this.categoria.slug = this.apiService.slug(this.categoria.nombre);
    }

    public setTipoComision(){
        if (this.categoria.tipo_comision == 'Ninguna') {
            this.categoria.comision = 0.0;
        }
    }

    onSubmit():void{
        this.categoria.empresa_id = this.apiService.auth_user().empresa_id;
        
        let formData:FormData = new FormData();
        for (var key in this.categoria) {
            formData.append(key, this.categoria[key] ? this.categoria[key] : '');
        }

        this.loading = true;
        this.apiService.store('categoria', formData).subscribe(categoria => {
            if(!this.categoria.id){
                categoria.subcategoria = [];
                this.categorias.push(categoria);
            }
            this.loadAll();
            this.loading = false;
            this.alertService.success("Datos guardados");
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    onNameChange(categoria:any, name:string):void{
        this.categoria = categoria;
        this.categoria.nombre = name;
        this.onSubmit();
    }


    delete(categoria:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('categoria/', categoria.id) .subscribe(data => {
                for (let i = 0; i < this.categorias.length; i++) { 
                    if (this.categorias[i].id == data.id )
                        this.categorias.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    
    setFile(event:any){
        this.file = event.target.files[0];
        this.categoria.file = this.file;
        var reader = new FileReader();
        reader.onload = ()=> {
            var url:any;
            url = reader.result;
            this.url_img_preview = url;
            this.preview = true;
           };
        reader.readAsDataURL(this.file!);
    }


}
