/**
 * Settings screen, one tab per area: profit settings, modules, each module that
 * is on, and uninstall. One Save button saves every tab.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
	TabPanel,
	TextControl,
} from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { CURRENCY } from '../utils';
import CheckoutSettings from './CheckoutSettings';
import ModulesPanel from './ModulesPanel';
import PaymentMethodsEditor from './PaymentMethodsEditor';
import ReturnReasonsEditor from './ReturnReasonsEditor';
import ZonesEditor from './ZonesEditor';

/**
 * Settings tab of each module, keyed by module ID.
 *
 * Add-ons add theirs with the `cprt.settings.modulePanels` filter from `@wordpress/hooks`.
 * A panel gets `settings`, `choices` and `onChange`, like CheckoutSettings.
 *
 * @return {Object} Components keyed by module ID.
 */
function modulePanels() {
	return applyFilters( 'cprt.settings.modulePanels', {
		checkout: CheckoutSettings,
	} );
}

/**
 * A card with a title and an explanation.
 *
 * @param {Object}  props
 * @param {string}  props.title       Title.
 * @param {string}  props.description What the section is for.
 * @param {Element} props.children    Fields.
 */
function Section( { title, description, children } ) {
	return (
		<section className="cprt-card cprt-settings__section">
			<div className="cprt-card__header">
				<h2>{ title }</h2>
			</div>
			{ description && (
				<p className="cprt-card__note">{ description }</p>
			) }
			{ children }
		</section>
	);
}

export default function SettingsApp() {
	const [ data, setData ] = useState( null );
	const [ settings, setSettings ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ recalculating, setRecalculating ] = useState( false );
	// TabPanel picks its own tab; to open one from code it is remounted on that tab.
	const [ openTab, setOpenTab ] = useState( { name: 'general', key: 0 } );

	useEffect( () => {
		apiFetch( { path: '/cprt/v1/settings' } )
			.then( ( response ) => {
				setData( response );
				setSettings( response.settings );
				setRecalculating( response.recalculating );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			);
	}, [] );

	if ( ! settings ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : (
			<div className="cprt-dashboard__loading">
				<Spinner />
			</div>
		);
	}

	const update = ( key ) => ( value ) =>
		setSettings( { ...settings, [ key ]: value } );

	const updateModule = ( id, changes ) =>
		setSettings( {
			...settings,
			modules: {
				...settings.modules,
				[ id ]: { ...settings.modules[ id ], ...changes },
			},
		} );

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/cprt/v1/settings',
			method: 'POST',
			data: settings,
		} )
			.then( ( response ) => {
				setSettings( response.settings );
				setNotice( {
					status: 'success',
					message: __(
						'Settings saved. Recalculate your orders so old orders use them too.',
						'auvrenth-cprt'
					),
					recalculate: true,
				} );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			)
			.finally( () => setSaving( false ) );
	};

	const recalculate = () => {
		apiFetch( { path: '/cprt/v1/recalculate', method: 'POST' } )
			.then( () => {
				setRecalculating( true );
				setNotice( {
					status: 'info',
					message: __(
						'Recalculating all orders in the background. This can take a few minutes on big stores.',
						'auvrenth-cprt'
					),
				} );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			);
	};

	const toggleStatus = ( key, checked ) =>
		update( 'report_statuses' )(
			checked
				? [ ...settings.report_statuses, key ]
				: settings.report_statuses.filter(
						( status ) => status !== key
				  )
		);

	const panels = modulePanels();
	const moduleTabs = data.choices.modules
		.filter(
			( module ) =>
				settings.modules[ module.id ]?.enabled && panels[ module.id ]
		)
		.map( ( module ) => ( { name: module.id, title: module.name } ) );

	const tabs = [
		{ name: 'general', title: __( 'Profit', 'auvrenth-cprt' ) },
		...moduleTabs,
		{ name: 'modules', title: __( 'Modules', 'auvrenth-cprt' ) },
		{ name: 'advanced', title: __( 'Advanced', 'auvrenth-cprt' ) },
	];

	const renderGeneral = () => (
		<>
			<Section
				title={ __( 'Courier zones', 'auvrenth-cprt' ) }
				description={ __(
					'What your courier charges you per parcel, by delivery area. Each order uses the first zone that covers its address, or "Everywhere else".',
					'auvrenth-cprt'
				) }
			>
				<ZonesEditor
					zones={ settings.zones }
					defaultZone={ settings.default_zone }
					countries={ data.choices.countries }
					onChange={ update( 'zones' ) }
					onDefaultChange={ update( 'default_zone' ) }
				/>
			</Section>

			<Section
				title={ __( 'Payment methods', 'auvrenth-cprt' ) }
				description={ __(
					"Tick the methods where the courier collects cash. Their fee is the courier's COD charge; for the others it is the payment gateway fee.",
					'auvrenth-cprt'
				) }
			>
				<PaymentMethodsEditor
					methods={ settings.payment_methods }
					gateways={ data.choices.gateways }
					onChange={ update( 'payment_methods' ) }
				/>
			</Section>

			<Section
				title={ __( 'Packaging', 'auvrenth-cprt' ) }
				description={ __(
					'Box, poly bag, tape and printing for one parcel. Counted on delivered and returned orders.',
					'auvrenth-cprt'
				) }
			>
				<TextControl
					type="number"
					min="0"
					step="0.01"
					className="cprt-settings__short"
					label={ sprintf(
						/* translators: %s: currency symbol */
						__( 'Packaging cost per order (%s)', 'auvrenth-cprt' ),
						CURRENCY
					) }
					value={ settings.packaging_cost }
					onChange={ update( 'packaging_cost' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Section>

			<Section
				title={ __( 'Return reasons', 'auvrenth-cprt' ) }
				description={ __(
					'Reasons you can pick on a returned order. Renaming one keeps it on old orders.',
					'auvrenth-cprt'
				) }
			>
				<ReturnReasonsEditor
					reasons={ settings.return_reasons }
					onChange={ update( 'return_reasons' ) }
				/>
			</Section>

			<Section
				title={ __( 'Reports', 'auvrenth-cprt' ) }
				description={ __(
					'Order statuses that count as sales (and returns) on the dashboard.',
					'auvrenth-cprt'
				) }
			>
				<div className="cprt-settings__checkboxes">
					{ data.choices.statuses.map( ( status ) => (
						<CheckboxControl
							key={ status.key }
							label={ status.label }
							checked={ settings.report_statuses.includes(
								status.key
							) }
							onChange={ ( checked ) =>
								toggleStatus( status.key, checked )
							}
							__nextHasNoMarginBottom
						/>
					) ) }
				</div>
			</Section>
		</>
	);

	const renderAdvanced = () => (
		<Section
			title={ __( 'Uninstall', 'auvrenth-cprt' ) }
			description={ __(
				'When Auvrenth CPRT is deleted from the Plugins screen, also delete its profit data, settings, product cost prices and order courier charges. Your orders and products stay. This cannot be undone.',
				'auvrenth-cprt'
			) }
		>
			<CheckboxControl
				label={ __(
					'Delete all Auvrenth CPRT data when the plugin is deleted',
					'auvrenth-cprt'
				) }
				checked={ settings.delete_data }
				onChange={ update( 'delete_data' ) }
				__nextHasNoMarginBottom
			/>
		</Section>
	);

	const renderTab = ( tab ) => {
		if ( 'general' === tab.name ) {
			return renderGeneral();
		}
		if ( 'modules' === tab.name ) {
			return (
				<ModulesPanel
					modules={ data.choices.modules }
					state={ settings.modules }
					hasTab={ ( id ) => Boolean( panels[ id ] ) }
					onToggle={ ( id, enabled ) =>
						updateModule( id, { enabled } )
					}
					onOpen={ ( id ) =>
						setOpenTab( { name: id, key: openTab.key + 1 } )
					}
				/>
			);
		}
		if ( 'advanced' === tab.name ) {
			return renderAdvanced();
		}

		const Panel = panels[ tab.name ];
		const module = data.choices.modules.find(
			( item ) => item.id === tab.name
		);
		return (
			<Panel
				settings={ settings.modules[ tab.name ].settings }
				choices={ module?.choices || {} }
				onChange={ ( moduleSettings ) =>
					updateModule( tab.name, { settings: moduleSettings } )
				}
			/>
		);
	};

	return (
		<div className="cprt-dashboard cprt-settings">
			<header className="cprt-dashboard__header">
				<h1>{ __( 'Auvrenth CPRT settings', 'auvrenth-cprt' ) }</h1>
			</header>

			{ ! settings.setup_done && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Welcome! Add the courier charges and payment fees you pay, then save. Until then, profit uses whatever is below and may be wrong.',
						'auvrenth-cprt'
					) }
				</Notice>
			) }

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					actions={
						notice.recalculate && ! recalculating
							? [
									{
										label: __(
											'Recalculate all orders',
											'auvrenth-cprt'
										),
										onClick: recalculate,
										variant: 'primary',
									},
							  ]
							: []
					}
				>
					{ notice.message }
				</Notice>
			) }

			<TabPanel
				key={ openTab.key }
				className="cprt-settings__tabs"
				tabs={ tabs }
				initialTabName={ openTab.name }
			>
				{ renderTab }
			</TabPanel>

			<div className="cprt-settings__actions">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save settings', 'auvrenth-cprt' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ recalculate }
					disabled={ recalculating }
				>
					{ recalculating
						? __( 'Recalculating…', 'auvrenth-cprt' )
						: __( 'Recalculate all orders', 'auvrenth-cprt' ) }
				</Button>
			</div>
		</div>
	);
}
