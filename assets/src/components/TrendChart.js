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

import { CURRENCY, formatMoney, shortDate, shortHour } from '../utils';

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
 * @param {string}   props.interval         `hour`, `day` or `week`.
 * @param {boolean}  props.multiDay         Whether the range covers more than one day.
 * @param {Function} props.onIntervalChange Called with `hour`, `day` or `week`.
 */
export default function TrendChart( {
	data,
	interval,
	multiDay,
	onIntervalChange,
} ) {
	const intervals = [
		{ value: 'hour', label: __( 'Hourly', 'auvrenth-cprt' ) },
		{ value: 'day', label: __( 'Daily', 'auvrenth-cprt' ) },
		{ value: 'week', label: __( 'Weekly', 'auvrenth-cprt' ) },
	];

	// Hourly rows are "Y-m-d H:00", the others "Y-m-d". Going by the row itself keeps
	// labels right while the data for a newly picked interval is still loading.
	const axisLabel = ( key ) =>
		String( key ).includes( ' ' )
			? shortHour( key, multiDay )
			: shortDate( key );
	const tooltipLabel = ( key ) =>
		String( key ).includes( ' ' )
			? shortHour( key, true )
			: shortDate( key );

	return (
		<section className="cprt-card cprt-trend">
			<div className="cprt-card__header">
				<h2>{ __( 'Revenue and profit', 'auvrenth-cprt' ) }</h2>
				<div className="cprt-toggle">
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
			<div className="cprt-trend__chart">
				<ResponsiveContainer width="100%" height={ 300 }>
					<ComposedChart
						data={ data }
						margin={ { top: 8, right: 8, bottom: 0, left: 0 } }
					>
						<CartesianGrid stroke="#f0f0f1" vertical={ false } />
						<XAxis
							dataKey="date"
							tickFormatter={ axisLabel }
							tick={ { fontSize: 12 } }
							minTickGap={ 16 }
						/>
						<YAxis
							tickFormatter={ axisMoney }
							tick={ { fontSize: 12 } }
							width={ 64 }
						/>
						<Tooltip
							labelFormatter={ tooltipLabel }
							formatter={ ( value, name ) => [
								formatMoney( value ),
								name,
							] }
						/>
						<Legend />
						<ReferenceLine y={ 0 } stroke="#8c8f94" />
						<Bar
							dataKey="revenue"
							name={ __( 'Revenue', 'auvrenth-cprt' ) }
							fill={ REVENUE_COLOR }
							radius={ [ 3, 3, 0, 0 ] }
						/>
						<Line
							dataKey="net_profit"
							name={ __( 'Net profit', 'auvrenth-cprt' ) }
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
