import { Component, OnInit, Input } from '@angular/core';
import { ApiService } from '@services/api.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';


@Component({
    selector: 'app-admin-header',
    templateUrl: './admin-header.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, TranslatePipe],

})
export class AdminHeaderComponent implements OnInit {

    @Input() usuario:any = {};

    constructor(public apiService: ApiService) {}

    ngOnInit() {
    }

}
