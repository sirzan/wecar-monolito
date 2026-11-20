export const SIMULATOR_ANNUAL_RATE = 12.5;
export const CAR_DETAIL_ANNUAL_RATE = 17.2;
export const SERVICE_FEE_RATE = 0.025;
export const DEFAULT_DOWN_PAYMENT_PCT = 20;
export const DEFAULT_TERM_MONTHS = 48;

/**
 * Cuota fija nivelada (amortización francesa).
 */
export function monthlyPayment(financedAmount: number, annualRate: number, months: number): number {
    if (financedAmount <= 0 || months <= 0) {
        return 0;
    }

    const monthlyRate = annualRate / 100 / 12;

    if (monthlyRate === 0) {
        return Math.round(financedAmount / months);
    }

    return Math.round((financedAmount * monthlyRate) / (1 - Math.pow(1 + monthlyRate, -months)));
}

export function serviceFee(financedAmount: number): number {
    return Math.round(Math.max(financedAmount, 0) * SERVICE_FEE_RATE);
}

/**
 * Estimado rápido para cards de autos: 20% de enganche, 17.2% anual, 48 meses.
 */
export function estimatedMonthlyFromPrice(price: number): number {
    const financed = Math.max(price * (1 - DEFAULT_DOWN_PAYMENT_PCT / 100), 0);

    return Math.round(monthlyPayment(financed, CAR_DETAIL_ANNUAL_RATE, DEFAULT_TERM_MONTHS) / 100) * 100;
}
