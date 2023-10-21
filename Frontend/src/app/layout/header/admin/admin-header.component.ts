import { Component, OnInit, Input } from '@angular/core';


@Component({
  selector: 'app-admin-header',
  templateUrl: './admin-header.component.html'
})
export class AdminHeaderComponent implements OnInit {

    @Input() usuario:any = {};

    ngOnInit() {
    }

}
