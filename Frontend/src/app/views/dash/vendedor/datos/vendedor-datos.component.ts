import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
    selector: 'app-vendedor-datos',
    templateUrl: './vendedor-datos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, CurrencyPipe],
    
})
export class VendedorDatosComponent implements OnInit {

    @Input() dash: any = {};
    @Input() loading:boolean = false;
    @Output() loadAll = new EventEmitter();
    public usuario:any = {};

    constructor( 
          private apiService: ApiService, private alertService: AlertService
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();    
    }


}
