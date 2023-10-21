import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-cocinero-dash',
  templateUrl: './cocinero-dash.component.html'
})
export class CocineroDashComponent implements OnInit {

    public departamento!:number;

    constructor( 
        private apiService: ApiService, private alertService: AlertService, private router: Router,
    ) { }


    ngOnInit() {
        this.departamento = this.apiService.auth_user().departamento_id;

        if (this.departamento == 0) {
            this.router.navigate(['/dash/cocina/general']);
        }else{
            this.router.navigate(['/dash/cocina/departamento/' + this.departamento]);
        }

    }
    


}
