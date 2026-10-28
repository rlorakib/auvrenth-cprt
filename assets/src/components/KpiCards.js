/**
 * Headline numbers for the selected period.
 */
import { __, sprintf } from '@wordpress/i18n';

import { formatMoney, formatPercent } from '../utils';

/**
 * Change against the previous period, e.g. "▲ 12.5% vs previous period".
 *
 * @param {Object}  props
 * @param {number}  props.current     This period's value.
 * @param {number}  props.previous    Previous period's value.
 * @param {boolean} props.higherIsBad Whether an increase is bad news (returns).
 */
function Change( { current, previous, higherIsBad = false } ) {
	if ( ! previous ) {
		return (
			<span className="munafa-kpi__change">
				{ __( 'No data for the previous period', 'munafa' ) }
			</span>
		);
	}

	const change = ( ( current - previous ) / Math.abs( previous ) ) * 100;
	const up = change >= 0;
	const good = higherIsBad ? ! up : up;

	return (
		<span
			className={ `munafa-kpi__change ${ good ? 'is-good' : 'is-bad' }` }
		>
			{ sprintf(
				/* translators: 1: ▲ or ▼, 2: percentage change */
				__( '%1$s %2$s vs previous period', 'munafa' ),
				up ? '▲' : '▼',
				formatPercent( Math.abs( change ) )
			) }
		</span>
	);
}

/**
 * @param {Object} props
 * @param {Object} props.current  Summary for the selected period.
 * @param {Object} props.previous Summary for the period before it.
 */
export default function KpiCards( { current, previous } ) {
	const cards = [
		{
			label: __( 'Revenue', 'munafa' ),
			value: formatMoney( current.revenue ),
			change: (
				<Change
					current={ current.revenue }
					previous={ previous.revenue }
				/>
			),
		},
		{
			label: __( 'Net profit', 'munafa' ),
			value: formatMoney( current.net_profit ),
			tone: current.net_profit < 0 ? 'is-bad' : 'is-good',
			change: (
				<Change
					current={ current.net_profit }
					previous={ previous.net_profit }
				/>
			),
		},
		{
			label: __( 'Margin', 'munafa' ),
			value: formatPercent( current.margin ),
			change: (
				<span className="munafa-kpi__change">
					{ sprintf(
						/* translators: %s: number of orders */
						__( '%s orders', 'munafa' ),
						current.orders
					) }
				</span>
			),
		},
		{
			label: __( 'Return rate', 'munafa' ),
			value: formatPercent( current.return_rate ),
			tone: current.return_rate >= 20 ? 'is-bad' : '',
			change: (
				<Change
					current={ current.return_rate }
					previous={ previous.return_rate }
					higherIsBad
				/>
			),
		},
		{
			label: __( 'Return loss', 'munafa' ),
			value: formatMoney( current.return_loss ),
			tone: current.return_loss > 0 ? 'is-bad' : '',
			change: (
				<span className="munafa-kpi__change">
					{ sprintf(
						/* translators: %s: number of returned orders */
						__( '%s returned orders', 'munafa' ),
						current.returned
					) }
				</span>
			),
		},
	];

	return (
		<div className="munafa-kpis">
			{ cards.map( ( card ) => (
				<div className="munafa-card munafa-kpi" key={ card.label }>
					<span className="munafa-kpi__label">{ card.label }</span>
					<strong
						className={ `munafa-kpi__value ${ card.tone || '' }` }
					>
						{ card.value }
					</strong>
					{ card.change }
				</div>
			) ) }
		</div>
	);
}
