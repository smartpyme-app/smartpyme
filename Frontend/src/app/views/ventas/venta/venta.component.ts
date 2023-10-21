import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../pipes/sum.pipe';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-venta',
  templateUrl: './venta.component.html'
})
export class VentaComponent implements OnInit {

    public venta:any = {};

    public loading = false;

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {

        this.loadAll();

    }

    public loadAll(){
        this.venta.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;
        this.apiService.read('venta/', this.venta.id).subscribe(venta => {
        this.venta = venta;
        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
