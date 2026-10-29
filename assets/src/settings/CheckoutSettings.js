/**
 * Simple checkout tab: field rules and phone number patterns.
 */
import {
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Code points of "0" in native digit sets, as in PHP's PhoneMask::DIGIT_ZEROS.
const DIGIT_ZEROS = [
	0x0660, 0x06f0, 0x0966, 0x09e6, 0x0a66, 0x0ae6, 0x0b66, 0x0be6, 0x0c66,
	0x0ce6, 0x0d66, 0x0de6, 0x1040,
];

/**
 * Same check as PhoneMask::matches() in PHP, for trying patterns out.
 *
 * @param {string}   phone    Phone number as typed.
 * @param {string[]} patterns Patterns, `#` for any digit.
 * @return {boolean} Whether the number fits a pattern.
 */
function phoneMatches( phone, patterns ) {
	const normalized = phone
		.trim()
		.replace( /\p{Nd}/gu, ( char ) => {
			const code = char.codePointAt( 0 );
			const zero = DIGIT_ZEROS.find(
				( start ) => code >= start && code <= start + 9
			);
			return undefined === zero ? char : String( code - zero );
		} )
		.replace( /[\s\-.()]+/g, '' );

	return patterns.some( ( pattern ) => {
		const regex = pattern
			.split( '' )
			.map( ( char ) =>
				'#' === char ? '[0-9]' : char.replace( /[+]/, '\\+' )
			)
			.join( '' );
		return new RegExp( `^${ regex }$` ).test( normalized );
	} );
}

/**
 * @param {Object}   props
 * @param {Object}   props.settings `{ fields, phone_patterns }`.
 * @param {Object}   props.choices  `{ phone_field }`, WooCommerce's Phone setting.
 * @param {Function} props.onChange Called with the new settings.
 */
export default function CheckoutSettings( { settings, choices, onChange } ) {
	const [ testNumber, setTestNumber ] = useState( '' );

	const fields = [
		{ key: 'last_name', label: __( 'Last name', 'munafa' ) },
		{ key: 'company', label: __( 'Company', 'munafa' ) },
		{
			key: 'address_2',
			label: __( 'Apartment, suite, etc. (address line 2)', 'munafa' ),
		},
		{ key: 'city', label: __( 'Town / City', 'munafa' ) },
		{ key: 'postcode', label: __( 'Postcode', 'munafa' ) },
	];
	const rules = [
		{ value: 'default', label: __( 'As WooCommerce has it', 'munafa' ) },
		{ value: 'required', label: __( 'Required', 'munafa' ) },
		{ value: 'optional', label: __( 'Optional', 'munafa' ) },
		{ value: 'hidden', label: __( 'Hidden', 'munafa' ) },
	];

	const patterns = settings.phone_patterns || [];
	const filled = patterns.map( ( p ) => p.trim() ).filter( Boolean );

	const setField = ( key, rule ) =>
		onChange( {
			...settings,
			fields: { ...settings.fields, [ key ]: rule },
		} );

	return (
		<>
			<section className="munafa-card munafa-settings__section">
				<div className="munafa-card__header">
					<h2>{ __( 'Checkout fields', 'munafa' ) }</h2>
				</div>
				<p className="munafa-card__note">
					{ __(
						'Fewer fields mean more finished orders. First name, address, state/district, country and phone always stay. Works with the Checkout block and the classic checkout.',
						'munafa'
					) }
				</p>
				<div className="munafa-checkout-fields">
					{ fields.map( ( field ) => (
						<SelectControl
							key={ field.key }
							label={ field.label }
							value={
								settings.fields?.[ field.key ] || 'default'
							}
							options={ rules }
							onChange={ ( rule ) => setField( field.key, rule ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) ) }
				</div>
			</section>

			<section className="munafa-card munafa-settings__section">
				<div className="munafa-card__header">
					<h2>{ __( 'Phone number check', 'munafa' ) }</h2>
				</div>
				<p className="munafa-card__note">
					{ __(
						'The courier calls this number, so a wrong one means a returned parcel. Orders whose phone fits none of these patterns are stopped at checkout. Leave empty to accept any number.',
						'munafa'
					) }
				</p>

				{ 'required' !== choices.phone_field && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Customers can still leave the phone empty. Make Phone required in the Checkout block settings (classic checkout: Customize → WooCommerce → Checkout).',
							'munafa'
						) }
					</Notice>
				) }

				<TextareaControl
					label={ __( 'Accepted patterns, one per line', 'munafa' ) }
					help={ __(
						'# is any digit; everything else must match exactly. For example, 01######### accepts 11-digit numbers starting with 01, and +8801######### the same number with the country code. Spaces, dashes and Bangla or Hindi digits are fine.',
						'munafa'
					) }
					value={ patterns.join( '\n' ) }
					onChange={ ( text ) =>
						onChange( {
							...settings,
							phone_patterns: text.split( '\n' ),
						} )
					}
					rows={ 4 }
					className="munafa-settings__patterns"
					__nextHasNoMarginBottom
				/>

				{ filled.length > 0 && (
					<div className="munafa-settings__short munafa-phone-test">
						<TextControl
							label={ __( 'Try a number', 'munafa' ) }
							type="tel"
							value={ testNumber }
							onChange={ setTestNumber }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						{ '' !== testNumber.trim() &&
							( phoneMatches( testNumber, filled ) ? (
								<p className="is-good">
									{ __( 'Accepted', 'munafa' ) }
								</p>
							) : (
								<p className="is-bad">
									{ __( 'Rejected', 'munafa' ) }
								</p>
							) ) }
					</div>
				) }
			</section>
		</>
	);
}
