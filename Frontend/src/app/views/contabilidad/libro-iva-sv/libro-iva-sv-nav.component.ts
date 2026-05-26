import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

@Component({
  selector: 'app-libro-iva-sv-nav',
  standalone: true,
  imports: [RouterModule, TooltipModule],
  templateUrl: './libro-iva-sv-nav.component.html',
})
export class LibroIvaSvNavComponent {}
