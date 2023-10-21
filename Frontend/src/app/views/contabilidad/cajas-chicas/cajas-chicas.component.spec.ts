import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajasChicasComponent } from './cajas-chicas.component';

describe('CajasChicasComponent', () => {
  let component: CajasChicasComponent;
  let fixture: ComponentFixture<CajasChicasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajasChicasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajasChicasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
