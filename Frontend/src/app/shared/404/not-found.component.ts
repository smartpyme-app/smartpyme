import { Component } from '@angular/core';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-not-found',
  templateUrl: './not-found.component.html'
})

export class NotFoundComponent  {

  constructor( 
        public apiService: ApiService
    ) {}

}
