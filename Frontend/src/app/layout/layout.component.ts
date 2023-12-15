import { Component } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-layout',
  templateUrl: './layout.component.html'
})

export class LayoutComponent  {
    public usuario: any = {};
    public elem: any;
    public isfullscreen: boolean = false;
    public isVisible: boolean = false;

    constructor(private apiService: ApiService, public alertService: AlertService) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
    }
}
