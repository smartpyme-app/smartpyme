import { Component, OnInit, Input } from '@angular/core';

@Component({
  selector: 'app-vendedor-header',
  templateUrl: './vendedor-header.component.html'
})
export class VendedorHeaderComponent implements OnInit {

    @Input() usuario:any = {};

    ngOnInit() {
    }


}
