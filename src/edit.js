import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	Placeholder,
	Icon,
	Notice,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from '../block.json';

const displaySections = [
	{
		title: __( 'Header', 'repo-pop' ),
		controls: [
			[ 'showTitle', __( 'Show title', 'repo-pop' ) ],
			[ 'showAvatar', __( 'Show owner avatar', 'repo-pop' ) ],
			[ 'showOwner', __( 'Show owner name', 'repo-pop' ) ],
		],
	},
	{
		title: __( 'Summary and Links', 'repo-pop' ),
		controls: [
			[ 'showSummary', __( 'Show summary', 'repo-pop' ) ],
			[ 'showHomepage', __( 'Show homepage link', 'repo-pop' ) ],
			[ 'showGitHubLink', __( 'Show GitHub link', 'repo-pop' ) ],
		],
	},
	{
		title: __( 'Repository Details', 'repo-pop' ),
		controls: [
			[ 'showLanguage', __( 'Show language', 'repo-pop' ) ],
			[ 'showTopics', __( 'Show topics', 'repo-pop' ) ],
			[ 'showStars', __( 'Show stars', 'repo-pop' ) ],
			[ 'showForks', __( 'Show forks', 'repo-pop' ) ],
			[ 'showOpenIssues', __( 'Show open issues', 'repo-pop' ) ],
			[ 'showLicense', __( 'Show license', 'repo-pop' ) ],
		],
	},
	{
		title: __( 'Dates and Status', 'repo-pop' ),
		controls: [
			[ 'showCreatedAt', __( 'Show created date', 'repo-pop' ) ],
			[ 'showUpdatedAt', __( 'Show updated date', 'repo-pop' ) ],
			[ 'showPushedAt', __( 'Show last push date', 'repo-pop' ) ],
			[ 'showDefaultBranch', __( 'Show default branch', 'repo-pop' ) ],
			[ 'showVisibility', __( 'Show visibility', 'repo-pop' ) ],
			[ 'showArchived', __( 'Show archived status', 'repo-pop' ) ],
		],
	},
];

function isLikelyGitHubRepoUrl( value ) {
	const trimmed = value.trim();

	if ( ! trimmed ) {
		return false;
	}

	if ( /^git@github\.com:[^/]+\/[^/]+(?:\.git)?$/i.test( trimmed ) ) {
		return true;
	}

	try {
		const url = new URL(
			/^[a-z][a-z0-9+.-]*:\/\//i.test( trimmed )
				? trimmed
				: `https://${ trimmed.replace( /^\/+/, '' ) }`
		);
		const host = url.hostname.replace( /^www\./i, '' ).toLowerCase();
		const parts = url.pathname.split( '/' ).filter( Boolean );

		return host === 'github.com' && parts.length >= 2;
	} catch {
		return false;
	}
}

function getDefaultValue( name ) {
	return metadata.attributes?.[ name ]?.default;
}

export default function Edit( { attributes, setAttributes } ) {
	const repoUrl = attributes.repoUrl || '';
	const blockProps = useBlockProps();
	const trimmedRepoUrl = repoUrl.trim();
	const urlIsInvalid =
		trimmedRepoUrl !== '' && ! isLikelyGitHubRepoUrl( trimmedRepoUrl );

	const inspectorControls = (
		<InspectorControls>
			<PanelBody
				title={ __( 'Repository', 'repo-pop' ) }
				initialOpen={ true }
			>
				<TextControl
					label={ __( 'GitHub repository URL', 'repo-pop' ) }
					help={ __(
						'Use a public github.com repository URL.',
						'repo-pop'
					) }
					value={ repoUrl }
					onChange={ ( value ) =>
						setAttributes( { repoUrl: value } )
					}
					onBlur={ ( event ) =>
						setAttributes( { repoUrl: event.target.value.trim() } )
					}
					placeholder="https://github.com/owner/repository"
					type="text"
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				{ urlIsInvalid && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Enter a public GitHub repository URL.',
							'repo-pop'
						) }
					</Notice>
				) }
			</PanelBody>

			{ displaySections.map( ( section ) => (
				<PanelBody
					key={ section.title }
					title={ section.title }
					initialOpen={ section.title === __( 'Header', 'repo-pop' ) }
				>
					{ section.controls.map( ( [ name, label ] ) => (
						<ToggleControl
							key={ name }
							label={ label }
							checked={
								typeof attributes[ name ] === 'undefined'
									? !! getDefaultValue( name )
									: !! attributes[ name ]
							}
							onChange={ ( value ) =>
								setAttributes( { [ name ]: value } )
							}
							__nextHasNoMarginBottom
						/>
					) ) }
				</PanelBody>
			) ) }
		</InspectorControls>
	);

	if ( ! trimmedRepoUrl ) {
		return (
			<div { ...blockProps }>
				{ inspectorControls }
				<Placeholder
					icon={ <Icon icon="editor-code" /> }
					label={ __( 'Repo Pop', 'repo-pop' ) }
					instructions={ __(
						'Paste a public GitHub repository URL.',
						'repo-pop'
					) }
				>
					<TextControl
						value={ repoUrl }
						onChange={ ( value ) =>
							setAttributes( { repoUrl: value } )
						}
						onBlur={ ( event ) =>
							setAttributes( {
								repoUrl: event.target.value.trim(),
							} )
						}
						placeholder="https://github.com/WordPress/gutenberg"
						className="repo-pop__url-input"
						type="text"
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</Placeholder>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			{ inspectorControls }
			{ urlIsInvalid ? (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The saved value does not look like a GitHub repository URL.',
						'repo-pop'
					) }
				</Notice>
			) : (
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					httpMethod="POST"
				/>
			) }
		</div>
	);
}
