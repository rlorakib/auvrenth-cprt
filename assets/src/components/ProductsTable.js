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
		return <p className="cprt-empty">{ empty }</p>;
	}

	return (
		<div className="cprt-table-wrap">
			<table className="widefat striped cprt-table">
				<thead>
					<tr>
						<th>{ __( 'Product', 'auvrenth-cprt' ) }</th>
						<th className="num">{ __( 'Sold', 'auvrenth-cprt' ) }</th>
						<th className="num">{ __( 'Returned', 'auvrenth-cprt' ) }</th>
						<th className="num">{ __( 'Revenue', 'auvrenth-cprt' ) }</th>
						<th className="num">
							{ __( 'Gross profit', 'auvrenth-cprt' ) }
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
									<span className="cprt-badge">
										{ __( 'No cost price', 'auvrenth-cprt' ) }
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
		<section className="cprt-card">
			<div className="cprt-card__header">
				<h2>{ __( 'Products', 'auvrenth-cprt' ) }</h2>
			</div>
			<p className="cprt-card__note">
				{ __(
					'Gross profit is revenue minus cost price, before courier charges and fees.',
					'auvrenth-cprt'
				) }
			</p>
			<TabPanel
				tabs={ [
					{
						name: 'profit',
						title: __( 'Most profitable', 'auvrenth-cprt' ),
					},
					{ name: 'loss', title: __( 'Loss making', 'auvrenth-cprt' ) },
				] }
			>
				{ ( tab ) =>
					'loss' === tab.name ? (
						<Table
							products={ lossMaking }
							empty={ __(
								'No product made a loss in this period.',
								'auvrenth-cprt'
							) }
						/>
					) : (
						<Table
							products={ profitable }
							empty={ __( 'No sales in this period.', 'auvrenth-cprt' ) }
						/>
					)
				}
			</TabPanel>
		</section>
	);
}
