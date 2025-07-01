import { Directive, Input, TemplateRef, ViewContainerRef, OnInit } from '@angular/core';
import { AuthorizationService } from '@services/Authorization/authorization.service';

@Directive({
  selector: '[appRequiresAuthorization]'
})
export class RequiresAuthorizationDirective implements OnInit {
  @Input() appRequiresAuthorization: string = '';
  @Input() authorizationData: any = {};

  constructor(
    private templateRef: TemplateRef<any>,
    private viewContainer: ViewContainerRef,
    private authorizationService: AuthorizationService
  ) { }

  ngOnInit() {
    this.checkAuthorization();
  }

  private checkAuthorization() {
    if (!this.appRequiresAuthorization) {
      this.viewContainer.createEmbeddedView(this.templateRef);
      return;
    }

    this.authorizationService.checkRequirement(
      this.appRequiresAuthorization, 
      this.authorizationData
    ).subscribe({
      next: (response : any) => {
        if (!response.requires_authorization) {
          this.viewContainer.createEmbeddedView(this.templateRef);
        } else {
          // Mostrar botón de solicitar autorización en lugar del contenido original
          this.showAuthorizationButton();
        }
      },
      error: () => {
        // En caso de error, mostrar el contenido original
        this.viewContainer.createEmbeddedView(this.templateRef);
      }
    });
  }

  private showAuthorizationButton() {
    this.viewContainer.clear();
    // Aquí podrías crear un botón que abra el modal de autorización
    // Por simplicidad, no se implementa aquí, pero es posible
  }
}