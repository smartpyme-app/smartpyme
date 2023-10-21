import { Component, OnInit, Input } from '@angular/core';

@Component({
  selector: 'app-mesero-header',
  templateUrl: './mesero-header.component.html'
})
export class MeseroHeaderComponent implements OnInit {

    @Input() usuario:any = {};

    ngOnInit() {
    }


}
