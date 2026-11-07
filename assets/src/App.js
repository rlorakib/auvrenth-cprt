/**
 * Dashboard root: picks the date range and loads every report for it.
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import DateRangePicker from './components/DateRangePicker';
import KpiCards from './components/KpiCards';
import ProductsTable from './components/ProductsTable';
import ReturnsReport from './components/ReturnsReport';
import TrendChart from './components/TrendChart';
import {
	MAX_HOURLY_DAYS,
	PRODUCTS_URL,
	SETTINGS_URL,
	SETUP_DONE,
	presetRange,
	rangeDays,
} from './utils';

/**
 * GET a Auvrenth CPRT report.
 *
 * @param {string} route Route under cprt/v1, e.g. `/summary`.
 * @param {Object} query Query arguments.
 * @return {Promise<Object>} Response data.
 */
function fetchReport( route, query ) {
	return apiFetch( { path: addQueryArgs( `/cprt/v1${ route }`, query ) } );
}

export default function App() {
	const [ range, setRange ] = useState( {
		preset: '30',
		...presetRange( '30' ),
	} );
	const [ interval, setTrendInterval ] = useState( 'day' );
	const [ reports, setReports ] = useState( null );
	const [ trend, setTrend ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const { after, before } = range;
	const days = rangeDays( after, before );
	const hourlyAllowed = days <= MAX_HOURLY_DAYS;
	// Hourly stays picked but shows days while the range is too long for it.
	const trendInterval =
		'hour' === interval && ! hourlyAllowed ? 'day' : interval;

	// Picking Hourly on a long range narrows it to the last 7 days, which always fits.
	const changeInterval = ( value ) => {
		if ( 'hour' === value && ! hourlyAllowed ) {
			setRange( { preset: '7', ...presetRange( '7' ) } );
		}
		setTrendInterval( value );
	};

	useEffect( () => {
		const dates = { after, before };
		let cancelled = false;
		setLoading( true );
		setError( '' );

		Promise.all( [
			fetchReport( '/summary', dates ),
			fetchReport( '/products', { ...dates, orderby: 'profit' } ),
			fetchReport( '/products', { ...dates, orderby: 'loss' } ),
			fetchReport( '/returns', dates ),
		] )
			.then( ( [ summary, profitable, lossMaking, returns ] ) => {
				if ( ! cancelled ) {
					setReports( { summary, profitable, lossMaking, returns } );
				}
			} )
			.catch( ( err ) => ! cancelled && setError( err.message ) )
			.finally( () => ! cancelled && setLoading( false ) );

		return () => {
			cancelled = true;
		};
	}, [ after, before ] );

	useEffect( () => {
		let cancelled = false;
		fetchReport( '/trend', { after, before, interval: trendInterval } )
			.then( ( data ) => ! cancelled && setTrend( data ) )
			.catch( ( err ) => ! cancelled && setError( err.message ) );

		return () => {
			cancelled = true;
		};
	}, [ after, before, trendInterval ] );

	const missing = reports?.summary.current.missing_cost || 0;

	return (
		<div className="cprt-dashboard">
			<header className="cprt-dashboard__header">
				<h1>{ __( 'Auvrenth CPRT – Profit dashboard', 'auvrenth-cprt' ) }</h1>
				<DateRangePicker value={ range } onChange={ setRange } />
			</header>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! SETUP_DONE && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Auvrenth CPRT is not set up yet, so courier charges and fees in these numbers may be wrong.',
						'auvrenth-cprt'
					) }{ ' ' }
					<a href={ SETTINGS_URL }>
						{ __(
							'Set up courier zones and payment fees',
							'auvrenth-cprt'
						) }
					</a>
				</Notice>
			) }

			{ missing > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of orders */
						_n(
							'%d order has products without a cost price, so profit is shown higher than it really is.',
							'%d orders have products without a cost price, so profit is shown higher than it really is.',
							missing,
							'auvrenth-cprt'
						),
						missing
					) }{ ' ' }
					<a href={ PRODUCTS_URL }>
						{ __( 'Add cost prices', 'auvrenth-cprt' ) }
					</a>
				</Notice>
			) }

			{ ! reports && loading && (
				<div className="cprt-dashboard__loading">
					<Spinner />
				</div>
			) }

			{ reports && (
				<div
					className={
						loading
							? 'cprt-dashboard__body is-loading'
							: 'cprt-dashboard__body'
					}
				>
					<KpiCards
						current={ reports.summary.current }
						previous={ reports.summary.previous }
					/>
					<TrendChart
						data={ trend }
						interval={ trendInterval }
						multiDay={ days > 1 }
						onIntervalChange={ changeInterval }
					/>
					<div className="cprt-dashboard__columns">
						<ProductsTable
							profitable={ reports.profitable }
							lossMaking={ reports.lossMaking }
						/>
						<ReturnsReport { ...reports.returns } />
					</div>
					<p className="cprt-dashboard__footer">
						{ __(
							'Courier charges, fees and packaging come from your Auvrenth CPRT settings.',
							'auvrenth-cprt'
						) }{ ' ' }
						<a href={ SETTINGS_URL }>
							{ __( 'Change settings', 'auvrenth-cprt' ) }
						</a>
					</p>
				</div>
			) }
		</div>
	);
}
