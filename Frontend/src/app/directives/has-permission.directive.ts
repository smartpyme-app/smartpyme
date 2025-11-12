import { Directive, Input, TemplateRef, ViewContainerRef, OnInit } from '@angular/core';
import { ApiService } from '../services/api.service';  // Ajusta la ruta según tu estructura

@Directive({
    selector: '[hasPermission]',
    standalone: true
})
export class HasPermissionDirective implements OnInit {
    @Input('hasPermission') permission: string = '';

    constructor(
        private templateRef: TemplateRef<any>,
        private viewContainer: ViewContainerRef,
        private apiService: ApiService
    ) {}

    ngOnInit() {
        if (this.apiService.hasPermission(this.permission)) {
            this.viewContainer.createEmbeddedView(this.templateRef);
        } else {
            this.viewContainer.clear();
        }
    }
}