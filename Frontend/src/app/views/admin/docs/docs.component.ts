import { Component } from '@angular/core';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-docs',
  templateUrl: './docs.component.html'
})

export class DocsComponent  {

  constructor( 
        public apiService: ApiService
    ) {}

}
