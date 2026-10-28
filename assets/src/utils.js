/**
 * Formatting and date helpers shared by the dashboard components.
 */

const settings = window.munafaSettings || {};

export const CURRENCY = settings.currency || '৳';
export const TODAY = settings.today || new Date().toISOString().slice( 0, 10 );
export const PRODUCTS_URL = settings.productsUrl || '';
export const SETTINGS_URL = settings.settingsUrl || '';
export const SETUP_DONE = Boolean( settings.setupDone );

const locale = settings.locale || undefined;

const decimals = settings.decimals ?? 2;

// Whole amounts drop the decimals ("৳1,250"); others always show all of them ("৳1,441.30").
const wholeFormat = new Intl.NumberFormat( locale, {
	maximumFractionDigits: 0,
} );
const decimalFormat = new Intl.NumberFormat( locale, {
	minimumFractionDigits: decimals,
	maximumFractionDigits: decimals,
} );

/**
 * Format an amount with the store currency, e.g. "৳1,250", "৳1,441.30" or "−৳90".
 *
 * @param {number} amount Amount.
 * @return {string} Formatted amount.
 */
export function formatMoney( amount ) {
	const value = Math.abs( Number( amount ) || 0 );
	const sign = Number( amount ) < 0 ? '−' : '';
	const format = Number.isInteger( value ) ? wholeFormat : decimalFormat;
	return `${ sign }${ CURRENCY }${ format.format( value ) }`;
}

/**
 * Format a percentage with one decimal, e.g. "12.5%".
 *
 * @param {number} value Percentage.
 * @return {string} Formatted percentage.
 */
export function formatPercent( value ) {
	return `${ ( Number( value ) || 0 ).toFixed( 1 ) }%`;
}

/**
 * Add days to a Y-m-d date. Works in UTC so the browser's timezone can't shift the day.
 *
 * @param {string} ymd  Date.
 * @param {number} days Days to add (negative to subtract).
 * @return {string} New Y-m-d date.
 */
export function addDays( ymd, days ) {
	const date = new Date( `${ ymd }T00:00:00Z` );
	date.setUTCDate( date.getUTCDate() + days );
	return date.toISOString().slice( 0, 10 );
}

/**
 * Short label for a Y-m-d date, e.g. "28 Oct".
 *
 * @param {string} ymd Date.
 * @return {string} Label.
 */
export function shortDate( ymd ) {
	return new Date( `${ ymd }T00:00:00Z` ).toLocaleDateString( locale, {
		day: 'numeric',
		month: 'short',
		timeZone: 'UTC',
	} );
}

/**
 * Turn a preset into after/before dates, relative to the site's today.
 *
 * @param {string} preset `today`, `7`, `30`, `90` or `month`.
 * @return {{after: string, before: string}} Dates.
 */
export function presetRange( preset ) {
	if ( 'today' === preset ) {
		return { after: TODAY, before: TODAY };
	}
	if ( 'month' === preset ) {
		return { after: `${ TODAY.slice( 0, 8 ) }01`, before: TODAY };
	}
	return {
		after: addDays( TODAY, -( Number( preset ) - 1 ) ),
		before: TODAY,
	};
}
