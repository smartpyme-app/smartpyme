import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { LazyImageDirective } from '../../directives/lazy-image.directive';

@Component({
    selector: 'app-not-found',
    templateUrl: './not-found.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, LazyImageDirective]
})

export class NotFoundComponent  {

  constructor( 
        public apiService: ApiService
    ) {}

}
