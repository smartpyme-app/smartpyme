import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';
import { CrearCategoriaComponent } from '@shared/modals/crear-categoria/crear-categoria.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-ver-producto',
  templateUrl: './ver-producto.component.html',
  styleUrls: ['./ver-producto.component.css']
})
export class VerProductoComponent {

  producto: any = {};
  public usuario: any = {};
  public loading = false;
  public guardar = false;


  constructor(public apiService: ApiService, private alertService: AlertService, private route: ActivatedRoute){}

  ngOnInit(){
    let param = this.route.snapshot.params;
  
    if(param['id']){
      this.apiService.read('producto/', param['id']).subscribe(producto => {
        this.producto = producto;
      },error => {this.alertService.error(error);this.loading = false;});
    }
  }
  

}
