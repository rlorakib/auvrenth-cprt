/**
 * Munafa admin dashboard entry point.
 */
import { createRoot } from '@wordpress/element';

import App from './App';
import './style.scss';

const container = document.getElementById( 'munafa-dashboard' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
