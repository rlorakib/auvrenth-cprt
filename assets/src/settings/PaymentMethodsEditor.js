/**
 * Per payment method: whether it is cash on delivery, and its fee.
 */
import { CheckboxControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   props
 * @param {Object}   props.methods  Settings keyed by gateway ID: `{ is_cod, fee_percent }`.
 * @param {Array}    props.gateways WooCommerce payment gateways.
 * @param {Function} props.onChange Called with the new settings object.
 */
export default function PaymentMethodsEditor( {
	methods,
	gateways,
	onChange,
} ) {
	if ( ! gateways.length ) {
		return (
			<p className="cprt-empty">
				{ __( 'WooCommerce has no payment methods.', 'auvrenth-cprt' ) }
			</p>
		);
	}

	const methodFor = ( id ) =>
		methods[ id ] || { is_cod: 'cod' === id, fee_percent: '' };

	const set = ( id, changes ) =>
		onChange( { ...methods, [ id ]: { ...methodFor( id ), ...changes } } );

	return (
		<div className="cprt-table-wrap">
			<table className="widefat striped cprt-table">
				<thead>
					<tr>
						<th>{ __( 'Payment method', 'auvrenth-cprt' ) }</th>
						<th>{ __( 'Cash on delivery', 'auvrenth-cprt' ) }</th>
						<th>{ __( 'Fee (%)', 'auvrenth-cprt' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ gateways.map( ( gateway ) => {
						const method = methodFor( gateway.id );
						return (
							<tr key={ gateway.id }>
								<td>
									{ gateway.title }
									{ ! gateway.enabled && (
										<span className="cprt-badge">
											{ __( 'Disabled', 'auvrenth-cprt' ) }
										</span>
									) }
								</td>
								<td>
									<CheckboxControl
										label={ __(
											'Courier collects cash',
											'auvrenth-cprt'
										) }
										checked={ method.is_cod }
										onChange={ ( isCod ) =>
											set( gateway.id, { is_cod: isCod } )
										}
										__nextHasNoMarginBottom
									/>
								</td>
								<td>
									<TextControl
										type="number"
										min="0"
										max="100"
										step="0.01"
										label={ __( 'Fee (%)', 'auvrenth-cprt' ) }
										hideLabelFromVision
										className="cprt-settings__short"
										value={ method.fee_percent }
										onChange={ ( fee ) =>
											set( gateway.id, {
												fee_percent: fee,
											} )
										}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}
