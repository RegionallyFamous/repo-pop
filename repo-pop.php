<?php
/**
 * Plugin Name:  Repo Pop
 * Plugin URI:   https://github.com/RegionallyFamous/repo-pop
 * Description:  Turn a public GitHub repository into a playful project showcase.
 * Version:      0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author:       Regionally Famous
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  repo-pop
 */

defined( 'ABSPATH' ) || exit;

define( 'REPO_POP_VERSION', '0.1.0' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p><strong>Repo Pop</strong> requires PHP 7.4 or higher. Current: ' . esc_html( PHP_VERSION ) . '</p></div>';
		}
	);
	return;
}

/**
 * Register the block using block.json.
 */
add_action(
	'init',
	static function () {
		register_block_type( __DIR__ . '/block.json' );
	}
);

/**
 * Block attribute defaults shared by PHP rendering.
 *
 * @return array<string,bool>
 */
function repo_pop_display_defaults() {
	return array(
		'showTitle'         => true,
		'showAvatar'        => true,
		'showOwner'         => true,
		'showSummary'       => true,
		'showHomepage'      => true,
		'showLanguage'      => true,
		'showTopics'        => true,
		'showStars'         => true,
		'showForks'         => true,
		'showOpenIssues'    => true,
		'showLicense'       => true,
		'showCreatedAt'     => true,
		'showUpdatedAt'     => true,
		'showPushedAt'      => true,
		'showDefaultBranch' => true,
		'showVisibility'    => true,
		'showArchived'      => true,
		'showGitHubLink'    => true,
	);
}

/**
 * Read a boolean block attribute using block defaults.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @param string              $name Attribute name.
 * @return bool
 */
function repo_pop_bool_attribute( $attributes, $name ) {
	$defaults = repo_pop_display_defaults();

	if ( array_key_exists( $name, $attributes ) ) {
		return (bool) $attributes[ $name ];
	}

	return ! empty( $defaults[ $name ] );
}

/**
 * Parse common GitHub repository URL formats.
 *
 * @param string $repo_url Repository URL.
 * @return array<string,string>|WP_Error
 */
function repo_pop_parse_repo_url( $repo_url ) {
	$value = trim( (string) $repo_url );

	if ( '' === $value ) {
		return new WP_Error( 'repo_pop_empty_url', __( 'Add a GitHub repository URL.', 'repo-pop' ) );
	}

	if ( preg_match( '#^git@github\.com:([^/]+)/(.+?)(?:\.git)?$#i', $value, $matches ) ) {
		$owner = $matches[1];
		$repo  = preg_replace( '#\.git$#i', '', $matches[2] );
		return repo_pop_validate_repo_parts( $owner, $repo );
	}

	if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $value ) ) {
		$value = 'https://' . ltrim( $value, '/' );
	}

	$parsed = wp_parse_url( $value );
	if ( ! is_array( $parsed ) ) {
		return new WP_Error( 'repo_pop_invalid_url', __( 'The repository URL could not be parsed.', 'repo-pop' ) );
	}

	$scheme = isset( $parsed['scheme'] ) ? strtolower( (string) $parsed['scheme'] ) : '';
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return new WP_Error( 'repo_pop_invalid_scheme', __( 'Only public http and https GitHub repository URLs are supported.', 'repo-pop' ) );
	}

	$host = isset( $parsed['host'] ) ? strtolower( (string) $parsed['host'] ) : '';
	$host = preg_replace( '#^www\.#', '', $host );
	if ( 'github.com' !== $host ) {
		return new WP_Error( 'repo_pop_invalid_host', __( 'Use a github.com repository URL.', 'repo-pop' ) );
	}

	$path  = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
	$parts = array_values( array_filter( explode( '/', $path ), 'strlen' ) );

	if ( count( $parts ) < 2 ) {
		return new WP_Error( 'repo_pop_missing_parts', __( 'The GitHub URL must include an owner and repository name.', 'repo-pop' ) );
	}

	$owner = $parts[0];
	$repo  = preg_replace( '#\.git$#i', '', $parts[1] );

	return repo_pop_validate_repo_parts( $owner, $repo );
}

/**
 * Validate parsed owner and repository values.
 *
 * @param string $owner GitHub owner.
 * @param string $repo GitHub repository.
 * @return array<string,string>|WP_Error
 */
function repo_pop_validate_repo_parts( $owner, $repo ) {
	$owner = trim( (string) $owner );
	$repo  = trim( (string) $repo );

	if ( '' === $owner || '' === $repo ) {
		return new WP_Error( 'repo_pop_missing_parts', __( 'The GitHub URL must include an owner and repository name.', 'repo-pop' ) );
	}

	if ( ! preg_match( '#^[A-Za-z0-9_.-]+$#', $owner ) || ! preg_match( '#^[A-Za-z0-9_.-]+$#', $repo ) ) {
		return new WP_Error( 'repo_pop_invalid_parts', __( 'The GitHub owner or repository name contains unsupported characters.', 'repo-pop' ) );
	}

	return array(
		'owner'     => $owner,
		'repo'      => $repo,
		'full_name' => $owner . '/' . $repo,
	);
}

/**
 * Fetch normalized repository data, with transient caching.
 *
 * @param string $owner GitHub owner.
 * @param string $repo GitHub repository.
 * @param bool   $include_readme Whether README intro data is needed.
 * @return array<string,mixed>|WP_Error
 */
function repo_pop_fetch_repository_data( $owner, $repo, $include_readme = true ) {
	$cache_key = 'repo_pop_' . md5( strtolower( $owner . '/' . $repo ) . ':' . ( $include_readme ? 'summary' : 'basic' ) );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) && ! empty( $cached['fullName'] ) ) {
		return $cached;
	}

	$endpoint = sprintf( '/repos/%s/%s', rawurlencode( $owner ), rawurlencode( $repo ) );
	$payload  = repo_pop_api_request( $endpoint );

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$readme_intro = '';
	if ( $include_readme ) {
		$readme = repo_pop_api_request( $endpoint . '/readme' );

		if ( ! is_wp_error( $readme ) ) {
			$readme_intro = repo_pop_extract_readme_intro( $readme );
		}
	}

	$data = repo_pop_normalize_repository_payload( $payload, $readme_intro );

	$cache_seconds = (int) apply_filters( 'repo_pop_cache_seconds', 12 * HOUR_IN_SECONDS, $owner, $repo );
	if ( $cache_seconds > 0 ) {
		set_transient( $cache_key, $data, $cache_seconds );
	}

	return $data;
}

/**
 * Request the public GitHub API.
 *
 * @param string $endpoint API endpoint starting with /.
 * @return array<string,mixed>|WP_Error
 */
function repo_pop_api_request( $endpoint ) {
	$url     = 'https://api.github.com' . $endpoint;
	$timeout = (int) apply_filters( 'repo_pop_http_timeout', 8 );

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => max( 1, $timeout ),
			'redirection' => 3,
			'headers'     => array(
				'Accept'               => 'application/vnd.github+json',
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'repo-pop/' . REPO_POP_VERSION . ' (' . home_url( '/' ) . ')',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'repo_pop_http_error',
			sprintf(
				/* translators: %s: HTTP error message. */
				__( 'GitHub could not be reached: %s', 'repo-pop' ),
				$response->get_error_message()
			)
		);
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = (string) wp_remote_retrieve_body( $response );
	$json = json_decode( $body, true );

	if ( $code < 200 || $code >= 300 ) {
		$message = is_array( $json ) && ! empty( $json['message'] )
			? (string) $json['message']
			: sprintf(
				/* translators: %d: HTTP status code. */
				__( 'GitHub returned HTTP %d.', 'repo-pop' ),
				$code
			);

		if ( 403 === $code && false !== stripos( $message, 'rate limit' ) ) {
			$message = __( 'GitHub API rate limit reached. Cached content will display again after the limit resets.', 'repo-pop' );
		}

		return new WP_Error(
			'repo_pop_api_error',
			sprintf(
				/* translators: %s: GitHub API error message. */
				__( 'GitHub API request failed: %s', 'repo-pop' ),
				$message
			),
			array( 'status' => $code )
		);
	}

	if ( ! is_array( $json ) ) {
		return new WP_Error( 'repo_pop_invalid_json', __( 'GitHub returned an unreadable API response.', 'repo-pop' ) );
	}

	return $json;
}

/**
 * Normalize the GitHub repository response into render-safe data.
 *
 * @param array<string,mixed> $payload GitHub API payload.
 * @param string              $readme_intro Clean README intro.
 * @return array<string,mixed>
 */
function repo_pop_normalize_repository_payload( $payload, $readme_intro ) {
	$owner = isset( $payload['owner'] ) && is_array( $payload['owner'] ) ? $payload['owner'] : array();

	$license = null;
	if ( isset( $payload['license'] ) && is_array( $payload['license'] ) ) {
		$license = array(
			'name'   => repo_pop_clean_text( $payload['license']['name'] ?? '' ),
			'spdxId' => repo_pop_clean_text( $payload['license']['spdx_id'] ?? '' ),
		);
	}

	$topics = array();
	if ( isset( $payload['topics'] ) && is_array( $payload['topics'] ) ) {
		foreach ( $payload['topics'] as $topic ) {
			$topic = sanitize_key( (string) $topic );
			if ( '' !== $topic ) {
				$topics[] = $topic;
			}
		}
	}

	$description = repo_pop_clean_text( $payload['description'] ?? '' );
	$summary     = repo_pop_build_summary( $description, $readme_intro );

	return array(
		'name'        => repo_pop_clean_text( $payload['name'] ?? '' ),
		'fullName'    => repo_pop_clean_text( $payload['full_name'] ?? '' ),
		'url'         => esc_url_raw( $payload['html_url'] ?? '' ),
		'description' => $description,
		'summary'     => $summary,
		'homepage'    => esc_url_raw( $payload['homepage'] ?? '' ),
		'language'    => repo_pop_clean_text( $payload['language'] ?? '' ),
		'topics'      => array_slice( array_values( array_unique( $topics ) ), 0, 12 ),
		'stars'       => isset( $payload['stargazers_count'] ) ? max( 0, (int) $payload['stargazers_count'] ) : 0,
		'forks'       => isset( $payload['forks_count'] ) ? max( 0, (int) $payload['forks_count'] ) : 0,
		'openIssues'  => isset( $payload['open_issues_count'] ) ? max( 0, (int) $payload['open_issues_count'] ) : 0,
		'license'     => $license,
		'owner'       => array(
			'login'     => repo_pop_clean_text( $owner['login'] ?? '' ),
			'avatarUrl' => esc_url_raw( $owner['avatar_url'] ?? '' ),
			'url'       => esc_url_raw( $owner['html_url'] ?? '' ),
		),
		'createdAt'   => repo_pop_clean_text( $payload['created_at'] ?? '' ),
		'updatedAt'   => repo_pop_clean_text( $payload['updated_at'] ?? '' ),
		'pushedAt'    => repo_pop_clean_text( $payload['pushed_at'] ?? '' ),
		'defaultBranch' => repo_pop_clean_text( $payload['default_branch'] ?? '' ),
		'visibility'  => repo_pop_clean_text( $payload['visibility'] ?? '' ),
		'archived'    => ! empty( $payload['archived'] ),
	);
}

/**
 * Build a concise summary from GitHub description and README intro.
 *
 * @param string $description Repository description.
 * @param string $readme_intro README intro.
 * @return string
 */
function repo_pop_build_summary( $description, $readme_intro ) {
	$description = trim( (string) $description );
	$readme_intro = trim( (string) $readme_intro );

	if ( '' === $description ) {
		return $readme_intro;
	}

	if ( '' === $readme_intro ) {
		return $description;
	}

	if ( false !== stripos( $readme_intro, $description ) || false !== stripos( $description, $readme_intro ) ) {
		return $description;
	}

	return $description . "\n\n" . $readme_intro;
}

/**
 * Extract the first useful prose paragraph from a README payload.
 *
 * @param array<string,mixed> $payload GitHub README payload.
 * @return string
 */
function repo_pop_extract_readme_intro( $payload ) {
	$content = isset( $payload['content'] ) ? repo_pop_regex_replace( '#\s+#', '', (string) $payload['content'] ) : '';
	$encoding = isset( $payload['encoding'] ) ? strtolower( (string) $payload['encoding'] ) : 'base64';

	if ( '' === $content || 'base64' !== $encoding ) {
		return '';
	}

	$decoded = base64_decode( $content, true );
	if ( false === $decoded ) {
		return '';
	}

	$text = str_replace( array( "\r\n", "\r" ), "\n", $decoded );
	$text = repo_pop_regex_replace( '#<!--.*?-->#s', "\n\n", $text );
	$text = repo_pop_regex_replace( '#```.*?```#s', "\n\n", $text );
	$text = repo_pop_regex_replace( '#~~~.*?~~~#s', "\n\n", $text );

	$paragraphs = preg_split( "#\n\s*\n#", (string) $text );
	if ( ! is_array( $paragraphs ) ) {
		return '';
	}

	foreach ( $paragraphs as $paragraph ) {
		$candidate = repo_pop_clean_markdown_paragraph( $paragraph );

		if ( '' === $candidate || strlen( $candidate ) < 40 ) {
			continue;
		}

		$lower = strtolower( $candidate );
		if ( false !== strpos( $lower, 'badge' ) || false !== strpos( $lower, 'shields.io' ) ) {
			continue;
		}

		return wp_trim_words( $candidate, 70, '...' );
	}

	return '';
}

/**
 * Strip common Markdown syntax from one prose paragraph.
 *
 * @param string $paragraph Markdown paragraph.
 * @return string
 */
function repo_pop_clean_markdown_paragraph( $paragraph ) {
	$paragraph = trim( (string) $paragraph );

	if ( '' === $paragraph ) {
		return '';
	}

	if ( preg_match( '~^\s*(\||[-=]{3,}|\#{1,6}\s*$)~m', $paragraph ) ) {
		return '';
	}

	if ( substr_count( $paragraph, '|' ) >= 2 ) {
		return '';
	}

	$paragraph = repo_pop_regex_replace( '#!\[[^\]]*\]\([^)]+\)#', ' ', $paragraph );
	$paragraph = repo_pop_regex_replace( '~^\s{0,3}\#{1,6}\s*~m', '', $paragraph );
	$paragraph = repo_pop_regex_replace( '#\[([^\]]+)\]\([^)]+\)#', '$1', $paragraph );
	$paragraph = repo_pop_regex_replace( '#`([^`]+)`#', '$1', $paragraph );
	$paragraph = repo_pop_regex_replace( '#<https?://[^>]+>#i', ' ', $paragraph );
	$paragraph = repo_pop_regex_replace( '#https?://\S+#i', ' ', $paragraph );
	$paragraph = str_replace( array( '*', '_', '~', '>', '#', '[', ']', '(', ')' ), '', $paragraph );

	return repo_pop_clean_text( $paragraph );
}

/**
 * Clean plain text from GitHub or Markdown values.
 *
 * @param mixed $value Text value.
 * @return string
 */
function repo_pop_clean_text( $value ) {
	$charset = get_option( 'blog_charset' );
	if ( ! is_string( $charset ) || '' === $charset ) {
		$charset = 'UTF-8';
	}

	$text = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, $charset );
	$text = repo_pop_regex_replace( '#\s+#', ' ', $text );

	return trim( (string) $text );
}

/**
 * Run preg_replace and preserve the original subject if PCRE fails.
 *
 * @param string $pattern Regex pattern.
 * @param string $replacement Replacement value.
 * @param string $subject Subject text.
 * @return string
 */
function repo_pop_regex_replace( $pattern, $replacement, $subject ) {
	$result = preg_replace( $pattern, $replacement, (string) $subject );

	return is_string( $result ) ? $result : (string) $subject;
}

/**
 * Format a GitHub ISO date using the site's date format.
 *
 * @param string $iso_date ISO date string.
 * @return string
 */
function repo_pop_format_date( $iso_date ) {
	$timestamp = strtotime( (string) $iso_date );

	if ( ! $timestamp ) {
		return '';
	}

	return date_i18n( get_option( 'date_format' ), $timestamp );
}

/**
 * Format repository counts.
 *
 * @param int $number Count.
 * @return string
 */
function repo_pop_format_number( $number ) {
	return number_format_i18n( max( 0, (int) $number ) );
}
