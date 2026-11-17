export const CURRENT_YEAR = new Date().getFullYear();
export const MAX_VEHICLE_YEAR = CURRENT_YEAR + 1;
export const MIN_VEHICLE_YEAR = 1990;

export const vehicleYears: number[] = Array.from(
    { length: MAX_VEHICLE_YEAR - MIN_VEHICLE_YEAR + 1 },
    (_, index) => MAX_VEHICLE_YEAR - index,
);

export const exteriorColors = ['Blanco', 'Negro', 'Gris', 'Plata', 'Rojo', 'Azul', 'Verde', 'Café', 'Beige', 'Amarillo', 'Naranja', 'Otro'];

export const interiorColors = ['Negro', 'Gris', 'Beige', 'Café', 'Blanco', 'Rojo', 'Azul', 'Otro'];

export const invoiceTypes = [
    'Factura de agencia',
    'Factura de particular',
    'Factura de seguro',
    'Factura de seminuevo',
    'Sin factura',
];

export const legacyInvoiceTypes = ['Factura original'];

export const insuranceOptions = ['Con seguro', 'Sin seguro'];
export const legacyInsuranceOptions = ['Vigente', 'Vencido'];

export const bodyTypes = ['Sedán', 'SUV', 'Hatchback', 'Pickup', 'Coupé', 'Van'];
export const transmissions = ['Automática', 'Manual', 'CVT'];
export const fuelTypes = ['Gasolina', 'Diésel', 'Híbrido', 'Eléctrico'];

export function withLegacyOption(options: string[], current: string | null | undefined): string[] {
    if (!current || options.includes(current)) {
        return options;
    }

    return [current, ...options];
}
