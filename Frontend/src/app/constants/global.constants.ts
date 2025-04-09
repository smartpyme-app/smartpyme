export class GlobalConstants {
    private static constants: any;

    static initialize(constants: any) {
        this.constants = constants;
    }

    static get planilla() {
        return this.constants?.planilla || {};
    }

}