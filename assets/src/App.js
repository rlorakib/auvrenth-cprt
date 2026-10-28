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
import { PRODUCTS_URL, SETTINGS_URL, SETUP_DONE, presetRange } from './utils';

/**
 * GET a Munafa report.
 *
 * @param {string} route Route under munafa/v1, e.g. `/summary`.
 * @param {Object} query Query arguments.
 * @return {Promise<Object>} Response data.
 */
function fetchReport( route, query ) {
	return apiFetch( { path: addQueryArgs( `/munafa/v1${ route }`, query ) } );
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
		fetchReport( '/trend', { after, before, interval } )
			.then( ( data ) => ! cancelled && setTrend( data ) )
			.catch( ( err ) => ! cancelled && setError( err.message ) );

		return () => {
			cancelled = true;
		};
	}, [ after, before, interval ] );

	const missing = reports?.summary.current.missing_cost || 0;

	return (
		<div className="munafa-dashboard">
			<header className="munafa-dashboard__header">
				<h1>{ __( 'Munafa – Profit dashboard', 'munafa' ) }</h1>
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
						'Munafa is not set up yet, so courier charges and fees in these numbers may be wrong.',
						'munafa'
					) }{ ' ' }
					<a href={ SETTINGS_URL }>
						{ __(
							'Set up courier zones and payment fees',
							'munafa'
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
							'munafa'
						),
						missing
					) }{ ' ' }
					<a href={ PRODUCTS_URL }>
						{ __( 'Add cost prices', 'munafa' ) }
					</a>
				</Notice>
			) }

			{ ! reports && loading && (
				<div className="munafa-dashboard__loading">
					<Spinner />
				</div>
			) }

			{ reports && (
				<div
					className={
						loading
							? 'munafa-dashboard__body is-loading'
							: 'munafa-dashboard__body'
					}
				>
					<KpiCards
						current={ reports.summary.current }
						previous={ reports.summary.previous }
					/>
					<TrendChart
						data={ trend }
						interval={ interval }
						onIntervalChange={ setTrendInterval }
					/>
					<div className="munafa-dashboard__columns">
						<ProductsTable
							profitable={ reports.profitable }
							lossMaking={ reports.lossMaking }
						/>
						<ReturnsReport { ...reports.returns } />
					</div>
					<p className="munafa-dashboard__footer">
						{ __(
							'Courier charges, fees and packaging come from your Munafa settings.',
							'munafa'
						) }{ ' ' }
						<a href={ SETTINGS_URL }>
							{ __( 'Change settings', 'munafa' ) }
						</a>
					</p>
				</div>
			) }
		</div>
	);
}
