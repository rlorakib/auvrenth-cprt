/**
 * Where parcels come back from, and why.
 */
import { __ } from '@wordpress/i18n';

import { formatMoney, formatPercent } from '../utils';

/**
 * @param {Object} props
 * @param {Array}  props.districts Rows per district from `/returns`.
 * @param {Array}  props.reasons   Rows per reason from `/returns`.
 */
export default function ReturnsReport( { districts, reasons } ) {
	const withReturns = districts
		.filter( ( district ) => district.returned > 0 )
		.sort(
			( a, b ) => b.return_rate - a.return_rate || b.returned - a.returned
		)
		.slice( 0, 10 );
	const totalReturns = reasons.reduce(
		( sum, reason ) => sum + reason.returned,
		0
	);

	return (
		<section className="munafa-card">
			<div className="munafa-card__header">
				<h2>{ __( 'Returns', 'munafa' ) }</h2>
			</div>

			{ ! totalReturns ? (
				<p className="munafa-empty">
					{ __( 'No returned orders in this period.', 'munafa' ) }
				</p>
			) : (
				<>
					<h3>{ __( 'Why parcels came back', 'munafa' ) }</h3>
					<ul className="munafa-bars">
						{ reasons.map( ( reason ) => (
							<li key={ reason.reason || 'none' }>
								<div className="munafa-bars__label">
									<span>{ reason.name }</span>
									<span>
										{ reason.returned } ·{ ' ' }
										{ formatMoney( -reason.loss ) }
									</span>
								</div>
								<div className="munafa-bars__track">
									<div
										className="munafa-bars__fill"
										style={ {
											width: `${
												( reason.returned /
													totalReturns ) *
												100
											}%`,
										} }
									/>
								</div>
							</li>
						) ) }
					</ul>

					<h3>
						{ __( 'Districts with the most returns', 'munafa' ) }
					</h3>
					<div className="munafa-table-wrap">
						<table className="widefat striped munafa-table">
							<thead>
								<tr>
									<th>{ __( 'District', 'munafa' ) }</th>
									<th className="num">
										{ __( 'Orders', 'munafa' ) }
									</th>
									<th className="num">
										{ __( 'Returned', 'munafa' ) }
									</th>
									<th className="num">
										{ __( 'Return rate', 'munafa' ) }
									</th>
									<th className="num">
										{ __( 'Loss', 'munafa' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ withReturns.map( ( district ) => (
									<tr key={ district.state || 'unknown' }>
										<td>{ district.name }</td>
										<td className="num">
											{ district.orders }
										</td>
										<td className="num">
											{ district.returned }
										</td>
										<td
											className={ `num ${
												district.return_rate >= 20
													? 'is-bad'
													: ''
											}` }
										>
											{ formatPercent(
												district.return_rate
											) }
										</td>
										<td className="num is-bad">
											{ formatMoney( -district.loss ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</>
			) }
		</section>
	);
}
