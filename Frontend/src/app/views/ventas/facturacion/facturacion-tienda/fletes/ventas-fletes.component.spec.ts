import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { VentasFletesComponent } from './ventas-fletes.component';

describe('VentasFletesComponent', () => {
  let component: VentasFletesComponent;
  let fixture: ComponentFixture<VentasFletesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ VentasFletesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(VentasFletesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
