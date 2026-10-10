/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import App from './components/App';
import './style.scss';

const ROOT_ELEMENT_ID = 'cherrylift-root';

const container = document.getElementById( ROOT_ELEMENT_ID );

if ( container ) {
	createRoot( container ).render( <App /> );
}
