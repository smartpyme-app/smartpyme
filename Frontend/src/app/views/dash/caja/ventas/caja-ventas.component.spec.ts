import { PopoverDirective } from 'ngx-bootstrap/popover';
import { CajaVentasComponent } from './caja-ventas.component';

describe('CajaVentasComponent', () => {
    it('imports PopoverModule so [popover] is not the native HTML popover', () => {
        const raw = (CajaVentasComponent as { ɵcmp?: { dependencies?: unknown[] | (() => unknown[]) } }).ɵcmp?.dependencies;
        const deps = typeof raw === 'function' ? raw() : (raw ?? []);
        expect(deps).toContain(PopoverDirective);
    });
});
