import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-docs',
    templateUrl: './docs.component.html',
    standalone: true,
    imports: [CommonModule]
})

export class DocsComponent  {

  constructor( 
        public apiService: ApiService
    ) {}

}
