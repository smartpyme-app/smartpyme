import { Component, OnInit, Input } from '@angular/core';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-admin-header',
  templateUrl: './admin-header.component.html'
})
export class AdminHeaderComponent implements OnInit {

    @Input() usuario:any = {};

    constructor(public apiService: ApiService) {}

    ngOnInit() {
    }

}
