import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoComboComponent } from './producto-combo.component';

describe('ProductoInformacionComponent', () => {
  let component: ProductoComboComponent;
  let fixture: ComponentFixture<ProductoComboComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoComboComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoComboComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
