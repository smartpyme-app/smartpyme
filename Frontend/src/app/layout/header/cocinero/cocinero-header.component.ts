import { Component, OnInit, Input } from '@angular/core';

@Component({
  selector: 'app-cocinero-header',
  templateUrl: './cocinero-header.component.html'
})
export class CocineroHeaderComponent implements OnInit {
    
    @Input() usuario:any = {};

    ngOnInit() {
    }


}
