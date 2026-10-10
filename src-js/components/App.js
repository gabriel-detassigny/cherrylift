/**
 * WordPress dependencies
 */
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ExportTab from './export/ExportTab';
import ImportTab from './import/ImportTab';

const TABS = [
	{
		name: 'export',
		title: __( 'Export', 'cherrylift' ),
	},
	{
		name: 'import',
		title: __( 'Import', 'cherrylift' ),
	},
];

export default function App() {
	return (
		<div className="cherrylift-app">
			<TabPanel tabs={ TABS }>
				{ ( tab ) => (
					<div className="cherrylift-app__panel">
						{ 'export' === tab.name ? (
							<ExportTab />
						) : (
							<ImportTab />
						) }
					</div>
				) }
			</TabPanel>
		</div>
	);
}
