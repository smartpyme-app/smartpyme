import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-cocina-datos',
  templateUrl: './cocina-datos.component.html'
})
export class CocinaDatosComponent implements OnInit {

    @Input() dash: any = {};
    @Input() loading:boolean = false;
    @Output() loadAll = new EventEmitter();
    public usuario:any = {};
    public departamentos:any = [];

    constructor( 
          private apiService: ApiService, private alertService: AlertService, private route: ActivatedRoute, private router: Router,
    ) { }

    ngOnInit() {

        this.usuario = this.apiService.auth_user();
        if (!this.departamentos.length) {
          this.apiService.getAll('departamentos').subscribe(departamentos => {
              this.departamentos = departamentos.data;
              this.loading = false;
          }, error => {this.alertService.error(error); this.loading = false;});
        }
    }


}
