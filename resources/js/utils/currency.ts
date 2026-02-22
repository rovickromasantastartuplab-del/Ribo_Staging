/**
 * Centralized currency formatting utility.
 * 
 * All files should import from here instead of calling 
 * window.appSettings.formatCurrency directly with hardcoded $ fallbacks.
 */

/**
 * Formats a number as currency using the app's configured currency settings.
 * Never hardcodes a specific currency symbol — falls back to reading from
 * window.appSettings.currencySettings if the formatCurrency method isn't loaded yet.
 */
export function formatCurrency(amount: number | string): string {
    if (window.appSettings?.formatCurrency) {
        return window.appSettings.formatCurrency(amount);
    }

    // Fallback: read symbol from currencySettings (never hardcoded)
    const symbol = window.appSettings?.currencySettings?.currencySymbol || '';
    const num = typeof amount === 'string' ? parseFloat(amount) : (amount || 0);

    if (isNaN(num)) return `${symbol}0.00`;

    return `${symbol}${num.toFixed(2)}`;
}

/**
 * Returns the current currency symbol from settings.
 * Use this when you need just the symbol (e.g., for JSX interpolation).
 */
export function getCurrencySymbol(): string {
    return window.appSettings?.currencySettings?.currencySymbol || '';
}
