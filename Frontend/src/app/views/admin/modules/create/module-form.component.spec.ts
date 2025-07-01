// modules.component.spec.ts
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ModuleFormComponent } from './module-form.component';
import { ModulesComponent } from '../modules.component';

describe('ModuleFormComponent', () => {
  let component: ModuleFormComponent;
    let fixture: ComponentFixture<ModuleFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ModulesComponent ]
    })
    .compileComponents();
  });

  beforeEach(() => {
    fixture = TestBed.createComponent(ModuleFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});