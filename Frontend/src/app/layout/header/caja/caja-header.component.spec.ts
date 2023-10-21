import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaHeaderComponent } from './caja-header.component';

describe('CajaHeaderComponent', () => {
  let component: CajaHeaderComponent;
  let fixture: ComponentFixture<CajaHeaderComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaHeaderComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaHeaderComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
