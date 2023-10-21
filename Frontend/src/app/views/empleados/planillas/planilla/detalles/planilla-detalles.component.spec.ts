import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { PlanillaDetallesComponent } from './planilla-detalles.component';

describe('PlanillaDetallesComponent', () => {
  let component: PlanillaDetallesComponent;
  let fixture: ComponentFixture<PlanillaDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ PlanillaDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(PlanillaDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
