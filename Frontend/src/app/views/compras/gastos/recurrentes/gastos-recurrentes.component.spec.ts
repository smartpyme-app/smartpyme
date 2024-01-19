import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { GastosRecurrentesComponent } from './gastos-recurrentes.component';

describe('GastosRecurrentesComponent', () => {
  let component: GastosRecurrentesComponent;
  let fixture: ComponentFixture<GastosRecurrentesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ GastosRecurrentesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(GastosRecurrentesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
