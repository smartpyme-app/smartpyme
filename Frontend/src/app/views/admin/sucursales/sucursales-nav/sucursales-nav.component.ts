import { ChangeDetectionStrategy, Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-sucursales-nav',
    standalone: true,
    imports: [CommonModule, RouterModule],
    templateUrl: './sucursales-nav.component.html',
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SucursalesNavComponent {
    constructor(public apiService: ApiService) {}
}
