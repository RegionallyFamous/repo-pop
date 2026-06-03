<?php
/**
 * Server-side render for the Repo Pop block.
 */

defined( 'ABSPATH' ) || exit;

$repo_pop_render_admin_error = static function ( $repo_pop_message ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo sprintf(
		'<div %s><p class="repo-pop-card__error">%s</p></div>',
		wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'repo-pop-card repo-pop-card--error' ) ) ),
		esc_html( $repo_pop_message )
	);
};

$repo_pop_repo_url = isset( $attributes['repoUrl'] ) ? trim( (string) $attributes['repoUrl'] ) : '';
$repo_pop_layout   = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'hero-stack';
$repo_pop_classes  = 'repo-pop-card repo-pop-card--' . $repo_pop_layout;

if ( ! in_array( $repo_pop_layout, array( 'hero-stack', 'bento-board', 'terminal-zine' ), true ) ) {
	$repo_pop_layout = 'hero-stack';
	$repo_pop_classes = 'repo-pop-card repo-pop-card--' . $repo_pop_layout;
}

if ( empty( $attributes['align'] ) ) {
	$repo_pop_classes .= ' alignwide';
}

if ( '' === $repo_pop_repo_url ) {
	$repo_pop_render_admin_error( __( 'Repo Pop: add a GitHub repository URL in the block settings.', 'repo-pop' ) );
	return;
}

$repo_pop_settings = array();
foreach ( array_keys( repo_pop_display_defaults() ) as $repo_pop_attribute_name ) {
	$repo_pop_settings[ $repo_pop_attribute_name ] = repo_pop_bool_attribute( $attributes, $repo_pop_attribute_name );
}

$repo_pop_parsed = repo_pop_parse_repo_url( $repo_pop_repo_url );
if ( is_wp_error( $repo_pop_parsed ) ) {
	$repo_pop_render_admin_error( $repo_pop_parsed->get_error_message() );
	return;
}

$repo_pop_repo = repo_pop_fetch_repository_data( $repo_pop_parsed['owner'], $repo_pop_parsed['repo'], $repo_pop_settings['showSummary'] );
if ( is_wp_error( $repo_pop_repo ) ) {
	$repo_pop_render_admin_error( $repo_pop_repo->get_error_message() );
	return;
}

$repo_pop_repo_name     = ! empty( $repo_pop_repo['name'] ) ? (string) $repo_pop_repo['name'] : (string) $repo_pop_parsed['repo'];
$repo_pop_full_name     = ! empty( $repo_pop_repo['fullName'] ) ? (string) $repo_pop_repo['fullName'] : (string) $repo_pop_parsed['full_name'];
$repo_pop_repo_url      = ! empty( $repo_pop_repo['url'] ) ? (string) $repo_pop_repo['url'] : 'https://github.com/' . rawurlencode( $repo_pop_parsed['owner'] ) . '/' . rawurlencode( $repo_pop_parsed['repo'] );
$repo_pop_owner         = isset( $repo_pop_repo['owner'] ) && is_array( $repo_pop_repo['owner'] ) ? $repo_pop_repo['owner'] : array();
$repo_pop_owner_name    = ! empty( $repo_pop_owner['login'] ) ? (string) $repo_pop_owner['login'] : (string) $repo_pop_parsed['owner'];
$repo_pop_owner_url     = ! empty( $repo_pop_owner['url'] ) ? (string) $repo_pop_owner['url'] : 'https://github.com/' . rawurlencode( $repo_pop_parsed['owner'] );
$repo_pop_summary       = ! empty( $repo_pop_repo['summary'] ) ? (string) $repo_pop_repo['summary'] : '';
$repo_pop_license       = isset( $repo_pop_repo['license'] ) && is_array( $repo_pop_repo['license'] ) ? $repo_pop_repo['license'] : null;
$repo_pop_license_label = '';

if ( $repo_pop_license ) {
	$repo_pop_license_label = ! empty( $repo_pop_license['spdxId'] ) && 'NOASSERTION' !== $repo_pop_license['spdxId']
		? (string) $repo_pop_license['spdxId']
		: (string) ( $repo_pop_license['name'] ?? '' );
}

$repo_pop_stat_items = array();
$repo_pop_meta_items = array();
$repo_pop_short_mark = static function ( $repo_pop_value ) {
	$repo_pop_known_marks = array(
		'css'        => 'CSS',
		'go'         => 'GO',
		'html'       => 'HTML',
		'java'       => 'JAVA',
		'javascript' => 'JS',
		'php'        => 'PHP',
		'python'     => 'PY',
		'ruby'       => 'RB',
		'rust'       => 'RS',
		'shell'      => 'SH',
		'typescript' => 'TS',
	);
	$repo_pop_key = strtolower( trim( (string) $repo_pop_value ) );

	if ( isset( $repo_pop_known_marks[ $repo_pop_key ] ) ) {
		return $repo_pop_known_marks[ $repo_pop_key ];
	}

	$repo_pop_mark = preg_replace( '/[^A-Za-z0-9]/', '', (string) $repo_pop_value );

	if ( '' === $repo_pop_mark ) {
		return 'I';
	}

	return strtoupper( substr( $repo_pop_mark, 0, 3 ) );
};

if ( $repo_pop_settings['showLanguage'] && ! empty( $repo_pop_repo['language'] ) ) {
	$repo_pop_meta_items[] = array( 'language', __( 'Language', 'repo-pop' ), (string) $repo_pop_repo['language'], $repo_pop_short_mark( $repo_pop_repo['language'] ) );
}

if ( $repo_pop_settings['showStars'] ) {
	$repo_pop_stat_items[] = array( 'stars', __( 'Stars', 'repo-pop' ), repo_pop_format_number( $repo_pop_repo['stars'] ?? 0 ), '*' );
}

if ( $repo_pop_settings['showForks'] ) {
	$repo_pop_stat_items[] = array( 'forks', __( 'Forks', 'repo-pop' ), repo_pop_format_number( $repo_pop_repo['forks'] ?? 0 ), '<>' );
}

if ( $repo_pop_settings['showOpenIssues'] ) {
	$repo_pop_stat_items[] = array( 'issues', __( 'Open issues', 'repo-pop' ), repo_pop_format_number( $repo_pop_repo['openIssues'] ?? 0 ), '!' );
}

if ( $repo_pop_settings['showLicense'] && '' !== $repo_pop_license_label ) {
	$repo_pop_meta_items[] = array( 'license', __( 'License', 'repo-pop' ), $repo_pop_license_label, 'LIC' );
}

if ( $repo_pop_settings['showDefaultBranch'] && ! empty( $repo_pop_repo['defaultBranch'] ) ) {
	$repo_pop_meta_items[] = array( 'branch', __( 'Default branch', 'repo-pop' ), (string) $repo_pop_repo['defaultBranch'], 'BR' );
}

if ( $repo_pop_settings['showVisibility'] && ! empty( $repo_pop_repo['visibility'] ) ) {
	$repo_pop_meta_items[] = array( 'visibility', __( 'Visibility', 'repo-pop' ), ucfirst( (string) $repo_pop_repo['visibility'] ), 'VIS' );
}

if ( $repo_pop_settings['showArchived'] ) {
	$repo_pop_meta_items[] = array( 'archived', __( 'Archived', 'repo-pop' ), ! empty( $repo_pop_repo['archived'] ) ? __( 'Yes', 'repo-pop' ) : __( 'No', 'repo-pop' ), 'ARC' );
}

$repo_pop_created_at = repo_pop_format_date( $repo_pop_repo['createdAt'] ?? '' );
$repo_pop_updated_at = repo_pop_format_date( $repo_pop_repo['updatedAt'] ?? '' );
$repo_pop_pushed_at  = repo_pop_format_date( $repo_pop_repo['pushedAt'] ?? '' );

if ( $repo_pop_settings['showCreatedAt'] && '' !== $repo_pop_created_at ) {
	$repo_pop_meta_items[] = array( 'created', __( 'Created', 'repo-pop' ), $repo_pop_created_at, 'NEW' );
}

if ( $repo_pop_settings['showUpdatedAt'] && '' !== $repo_pop_updated_at ) {
	$repo_pop_meta_items[] = array( 'updated', __( 'Updated', 'repo-pop' ), $repo_pop_updated_at, 'UPD' );
}

if ( $repo_pop_settings['showPushedAt'] && '' !== $repo_pop_pushed_at ) {
	$repo_pop_meta_items[] = array( 'pushed', __( 'Last push', 'repo-pop' ), $repo_pop_pushed_at, 'PUSH' );
}

$repo_pop_has_header = ( $repo_pop_settings['showAvatar'] && ! empty( $repo_pop_owner['avatarUrl'] ) ) || $repo_pop_settings['showTitle'] || $repo_pop_settings['showOwner'];
$repo_pop_links      = array();
$repo_pop_avatar_alt = '';

if ( '' !== $repo_pop_owner_name ) {
	/* translators: %s: GitHub repository owner name. */
	$repo_pop_avatar_alt = sprintf( __( '%s avatar', 'repo-pop' ), $repo_pop_owner_name );
}

if ( $repo_pop_settings['showHomepage'] && ! empty( $repo_pop_repo['homepage'] ) ) {
	$repo_pop_links[] = array(
		'key'   => 'homepage',
		'url'   => (string) $repo_pop_repo['homepage'],
		'label' => __( 'Homepage', 'repo-pop' ),
		'mark'  => 'www',
	);
}

if ( $repo_pop_settings['showGitHubLink'] ) {
	$repo_pop_links[] = array(
		'key'   => 'github',
		'url'   => $repo_pop_repo_url,
		'label' => __( 'View on GitHub', 'repo-pop' ),
		'mark'  => 'git',
	);
}
?>

<article <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => $repo_pop_classes ) ) ); ?>>
	<div class="repo-pop-card__showcase">
		<?php if ( $repo_pop_has_header ) : ?>
			<header class="repo-pop-card__hero">
				<div class="repo-pop-card__identity">
					<?php if ( $repo_pop_settings['showAvatar'] && ! empty( $repo_pop_owner['avatarUrl'] ) ) : ?>
						<a class="repo-pop-card__avatar-link" href="<?php echo esc_url( $repo_pop_owner_url ); ?>" target="_blank" rel="noopener noreferrer">
							<img
								class="repo-pop-card__avatar"
								src="<?php echo esc_url( $repo_pop_owner['avatarUrl'] ); ?>"
								alt="<?php echo esc_attr( $repo_pop_avatar_alt ); ?>"
								loading="lazy"
								decoding="async"
							/>
						</a>
					<?php endif; ?>

					<?php if ( $repo_pop_settings['showTitle'] || $repo_pop_settings['showOwner'] ) : ?>
						<div class="repo-pop-card__heading">
							<span class="repo-pop-card__kicker"><?php esc_html_e( 'GitHub project', 'repo-pop' ); ?></span>

							<?php if ( $repo_pop_settings['showTitle'] ) : ?>
								<h3 class="repo-pop-card__title">
									<a href="<?php echo esc_url( $repo_pop_repo_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $repo_pop_repo_name ); ?>
									</a>
								</h3>
							<?php endif; ?>

							<?php if ( $repo_pop_settings['showOwner'] ) : ?>
								<a class="repo-pop-card__owner" href="<?php echo esc_url( $repo_pop_owner_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $repo_pop_owner_name ); ?>
								</a>
								<?php if ( $repo_pop_settings['showRepoPath'] && '' !== $repo_pop_full_name ) : ?>
									<span class="repo-pop-card__path"><?php echo esc_html( 'github.com/' . $repo_pop_full_name ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $repo_pop_stat_items ) ) : ?>
					<ul class="repo-pop-card__stats">
						<?php foreach ( $repo_pop_stat_items as $repo_pop_item ) : ?>
							<li class="repo-pop-card__stat repo-pop-card__stat--<?php echo esc_attr( sanitize_html_class( $repo_pop_item[0] ) ); ?>">
								<span class="repo-pop-card__stat-mark" aria-hidden="true"><?php echo esc_html( $repo_pop_item[3] ); ?></span>
								<strong><?php echo esc_html( $repo_pop_item[2] ); ?></strong>
								<span><?php echo esc_html( $repo_pop_item[1] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php if ( $repo_pop_settings['showSummary'] && '' !== $repo_pop_summary ) : ?>
			<div class="repo-pop-card__summary">
				<?php foreach ( preg_split( "#\n\s*\n#", $repo_pop_summary ) as $repo_pop_summary_paragraph ) : ?>
					<?php if ( '' !== trim( $repo_pop_summary_paragraph ) ) : ?>
						<p><?php echo esc_html( trim( $repo_pop_summary_paragraph ) ); ?></p>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $repo_pop_settings['showTopics'] && ! empty( $repo_pop_repo['topics'] ) && is_array( $repo_pop_repo['topics'] ) ) : ?>
			<div class="repo-pop-card__topics" aria-label="<?php esc_attr_e( 'GitHub topics', 'repo-pop' ); ?>">
				<?php foreach ( $repo_pop_repo['topics'] as $repo_pop_topic ) : ?>
					<a href="<?php echo esc_url( 'https://github.com/topics/' . rawurlencode( (string) $repo_pop_topic ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $repo_pop_topic ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $repo_pop_meta_items ) ) : ?>
			<section class="repo-pop-card__details" aria-label="<?php esc_attr_e( 'Repository details', 'repo-pop' ); ?>">
				<h4><?php esc_html_e( 'Project facts', 'repo-pop' ); ?></h4>
				<ul class="repo-pop-card__meta">
					<?php foreach ( $repo_pop_meta_items as $repo_pop_item ) : ?>
						<li class="repo-pop-card__meta-item repo-pop-card__meta-item--<?php echo esc_attr( sanitize_html_class( $repo_pop_item[0] ) ); ?>">
							<span class="repo-pop-card__meta-mark" aria-hidden="true"><?php echo esc_html( $repo_pop_item[3] ); ?></span>
							<span class="repo-pop-card__meta-label"><?php echo esc_html( $repo_pop_item[1] ); ?></span>
							<strong><?php echo esc_html( $repo_pop_item[2] ); ?></strong>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $repo_pop_links ) ) : ?>
			<footer class="repo-pop-card__links">
				<?php foreach ( $repo_pop_links as $repo_pop_link ) : ?>
					<a class="repo-pop-card__link repo-pop-card__link--<?php echo esc_attr( sanitize_html_class( $repo_pop_link['key'] ) ); ?>" href="<?php echo esc_url( $repo_pop_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="repo-pop-card__link-mark" aria-hidden="true"><?php echo esc_html( $repo_pop_link['mark'] ); ?></span>
						<?php echo esc_html( $repo_pop_link['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</footer>
		<?php endif; ?>
	</div>
</article>
