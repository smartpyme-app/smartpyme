import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { RequisicionesComprasComponent } from './requisiciones-compras.component';

describe('RequisicionesComprasComponent', () => {
  let component: RequisicionesComprasComponent;
  let fixture: ComponentFixture<RequisicionesComprasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ RequisicionesComprasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(RequisicionesComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
