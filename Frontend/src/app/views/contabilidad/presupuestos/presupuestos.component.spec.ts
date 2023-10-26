import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { PresupuestosComponent } from './presupuestos.component';

describe('PresupuestosComponent', () => {
  let component: PresupuestosComponent;
  let fixture: ComponentFixture<PresupuestosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ PresupuestosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(PresupuestosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
