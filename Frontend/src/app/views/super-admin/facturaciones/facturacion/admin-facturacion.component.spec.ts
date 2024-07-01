import { TestBed, async } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { FacturacionComponent } from './facturacion.component';

describe('VentaComponent', () => {
  beforeEach(async(() => {
    TestBed.configureTestingModule({
      imports: [
        RouterTestingModule
      ],
      declarations: [
        FacturacionComponent
      ],
    }).compileComponents();
  }));

  it('should create the app', () => {
    const fixture = TestBed.createComponent(FacturacionComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app).toBeTruthy();
  });

  it(`should have as title 'wproject'`, () => {
    const fixture = TestBed.createComponent(FacturacionComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app.title).toEqual('wproject');
  });

  it('should render title in a h1 tag', () => {
    const fixture = TestBed.createComponent(FacturacionComponent);
    fixture.detectChanges();
    const compiled = fixture.debugElement.nativeElement;
    expect(compiled.querySelector('h1').textContent).toContain('Welcome to wproject!');
  });
});
