/**
 * Editable list of return reasons. Each keeps its stored key when renamed.
 */
import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {Object}   props
 * @param {Array}    props.reasons  `{ key, label }` list; new reasons have an empty key.
 * @param {Function} props.onChange Called with the new list.
 */
export default function ReturnReasonsEditor( { reasons, onChange } ) {
	const setLabel = ( index, label ) =>
		onChange(
			reasons.map( ( reason, i ) =>
				i === index ? { ...reason, label } : reason
			)
		);

	return (
		<div className="munafa-reasons">
			{ reasons.map( ( reason, index ) => (
				<div
					className="munafa-reasons__row"
					key={ reason.key || `new-${ index }` }
				>
					<TextControl
						label={ sprintf(
							/* translators: %d: reason number */
							__( 'Reason %d', 'munafa' ),
							index + 1
						) }
						hideLabelFromVision
						value={ reason.label }
						onChange={ ( label ) => setLabel( index, label ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () =>
							onChange(
								reasons.filter( ( _, i ) => i !== index )
							)
						}
					>
						{ __( 'Remove', 'munafa' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [ ...reasons, { key: '', label: '' } ] )
				}
			>
				{ __( 'Add reason', 'munafa' ) }
			</Button>
		</div>
	);
}
