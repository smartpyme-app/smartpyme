import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaCortesComponent } from './caja-cortes.component';

describe('CajaCortesComponent', () => {
  let component: CajaCortesComponent;
  let fixture: ComponentFixture<CajaCortesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaCortesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaCortesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
