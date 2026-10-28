/**
 * Courier zones: name, delivery areas, delivery charge and return charge.
 */
import { Button, FormTokenField, TextControl } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { CURRENCY } from '../utils';

/**
 * Area codes (`BD`, `BD:BD-13`) with readable labels, both ways.
 *
 * @param {Array} countries Countries with their states, from the settings API.
 * @return {{labels: Map, codes: Map}} Code → label and label → code.
 */
function buildAreas( countries ) {
	const labels = new Map();
	const codes = new Map();
	const add = ( code, label ) => {
		labels.set( code, label );
		codes.set( label, code );
	};

	countries.forEach( ( country ) => {
		add(
			country.code,
			sprintf(
				/* translators: %s: country name */
				__( '%s (all areas)', 'munafa' ),
				country.name
			)
		);
		country.states.forEach( ( state ) =>
			add(
				`${ country.code }:${ state.code }`,
				sprintf(
					/* translators: 1: state or district, 2: country */
					__( '%1$s, %2$s', 'munafa' ),
					state.name,
					country.name
				)
			)
		);
	} );

	return { labels, codes };
}

/**
 * Delivery charge and return charge inputs.
 *
 * @param {Object}   props
 * @param {Object}   props.zone     Zone (or default zone).
 * @param {Function} props.onChange Called with the changed zone.
 */
function ChargeFields( { zone, onChange } ) {
	return (
		<div className="munafa-zone__charges">
			<TextControl
				type="number"
				min="0"
				step="0.01"
				label={ sprintf(
					/* translators: %s: currency symbol */
					__( 'Delivery charge (%s)', 'munafa' ),
					CURRENCY
				) }
				value={ zone.delivery_charge }
				onChange={ ( value ) =>
					onChange( { ...zone, delivery_charge: value } )
				}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<TextControl
				type="number"
				min="0"
				max="200"
				step="0.01"
				label={ __( 'Return charge (% of delivery charge)', 'munafa' ) }
				help={ __(
					'What the courier charges to bring a parcel back.',
					'munafa'
				) }
				value={ zone.return_percent }
				onChange={ ( value ) =>
					onChange( { ...zone, return_percent: value } )
				}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		</div>
	);
}

/**
 * @param {Object}   props
 * @param {Array}    props.zones           Zones in matching order.
 * @param {Object}   props.defaultZone     Charges for "Everywhere else".
 * @param {Array}    props.countries       Selling countries with states.
 * @param {Function} props.onChange        Called with the new zone list.
 * @param {Function} props.onDefaultChange Called with the new default charges.
 */
export default function ZonesEditor( {
	zones,
	defaultZone,
	countries,
	onChange,
	onDefaultChange,
} ) {
	const areas = useMemo( () => buildAreas( countries ), [ countries ] );
	const suggestions = useMemo( () => [ ...areas.codes.keys() ], [ areas ] );

	const setZone = ( index, zone ) =>
		onChange(
			zones.map( ( current, i ) => ( i === index ? zone : current ) )
		);

	const move = ( index, offset ) => {
		const next = [ ...zones ];
		const [ zone ] = next.splice( index, 1 );
		next.splice( index + offset, 0, zone );
		onChange( next );
	};

	const add = () =>
		onChange( [
			...zones,
			{
				id: '',
				name: '',
				areas: [],
				delivery_charge: '',
				return_percent: '',
			},
		] );

	return (
		<div className="munafa-zones">
			{ ! zones.length && (
				<p className="munafa-empty">
					{ __(
						'No zones yet. Every order uses "Everywhere else" until you add one.',
						'munafa'
					) }
				</p>
			) }

			{ zones.map( ( zone, index ) => (
				<div
					className="munafa-zone"
					key={ zone.id || `new-${ index }` }
				>
					<div className="munafa-zone__header">
						<strong>
							{ sprintf(
								/* translators: %d: zone number */
								__( 'Zone %d', 'munafa' ),
								index + 1
							) }
						</strong>
						<div className="munafa-zone__tools">
							<Button
								size="small"
								variant="tertiary"
								disabled={ 0 === index }
								onClick={ () => move( index, -1 ) }
							>
								{ __( 'Move up', 'munafa' ) }
							</Button>
							<Button
								size="small"
								variant="tertiary"
								disabled={ index === zones.length - 1 }
								onClick={ () => move( index, 1 ) }
							>
								{ __( 'Move down', 'munafa' ) }
							</Button>
							<Button
								size="small"
								variant="tertiary"
								isDestructive
								onClick={ () =>
									onChange(
										zones.filter( ( _, i ) => i !== index )
									)
								}
							>
								{ __( 'Remove', 'munafa' ) }
							</Button>
						</div>
					</div>

					<TextControl
						label={ __( 'Zone name', 'munafa' ) }
						placeholder={ __( 'e.g. Inside city', 'munafa' ) }
						value={ zone.name }
						onChange={ ( name ) =>
							setZone( index, { ...zone, name } )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>

					<FormTokenField
						label={ __( 'Delivery areas', 'munafa' ) }
						value={ zone.areas.map(
							( code ) => areas.labels.get( code ) || code
						) }
						suggestions={ suggestions }
						maxSuggestions={ 20 }
						__experimentalExpandOnFocus
						__experimentalValidateInput={ ( label ) =>
							areas.codes.has( label )
						}
						onChange={ ( labels ) =>
							setZone( index, {
								...zone,
								areas: labels
									.map( ( label ) =>
										areas.codes.get(
											'string' === typeof label
												? label
												: label.value
										)
									)
									.filter( Boolean ),
							} )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>

					<ChargeFields
						zone={ zone }
						onChange={ ( changed ) => setZone( index, changed ) }
					/>
				</div>
			) ) }

			<Button variant="secondary" onClick={ add }>
				{ __( 'Add zone', 'munafa' ) }
			</Button>

			<div className="munafa-zone munafa-zone--default">
				<div className="munafa-zone__header">
					<strong>{ __( 'Everywhere else', 'munafa' ) }</strong>
				</div>
				<p className="munafa-card__note">
					{ __(
						'Used for addresses no zone above covers.',
						'munafa'
					) }
				</p>
				<ChargeFields
					zone={ defaultZone }
					onChange={ onDefaultChange }
				/>
			</div>
		</div>
	);
}
