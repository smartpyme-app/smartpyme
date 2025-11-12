import { Component, OnInit, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';


@Component({
    selector: 'app-admin-header',
    templateUrl: './admin-header.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class AdminHeaderComponent implements OnInit {

    @Input() usuario:any = {};

    ngOnInit() {
    }

}
