import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CajaDashComponent } from './caja-dash.component';

describe('CajaDashComponent', () => {
  let component: CajaDashComponent;
  let fixture: ComponentFixture<CajaDashComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CajaDashComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CajaDashComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
