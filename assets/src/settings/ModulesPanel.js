/**
 * Modules tab: turn each module on or off, and jump to its settings.
 */
import { Button, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}   props
 * @param {Array}    props.modules  `{ id, name, description }` for every module.
 * @param {Object}   props.state    `{ [id]: { enabled, settings } }`.
 * @param {Function} props.hasTab   Whether a module has a settings tab, by ID.
 * @param {Function} props.onToggle Called with the module ID and whether it is on.
 * @param {Function} props.onOpen   Called with the module ID to show its tab.
 */
export default function ModulesPanel( {
	modules,
	state,
	hasTab,
	onToggle,
	onOpen,
} ) {
	return (
		<div className="cprt-modules">
			{ modules.map( ( module ) => {
				const enabled = Boolean( state[ module.id ]?.enabled );
				return (
					<div
						className="cprt-card cprt-module"
						key={ module.id }
					>
						<ToggleControl
							label={ <strong>{ module.name }</strong> }
							help={ module.description }
							checked={ enabled }
							onChange={ ( checked ) =>
								onToggle( module.id, checked )
							}
							__nextHasNoMarginBottom
						/>
						{ enabled && hasTab( module.id ) && (
							<Button
								variant="secondary"
								size="compact"
								onClick={ () => onOpen( module.id ) }
							>
								{ __( 'Settings', 'auvrenth-cprt' ) }
							</Button>
						) }
					</div>
				);
			} ) }
			<p className="cprt-card__note">
				{ __(
					'A module that is on gets its own tab. Save to apply.',
					'auvrenth-cprt'
				) }
			</p>
		</div>
	);
}
