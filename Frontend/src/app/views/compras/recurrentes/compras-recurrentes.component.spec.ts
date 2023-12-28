import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ComprasRecurrentesComponent } from './compras-recurrentes.component';

describe('ComprasRecurrentesComponent', () => {
  let component: ComprasRecurrentesComponent;
  let fixture: ComponentFixture<ComprasRecurrentesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ComprasRecurrentesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ComprasRecurrentesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
