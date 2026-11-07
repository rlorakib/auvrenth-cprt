/**
 * Auvrenth CPRT admin entry point: the dashboard or the settings screen, depending on the page.
 */
import { createRoot } from '@wordpress/element';

import App from './App';
import SettingsApp from './settings/SettingsApp';
import './style.scss';

const container = document.getElementById( 'cprt-dashboard' );

if ( container ) {
	createRoot( container ).render(
		'settings' === container.dataset.view ? <SettingsApp /> : <App />
	);
}
