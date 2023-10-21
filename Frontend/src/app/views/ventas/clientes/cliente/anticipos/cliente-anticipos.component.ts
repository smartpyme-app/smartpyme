import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cliente-anticipos',
  templateUrl: './cliente-anticipos.component.html'
})
export class ClienteAnticiposComponent implements OnInit {

    public cliente:any = {};
		public anticipos:any = [];
		public token:string = '';
    public loading:boolean = false;

	  constructor(private apiService: ApiService, private alertService: AlertService,  private route: ActivatedRoute, private router: Router){ }

		ngOnInit() {
			this.token = this.apiService.auth_token();
	        this.loadAll();
	    }

	    public loadAll() {
	        const id = +this.route.snapshot.paramMap.get('id')!;
	                	        
 	        if(isNaN(id)){
 	            this.anticipos = [];
 	        }
 	        else{
 	            // Optenemos el anticipos
              this.loading = true;
              this.apiService.read('cliente/', id).subscribe(cliente => {
                  this.cliente = cliente;
              
              }, error => {this.alertService.error(error); });
              this.apiService.read('cliente/anticipos/', id).subscribe(anticipos => {
                  this.anticipos = anticipos;
                  this.loading = false;
 	            }, error => {this.alertService.error(error); });
 	        }
	    }

        public setPagination(event:any):void{
           this.loading = true;
           this.apiService.paginate(this.anticipos.path + '?page='+ event.page).subscribe(anticipos => { 
               this.anticipos = anticipos;
               this.loading = false;
           }, error => {this.alertService.error(error); this.loading = false;});
        }


}
