// components/admin/authorization-types/authorization-types.component.ts
import { Component, OnInit } from '@angular/core';
import { AuthorizationService, AuthorizationType } from '@services/Authorization/authorization.service';
import { AlertService } from '@services/alert.service';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-authorization-types',
  templateUrl: './authorization-types.component.html'
})
export class AuthorizationTypesComponent implements OnInit {
  types: AuthorizationType[] = [];
  loading: boolean = false;
  
  // Modal para crear/editar tipo
  showTypeModal: boolean = false;
  editingType: AuthorizationType | null = null;
  typeForm: any = {
    name: '',
    display_name: '',
    description: '',
    expiration_hours: 24,
    conditions: {}
  };

  // Modal para asignar usuarios
  showUsersModal: boolean = false;
  selectedType: AuthorizationType | null = null;
  availableUsers: any[] = [];
  assignedUsers: any[] = [];
  selectedUserIds: number[] = [];

  constructor(
    private authorizationService: AuthorizationService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    this.loadTypes();
  }

  loadTypes() {
    this.loading = true;
    this.authorizationService.getAuthorizationTypes().subscribe({
      next: (response) => {
        this.types = response.data;
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  openTypeModal(type?: AuthorizationType) {
    this.editingType = type || null;
    this.typeForm = type ? { ...type } : {
      name: '',
      display_name: '',
      description: '',
      expiration_hours: 24,
      conditions: {}
    };
    this.showTypeModal = true;
  }

  saveType() {
    this.loading = true;
    
    if (this.editingType) {
      // Actualizar tipo existente
      // Aquí iría la lógica de actualización
      this.alertService.success('success','Función de actualización pendiente de implementar');
      this.loading = false;
    } else {
      // Crear nuevo tipo
      this.authorizationService.createAuthorizationType(this.typeForm).subscribe({
        next: (response) => {
          if (response.ok) {
            this.alertService.success('success','Tipo de autorización creado exitosamente');
            this.loadTypes();
            this.closeTypeModal();
          }
          this.loading = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      });
    }
  }

  closeTypeModal() {
    this.showTypeModal = false;
    this.editingType = null;
    this.typeForm = {
      name: '',
      display_name: '',
      description: '',
      expiration_hours: 24,
      conditions: {}
    };
  }

  openUsersModal(type: AuthorizationType) {
    this.selectedType = type;
    this.loadUsersForType(type.id);
    this.showUsersModal = true;
  }

  loadUsersForType(typeId: number) {
    this.loading = true;
    
    // Cargar usuarios disponibles
    this.authorizationService.getAvailableUsers(typeId).subscribe({
      next: (response) => {
        if (response.ok) {
          this.availableUsers = response.data;
        }
      },
      error: (error) => this.alertService.error(error)
    });

    // Cargar usuarios asignados
    this.authorizationService.getAuthorizationTypeUsers(typeId).subscribe({
      next: (response) => {
        if (response.ok) {
          this.assignedUsers = response.data;
          this.selectedUserIds = this.assignedUsers.map(u => u.id);
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  onUserSelectionChange(userId: number, event: any) {
    if (event.target.checked) {
      if (!this.selectedUserIds.includes(userId)) {
        this.selectedUserIds.push(userId);
      }
    } else {
      this.selectedUserIds = this.selectedUserIds.filter(id => id !== userId);
    }
  }

  saveUserAssignments() {
    if (!this.selectedType) return;

    this.loading = true;
    this.authorizationService.assignUsersToAuthorizationType(
      this.selectedType.id, 
      this.selectedUserIds
    ).subscribe({
      next: (response) => {
        if (response.ok) {
          this.alertService.success('success','Usuarios asignados exitosamente');
          this.closeUsersModal();
          this.loadTypes();
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  closeUsersModal() {
    this.showUsersModal = false;
    this.selectedType = null;
    this.availableUsers = [];
    this.assignedUsers = [];
    this.selectedUserIds = [];
  }

  isUserSelected(userId: number): boolean {
    return this.selectedUserIds.includes(userId);
  }

  addCondition() {
    // Lógica para agregar condiciones dinámicamente
    const key = prompt('Nombre de la condición:');
    const value = prompt('Valor de la condición:');
    
    if (key && value) {
      this.typeForm.conditions[key] = isNaN(Number(value)) ? value : Number(value);
    }
  }

  removeCondition(key: string) {
    delete this.typeForm.conditions[key];
  }

  getConditionKeys(): string[] {
    return Object.keys(this.typeForm.conditions || {});
  }

  isUserAssigned(userId: number): boolean {
    return this.assignedUsers.find(u => u.id === userId) !== undefined;
  }
  
  isUserNotAssigned(userId: number): boolean {
    return !this.assignedUsers.find(u => u.id === userId);
  }
  
  toggleAllUsers(event: any) {
    if (event.target.checked) {
      // Seleccionar todos los usuarios disponibles
      this.selectedUserIds = [...this.availableUsers.map(u => u.id)];
    } else {
      // Deseleccionar todos
      this.selectedUserIds = [];
    }
  }
}