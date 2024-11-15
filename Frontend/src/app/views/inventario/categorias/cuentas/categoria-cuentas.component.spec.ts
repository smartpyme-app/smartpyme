import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CategoriaCuentasComponent } from './categoria-cuentas.component';

describe('CategoriaCuentasComponent', () => {
  let component: CategoriaCuentasComponent;
  let fixture: ComponentFixture<CategoriaCuentasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CategoriaCuentasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CategoriaCuentasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
