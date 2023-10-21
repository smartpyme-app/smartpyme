import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { EmpleadoDocumentosComponent } from './empleado-documentos.component';

describe('EmpleadoDocumentosComponent', () => {
  let component: EmpleadoDocumentosComponent;
  let fixture: ComponentFixture<EmpleadoDocumentosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ EmpleadoDocumentosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(EmpleadoDocumentosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
