import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CodigoBarraComponent } from './codigo-barra.component';

describe('CodigoBarraComponent', () => {
  let component: CodigoBarraComponent;
  let fixture: ComponentFixture<CodigoBarraComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CodigoBarraComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CodigoBarraComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
