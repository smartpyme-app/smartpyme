import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajasComponent } from './cajas.component';

describe('CajasComponent', () => {
  let component: CajasComponent;
  let fixture: ComponentFixture<CajasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
