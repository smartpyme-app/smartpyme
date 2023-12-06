import { Component } from '@angular/core';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-layout',
  templateUrl: './layout.component.html'
})

export class LayoutComponent  {
    constructor(private alertService: AlertService) {}
}
