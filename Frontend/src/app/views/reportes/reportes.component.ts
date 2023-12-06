import { Component } from '@angular/core';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-reportes',
    templateUrl: './reportes.component.html'
})
export class ReportesComponent {

    constructor(public apiService: ApiService) {}
    
}
