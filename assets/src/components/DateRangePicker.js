/**
 * Date range selector: presets plus a custom from/to range.
 */
import { SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TODAY, presetRange } from '../utils';

const PRESETS = [
	{ value: 'today', label: __( 'Today', 'munafa' ) },
	{ value: '7', label: __( 'Last 7 days', 'munafa' ) },
	{ value: '30', label: __( 'Last 30 days', 'munafa' ) },
	{ value: '90', label: __( 'Last 90 days', 'munafa' ) },
	{ value: 'month', label: __( 'This month', 'munafa' ) },
	{ value: 'custom', label: __( 'Custom range', 'munafa' ) },
];

/**
 * @param {Object}   props
 * @param {Object}   props.value    `{ preset, after, before }`.
 * @param {Function} props.onChange Called with the new value.
 */
export default function DateRangePicker( { value, onChange } ) {
	const choosePreset = ( preset ) => {
		onChange(
			'custom' === preset
				? { ...value, preset }
				: { preset, ...presetRange( preset ) }
		);
	};

	const setDate = ( key, date ) => {
		if ( /^\d{4}-\d{2}-\d{2}$/.test( date ) ) {
			onChange( { ...value, [ key ]: date } );
		}
	};

	return (
		<div className="munafa-date-range">
			<SelectControl
				label={ __( 'Period', 'munafa' ) }
				hideLabelFromVision
				value={ value.preset }
				options={ PRESETS }
				onChange={ choosePreset }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			{ 'custom' === value.preset && (
				<>
					<TextControl
						type="date"
						label={ __( 'From', 'munafa' ) }
						value={ value.after }
						max={ value.before }
						onChange={ ( date ) => setDate( 'after', date ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						type="date"
						label={ __( 'To', 'munafa' ) }
						value={ value.before }
						min={ value.after }
						max={ TODAY }
						onChange={ ( date ) => setDate( 'before', date ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</>
			) }
		</div>
	);
}
