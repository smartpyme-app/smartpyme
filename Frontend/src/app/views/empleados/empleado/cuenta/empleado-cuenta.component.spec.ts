import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { EmpleadoCuentaComponent } from './empleado-cuanta.component';

describe('EmpleadoCuentaComponent', () => {
  let component: EmpleadoCuentaComponent;
  let fixture: ComponentFixture<EmpleadoCuentaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ EmpleadoCuentaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(EmpleadoCuentaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
