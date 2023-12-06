import { Component, OnInit} from '@angular/core';

import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-facturacion',
  templateUrl: './facturacion.component.html'
})

export class FacturacionComponent implements OnInit {
    
    public usuario: any = {};

    constructor(private apiService: ApiService) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
    }


}
