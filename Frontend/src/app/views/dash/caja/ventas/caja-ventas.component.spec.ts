import { PopoverDirective } from 'ngx-bootstrap/popover';
import { TemplateRef } from '@angular/core';
import { CajaVentasComponent } from './caja-ventas.component';

describe('CajaVentasComponent', () => {
    it('imports PopoverModule so [popover] is not the native HTML popover', () => {
        const raw = (CajaVentasComponent as { ɵcmp?: { dependencies?: unknown[] | (() => unknown[]) } }).ɵcmp?.dependencies;
        const deps = typeof raw === 'function' ? raw() : (raw ?? []);
        expect(deps).toContain(PopoverDirective);
    });

    it('openModal conserva el id de la venta (no resetea a item vacío de BaseCrud)', () => {
        const component = Object.create(CajaVentasComponent.prototype) as CajaVentasComponent;
        component.venta = {};
        component.modalManager = { openModal: jasmine.createSpy('openModal') };
        const template = {} as TemplateRef<unknown>;
        const venta = { id: 42, total: 100 };

        component.openModal(template, venta);

        expect(component.venta.id).toBe(42);
        expect(component.modalManager.openModal).toHaveBeenCalledWith(template, undefined);
    });
});
