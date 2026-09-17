import { Component, EventEmitter, Input, Output } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-activos-nav-tabs',
    standalone: true,
    imports: [RouterModule],
    templateUrl: './activos-nav-tabs.component.html',
})
export class ActivosNavTabsComponent {
    @Input() todosClickable = false;
    @Output() todosClick = new EventEmitter<void>();

    constructor(public apiService: ApiService) {}

    onTodosClick(): void {
        if (this.todosClickable) {
            this.todosClick.emit();
        }
    }
}
