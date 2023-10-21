import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-tipos',
  templateUrl: './tipos.component.html'
})

export class TiposComponent implements OnInit {

    public tipo:any = {};
    public loading:boolean = false;

    @Input() tipos:any = [];
    @Input() subcategoria:any = [];
    @Output() update = new EventEmitter();

    modalRef?: BsModalRef;

    // Img Upload
    public file?:File;
    public preview = false;
    public url_img_preview:string = '';

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        // this.loadAll(this.subcategoria_id);
    }

    // public loadAll(id:number) {
    //     this.loading = true;
    //     this.apiService.getAll('subcategoria/' + id + '/tipos').subscribe(tipos => { 
    //         this.tipos = tipos;
    //         this.file = null;
    //         this.loading = false;
    //     }, error => {this.alertService.error(error); });
    // }


    openModal(template: TemplateRef<any>, tipo:any) {
        this.tipo = tipo;
        this.modalRef = this.modalService.show(template, {class: 'modal-sm', backdrop: 'static'});
    }

    slug(){
        this.tipo.slug = this.apiService.slug(this.tipo.nombre);
    }


    onSubmit(){
        this.tipo.subcategoria_id = this.subcategoria.id;
        
        let formData:FormData = new FormData();
        for (var key in this.tipo) {
            formData.append(key, this.tipo[key] ? this.tipo[key] : '');
        }

        this.loading = true;
        this.apiService.store('tipo', formData).subscribe(tipo => {
            if(!this.tipo.id)
                this.tipos.push(tipo);
            this.loading = false;
            this.update.emit();
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    onNameChange(tipo:any, name:string):void{
        this.tipo = tipo;
        this.tipo.nombre = name;
        this.onSubmit();
    }


    delete(tipo:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('tipo/', tipo.id) .subscribe(data => {
                for (let i = 0; i < this.tipos.length; i++) { 
                    if (this.tipos[i].id == data.id )
                        this.tipos.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    setFile(event:any){
        this.file = event.target.files[0];
        this.tipo.file = this.file;
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
