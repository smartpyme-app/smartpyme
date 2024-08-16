import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BodegasComponent } from './bodegas.component';

describe('BodegasComponent', () => {
  let component: BodegasComponent;
  let fixture: ComponentFixture<BodegasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BodegasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BodegasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
