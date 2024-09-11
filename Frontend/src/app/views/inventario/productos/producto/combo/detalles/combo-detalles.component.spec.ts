import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ComboDetallesComponent } from './combo-detalles.component';

describe('CompraDetallesComponent', () => {
  let component: ComboDetallesComponent;
  let fixture: ComponentFixture<ComboDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ComboDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ComboDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
