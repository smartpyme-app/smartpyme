import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Location } from '@angular/common';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-compra',
  templateUrl: './compra.component.html'
})
export class CompraComponent implements OnInit {

    public compra:any = {};
    public loading = false;

    modalRef!: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
        private location: Location
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {

        this.loadAll();

    }

    public loadAll(){
        if(this.modalRef){
            this.modalRef.hide();
        }
        this.compra.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;
        this.apiService.read('compra/', this.compra.id).subscribe(compra => {
        this.compra = compra;
        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public openAbono(template: TemplateRef<any>, compra:any){
        this.compra = compra;
        this.modalRef = this.modalService.show(template);
    }

    public goBack() {
        this.location.back();
    }

    public imprimir(){
        window.open(this.apiService.baseUrl + '/api/compra/impresion/' + this.compra.id + '?token=' + this.apiService.auth_token());
    }

}
