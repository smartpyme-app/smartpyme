import { Component, OnInit,TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
  selector: 'app-flota-mantenimientos',
  templateUrl: './flota-mantenimientos.component.html'
})
export class FlotaMantenimientosComponent implements OnInit {

    public mantenimientos:any = [];
    public orden:any = {};
    public countries:any = [];

    public loading = false;
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        const id = +this.route.snapshot.paramMap.get('id')!;
        if(id)
            this.loadAll(id);
        else
            this.mantenimientos = [];
    }

    public loadAll(id:number){
        this.loading = true;
        this.apiService.getAll('flota/mantenimientos/' + id ).subscribe(mantenimientos => {
            this.mantenimientos = mantenimientos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.mantenimientos.path + '?page='+ event.page).subscribe(mantenimientos => { 
            this.mantenimientos = mantenimientos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
