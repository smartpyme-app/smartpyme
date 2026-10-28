import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { environment } from '../../../environments/environment';

@Component({
    selector: 'app-footer',
    templateUrl: './footer.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class FooterComponent implements OnInit {

    appVersion: string = '';

  constructor() { }

  ngOnInit() {
    // ponytail: evita fetch al manifest (en algunos despliegues pasa por PHP y tarda segundos).
    this.appVersion = environment.appVersion
      ? `Versión: ${environment.appVersion}`
      : '';
  }

}
