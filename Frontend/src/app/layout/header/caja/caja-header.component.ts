import { Component, OnInit, Input } from '@angular/core';

@Component({
  selector: 'app-caja-header',
  templateUrl: './caja-header.component.html'
})
export class CajaHeaderComponent implements OnInit {

    @Input() usuario:any = {};

    ngOnInit() {
    }


}
