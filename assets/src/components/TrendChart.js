/**
 * Revenue (bars) and net profit (line) over time.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	Bar,
	CartesianGrid,
	ComposedChart,
	Legend,
	Line,
	ReferenceLine,
	ResponsiveContainer,
	Tooltip,
	XAxis,
	YAxis,
} from 'recharts';

import { CURRENCY, formatMoney, shortDate } from '../utils';

const REVENUE_COLOR = '#72aee6';
const PROFIT_COLOR = '#008a20';

/**
 * Compact axis label, e.g. "৳12k".
 *
 * @param {number} value Amount.
 * @return {string} Label.
 */
function axisMoney( value ) {
	const abs = Math.abs( value );
	const sign = value < 0 ? '−' : '';
	if ( abs >= 1000 ) {
		return `${ sign }${ CURRENCY }${ Math.round( abs / 100 ) / 10 }k`;
	}
	return `${ sign }${ CURRENCY }${ abs }`;
}

/**
 * @param {Object}   props
 * @param {Array}    props.data             Trend rows from `/trend`.
 * @param {string}   props.interval         `day` or `week`.
 * @param {Function} props.onIntervalChange Called with `day` or `week`.
 */
export default function TrendChart( { data, interval, onIntervalChange } ) {
	const intervals = [
		{ value: 'day', label: __( 'Daily', 'munafa' ) },
		{ value: 'week', label: __( 'Weekly', 'munafa' ) },
	];

	return (
		<section className="munafa-card munafa-trend">
			<div className="munafa-card__header">
				<h2>{ __( 'Revenue and profit', 'munafa' ) }</h2>
				<div className="munafa-toggle">
					{ intervals.map( ( option ) => (
						<Button
							key={ option.value }
							size="compact"
							variant={
								interval === option.value
									? 'primary'
									: 'secondary'
							}
							onClick={ () => onIntervalChange( option.value ) }
							aria-pressed={ interval === option.value }
						>
							{ option.label }
						</Button>
					) ) }
				</div>
			</div>
			<div className="munafa-trend__chart">
				<ResponsiveContainer width="100%" height={ 300 }>
					<ComposedChart
						data={ data }
						margin={ { top: 8, right: 8, bottom: 0, left: 0 } }
					>
						<CartesianGrid stroke="#f0f0f1" vertical={ false } />
						<XAxis
							dataKey="date"
							tickFormatter={ shortDate }
							tick={ { fontSize: 12 } }
							minTickGap={ 16 }
						/>
						<YAxis
							tickFormatter={ axisMoney }
							tick={ { fontSize: 12 } }
							width={ 64 }
						/>
						<Tooltip
							labelFormatter={ shortDate }
							formatter={ ( value, name ) => [
								formatMoney( value ),
								name,
							] }
						/>
						<Legend />
						<ReferenceLine y={ 0 } stroke="#8c8f94" />
						<Bar
							dataKey="revenue"
							name={ __( 'Revenue', 'munafa' ) }
							fill={ REVENUE_COLOR }
							radius={ [ 3, 3, 0, 0 ] }
						/>
						<Line
							dataKey="net_profit"
							name={ __( 'Net profit', 'munafa' ) }
							stroke={ PROFIT_COLOR }
							strokeWidth={ 2 }
							dot={ false }
							type="monotone"
						/>
					</ComposedChart>
				</ResponsiveContainer>
			</div>
		</section>
	);
}
