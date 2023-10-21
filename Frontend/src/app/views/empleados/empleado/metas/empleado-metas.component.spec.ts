import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { EmpleadoMetasComponent } from './empleado-metas.component';

describe('EmpleadoMetasComponent', () => {
  let component: EmpleadoMetasComponent;
  let fixture: ComponentFixture<EmpleadoMetasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ EmpleadoMetasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(EmpleadoMetasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
