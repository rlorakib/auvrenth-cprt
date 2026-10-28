/**
 * Most profitable and loss making products.
 */
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { formatMoney } from '../utils';

/**
 * @param {Object} props
 * @param {Array}  props.products Rows from `/products`.
 * @param {string} props.empty    Text when there are no rows.
 */
function Table( { products, empty } ) {
	if ( ! products.length ) {
		return <p className="munafa-empty">{ empty }</p>;
	}

	return (
		<div className="munafa-table-wrap">
			<table className="widefat striped munafa-table">
				<thead>
					<tr>
						<th>{ __( 'Product', 'munafa' ) }</th>
						<th className="num">{ __( 'Sold', 'munafa' ) }</th>
						<th className="num">{ __( 'Returned', 'munafa' ) }</th>
						<th className="num">{ __( 'Revenue', 'munafa' ) }</th>
						<th className="num">
							{ __( 'Gross profit', 'munafa' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ products.map( ( product ) => (
						<tr key={ product.id }>
							<td>
								{ product.edit_url ? (
									<a href={ product.edit_url }>
										{ product.name }
									</a>
								) : (
									product.name
								) }
								{ product.missing_cost && (
									<span className="munafa-badge">
										{ __( 'No cost price', 'munafa' ) }
									</span>
								) }
							</td>
							<td className="num">{ product.qty }</td>
							<td className="num">{ product.returned }</td>
							<td className="num">
								{ formatMoney( product.revenue ) }
							</td>
							<td
								className={ `num ${
									product.profit < 0 ? 'is-bad' : ''
								}` }
							>
								{ formatMoney( product.profit ) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

/**
 * @param {Object} props
 * @param {Array}  props.profitable Most profitable products.
 * @param {Array}  props.lossMaking Products with negative gross profit.
 */
export default function ProductsTable( { profitable, lossMaking } ) {
	return (
		<section className="munafa-card">
			<div className="munafa-card__header">
				<h2>{ __( 'Products', 'munafa' ) }</h2>
			</div>
			<p className="munafa-card__note">
				{ __(
					'Gross profit is revenue minus cost price, before courier charges and fees.',
					'munafa'
				) }
			</p>
			<TabPanel
				tabs={ [
					{
						name: 'profit',
						title: __( 'Most profitable', 'munafa' ),
					},
					{ name: 'loss', title: __( 'Loss making', 'munafa' ) },
				] }
			>
				{ ( tab ) =>
					'loss' === tab.name ? (
						<Table
							products={ lossMaking }
							empty={ __(
								'No product made a loss in this period.',
								'munafa'
							) }
						/>
					) : (
						<Table
							products={ profitable }
							empty={ __( 'No sales in this period.', 'munafa' ) }
						/>
					)
				}
			</TabPanel>
		</section>
	);
}
