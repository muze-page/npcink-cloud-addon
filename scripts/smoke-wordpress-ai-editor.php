<?php
/**
 * Local WP-CLI smoke gate for the current WordPress AI editor data path.
 *
 * Run with:
 * composer run smoke:wp-ai-editor
 *
 * Optional:
 * WP_AI_SMOKE_USER=1 composer run smoke:wp-ai-editor
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "[fail] Run this file through WP-CLI eval-file inside a WordPress install.\n" );
	exit( 1 );
}

/**
 * Prints a passing smoke assertion.
 *
 * @param string $message Message.
 * @return void
 */
function npcink_cloud_addon_wp_ai_editor_smoke_ok( string $message ): void {
	fwrite( STDOUT, '[ok] ' . $message . "\n" );
}

/**
 * Throws a smoke assertion failure so the temporary draft can be cleaned up.
 *
 * @param bool   $condition Condition.
 * @param string $message Failure message.
 * @return void
 */
function npcink_cloud_addon_wp_ai_editor_smoke_require( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Rejects every non-local WordPress target before creating a fixture draft.
 *
 * @return void
 */
function npcink_cloud_addon_wp_ai_editor_smoke_require_local_target(): void {
	$environment = wp_get_environment_type();
	$host        = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	$is_local_host = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
		|| ( strlen( $host ) > 6 && '.local' === substr( $host, -6 ) );

	npcink_cloud_addon_wp_ai_editor_smoke_require(
		in_array( $environment, array( 'local', 'development' ), true ),
		'Editor data-path smoke accepts only a WordPress local/development environment.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		$is_local_host,
		'Editor data-path smoke accepts only localhost, loopback, or a .local hostname.'
	);
}

/**
 * Selects the smoke user from WP_AI_SMOKE_USER.
 *
 * @return void
 */
function npcink_cloud_addon_wp_ai_editor_smoke_set_user(): void {
	$user_spec = (string) ( getenv( 'WP_AI_SMOKE_USER' ) ?: '1' );
	$user      = false;

	if ( is_numeric( $user_spec ) ) {
		$user = get_user_by( 'id', absint( $user_spec ) );
		if ( ! $user ) {
			$user = get_user_by( 'login', $user_spec );
		}
	} else {
		$user = get_user_by( 'login', $user_spec );
	}

	npcink_cloud_addon_wp_ai_editor_smoke_require(
		(bool) $user && ! empty( $user->ID ),
		'Smoke user not found. Set WP_AI_SMOKE_USER to an administrator login or ID.'
	);

	wp_set_current_user( (int) $user->ID );

	npcink_cloud_addon_wp_ai_editor_smoke_require(
		current_user_can( 'edit_posts' ),
		'Smoke user cannot edit posts.'
	);
}

/**
 * Runs a REST request in-process.
 *
 * @param string              $method HTTP method.
 * @param string              $route REST route.
 * @param array<string,mixed> $body JSON body.
 * @param array<string,mixed> $params Query params.
 * @return WP_REST_Response
 */
function npcink_cloud_addon_wp_ai_editor_smoke_rest_request( string $method, string $route, array $body = array(), array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	if ( ! empty( $body ) ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}

	return rest_do_request( $request );
}

/**
 * Returns REST response data or throws a concise error.
 *
 * @param WP_REST_Response $response REST response.
 * @param string           $label Assertion label.
 * @param int              $expected_status Expected HTTP status.
 * @return mixed
 */
function npcink_cloud_addon_wp_ai_editor_smoke_data( $response, string $label, int $expected_status = 200 ) {
	$status = (int) $response->get_status();
	$data   = $response->get_data();
	if ( $expected_status !== $status ) {
		$error = is_array( $data ) ? (string) ( $data['message'] ?? wp_json_encode( $data ) ) : (string) $data;
		throw new RuntimeException( $label . ' returned HTTP ' . $status . ': ' . substr( $error, 0, 300 ) );
	}

	return $data;
}

/**
 * Extracts text from a WordPress AI ability result shape.
 *
 * @param mixed $data REST response data.
 * @return string
 */
function npcink_cloud_addon_wp_ai_editor_smoke_text( $data ): string {
	if ( is_string( $data ) ) {
		return trim( $data );
	}
	if ( ! is_array( $data ) ) {
		return '';
	}

	$candidates = array(
		$data['title'] ?? null,
		$data['summary'] ?? null,
		$data['description'] ?? null,
		$data['description']['text'] ?? null,
		$data['output_text'] ?? null,
		$data['data']['result']['title'] ?? null,
		$data['data']['result']['description']['text'] ?? null,
		$data['data']['result']['output_text'] ?? null,
		$data['result']['title'] ?? null,
		$data['result']['description']['text'] ?? null,
		$data['result']['output_text'] ?? null,
	);

	foreach ( $candidates as $candidate ) {
		if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
			return trim( $candidate );
		}
	}

	return '';
}

/**
 * Returns the current revision IDs for one post.
 *
 * @param int $post_id Post ID.
 * @return list<int>
 */
function npcink_cloud_addon_wp_ai_editor_smoke_revision_ids( int $post_id ): array {
	$revision_ids = array_map( 'absint', array_keys( wp_get_post_revisions( $post_id ) ) );
	sort( $revision_ids, SORT_NUMERIC );

	return array_values( $revision_ids );
}

/**
 * Captures the local fields that Cloud suggestion calls must not mutate.
 *
 * @param int $post_id Post ID.
 * @return array{title:string,content:string,status:string,revision_ids:list<int>}
 */
function npcink_cloud_addon_wp_ai_editor_smoke_snapshot( int $post_id ): array {
	$post = get_post( $post_id );
	npcink_cloud_addon_wp_ai_editor_smoke_require( $post instanceof WP_Post, 'Temporary smoke draft no longer exists.' );

	return array(
		'title'        => (string) $post->post_title,
		'content'      => (string) $post->post_content,
		'status'       => (string) $post->post_status,
		'revision_ids' => npcink_cloud_addon_wp_ai_editor_smoke_revision_ids( $post_id ),
	);
}

/**
 * Builds the one summary group block used by the WordPress AI editor feature.
 *
 * @param string $summary Summary text.
 * @return string
 */
function npcink_cloud_addon_wp_ai_editor_smoke_summary_block( string $summary ): string {
	$paragraphs = preg_split( "/\n{2,}/", trim( $summary ) );
	$inner      = '';
	foreach ( $paragraphs ?: array() as $paragraph ) {
		$paragraph = trim( $paragraph );
		if ( '' === $paragraph ) {
			continue;
		}
		$inner .= '<!-- wp:paragraph -->' . "\n";
		$inner .= '<p>' . esc_html( $paragraph ) . '</p>' . "\n";
		$inner .= '<!-- /wp:paragraph -->' . "\n";
	}

	npcink_cloud_addon_wp_ai_editor_smoke_require( '' !== $inner, 'Summary suggestion could not produce a summary block.' );

	return '<!-- wp:group {"className":"ai-summarization-summary","aiGeneratedSummary":true} -->' . "\n"
		. '<div class="wp-block-group ai-summarization-summary">' . "\n"
		. $inner
		. '</div>' . "\n"
		. '<!-- /wp:group -->';
}

/**
 * Builds the accepted selected whole core/paragraph block rewrite.
 *
 * The upstream editor feature replaces only the selected paragraph's content
 * attribute. This smoke keeps that same one-block boundary and rejects output
 * that attempts to introduce another block.
 *
 * @param string $suggestion Rewrite suggestion.
 * @return string
 */
function npcink_cloud_addon_wp_ai_editor_smoke_rewritten_paragraph_block( string $suggestion ): string {
	$inner = trim( wp_kses_post( $suggestion ) );
	if ( 1 === preg_match( '#\A<p>(.*)</p>\z#s', $inner, $matches ) ) {
		$inner = trim( (string) $matches[1] );
	}

	npcink_cloud_addon_wp_ai_editor_smoke_require( '' !== trim( wp_strip_all_tags( $inner ) ), 'Content resizing returned empty paragraph content.' );
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		1 !== preg_match( '/<!--\s*wp:|<\/?(?:p|div|section|article|h[1-6]|ul|ol|li|blockquote)\b/i', $inner ),
		'Content resizing returned more than one paragraph-block boundary.'
	);

	return '<!-- wp:paragraph {"aiResized":true} -->' . "\n"
		. '<p>' . $inner . '</p>' . "\n"
		. '<!-- /wp:paragraph -->';
}

/**
 * Applies three explicitly accepted suggestions to the isolated draft once.
 *
 * This is a local data-path acceptance helper. It does not simulate browser
 * review or Core audit. A second call returns a no-op before issuing a write.
 *
 * @param int    $post_id Post ID.
 * @param string $initial_title Expected initial title.
 * @param string $initial_content Expected initial content.
 * @param string $accepted_title Accepted title suggestion.
 * @param string $summary Accepted summary suggestion.
 * @param string $target_block Exact target paragraph block.
 * @param string $accepted_target_block Accepted replacement paragraph block.
 * @param string $sentinel_block Exact non-target sentinel paragraph block.
 * @return array{changed:bool,content:string}
 */
function npcink_cloud_addon_wp_ai_editor_smoke_apply_accepted_suggestions(
	int $post_id,
	string $initial_title,
	string $initial_content,
	string $accepted_title,
	string $summary,
	string $target_block,
	string $accepted_target_block,
	string $sentinel_block
): array {
	$replacement_count = 0;
	$rewritten_content = str_replace( $target_block, $accepted_target_block, $initial_content, $replacement_count );
	npcink_cloud_addon_wp_ai_editor_smoke_require( 1 === $replacement_count, 'Expected exactly one target core/paragraph block in the initial draft.' );

	$expected_content = npcink_cloud_addon_wp_ai_editor_smoke_summary_block( $summary ) . "\n\n" . $rewritten_content;
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		1 === substr_count( $expected_content, '"aiGeneratedSummary":true' ),
		'Accepted content must contain exactly one generated summary block.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		1 === substr_count( $expected_content, $sentinel_block ),
		'Expected content must preserve the non-target sentinel paragraph byte-for-byte.'
	);

	$current = npcink_cloud_addon_wp_ai_editor_smoke_snapshot( $post_id );
	npcink_cloud_addon_wp_ai_editor_smoke_require( 'draft' === $current['status'], 'Local acceptance may apply only to a draft.' );

	if ( $accepted_title === $current['title'] && $expected_content === $current['content'] ) {
		return array(
			'changed' => false,
			'content' => $expected_content,
		);
	}

	npcink_cloud_addon_wp_ai_editor_smoke_require(
		$initial_title === $current['title'] && $initial_content === $current['content'],
		'Local draft changed outside the explicit acceptance step.'
	);

	$updated = npcink_cloud_addon_wp_ai_editor_smoke_data(
		npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
			'POST',
			'/wp/v2/posts/' . $post_id,
			array(
				'title'   => $accepted_title,
				'content' => $expected_content,
				'status'  => 'draft',
			)
		),
		'Explicit local draft acceptance'
	);

	npcink_cloud_addon_wp_ai_editor_smoke_require(
		is_array( $updated ) && 'draft' === (string) ( $updated['status'] ?? '' ),
		'Explicit local acceptance did not preserve draft status.'
	);

	return array(
		'changed' => true,
		'content' => $expected_content,
	);
}

$post_id = 0;
$failure = '';

try {
	npcink_cloud_addon_wp_ai_editor_smoke_require_local_target();
	npcink_cloud_addon_wp_ai_editor_smoke_set_user();

	$initial_title            = 'Npcink Cloud WordPress AI editor deterministic smoke draft';
	$target_paragraph_content = '这段目标文字专门用于验证 WordPress AI 对选中整个段落块的改写建议。它应当在本地明确接受后被替换，同时不能影响相邻的非目标段落。';
	$sentinel_text            = 'NPCINK_SENTINEL_NON_TARGET_PARAGRAPH_DO_NOT_CHANGE_20260718：这个非目标段落必须逐字不变。';
	$target_block             = '<!-- wp:paragraph -->' . "\n"
		. '<p>' . $target_paragraph_content . '</p>' . "\n"
		. '<!-- /wp:paragraph -->';
	$sentinel_block           = '<!-- wp:paragraph -->' . "\n"
		. '<p>' . $sentinel_text . '</p>' . "\n"
		. '<!-- /wp:paragraph -->';
	$initial_content          = $target_block . "\n\n" . $sentinel_block;

	$created = npcink_cloud_addon_wp_ai_editor_smoke_data(
		npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
			'POST',
			'/wp/v2/posts',
			array(
				'title'   => $initial_title,
				'content' => $initial_content,
				'status'  => 'draft',
			)
		),
		'Deterministic temporary draft creation',
		201
	);

	$post_id = absint( is_array( $created ) ? ( $created['id'] ?? 0 ) : 0 );
	npcink_cloud_addon_wp_ai_editor_smoke_require( $post_id > 0, 'Temporary draft creation did not return a post ID.' );

	$before_cloud = npcink_cloud_addon_wp_ai_editor_smoke_snapshot( $post_id );
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		array(
			'title'        => $initial_title,
			'content'      => $initial_content,
			'status'       => 'draft',
			'revision_ids' => $before_cloud['revision_ids'],
		) === $before_cloud,
		'Temporary draft did not preserve the deterministic initial title, body, and draft status.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Created deterministic temporary draft ' . $post_id . '.' );

	$title_suggestion = npcink_cloud_addon_wp_ai_editor_smoke_text(
		npcink_cloud_addon_wp_ai_editor_smoke_data(
			npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
				'POST',
				'/wp-abilities/v1/abilities/ai/title-generation/run',
				array(
					'input' => array(
						'content' => $initial_content,
						'context' => (string) $post_id,
					),
				)
			),
			'Editor title generation ability'
		)
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		'' !== $title_suggestion && $initial_title !== $title_suggestion && 1 !== preg_match( '/以下是|下面是|here are/i', $title_suggestion ),
		'Editor title generation ability returned empty, unchanged, or boilerplate text.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_ok( 'ai/title-generation returned one suggestion without local acceptance.' );

	$summary = npcink_cloud_addon_wp_ai_editor_smoke_text(
		npcink_cloud_addon_wp_ai_editor_smoke_data(
			npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
				'POST',
				'/wp-abilities/v1/abilities/ai/summarization/run',
				array(
					'input' => array(
						'content' => $initial_content,
						'context' => (string) $post_id,
						'length'  => 'short',
					),
				)
			),
			'Editor summarization ability'
		)
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require( '' !== $summary, 'Editor summarization ability returned empty text.' );
	npcink_cloud_addon_wp_ai_editor_smoke_ok( 'ai/summarization returned one suggestion without local acceptance.' );

	$rewrite_suggestion = npcink_cloud_addon_wp_ai_editor_smoke_text(
		npcink_cloud_addon_wp_ai_editor_smoke_data(
			npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
				'POST',
				'/wp-abilities/v1/abilities/ai/content-resizing/run',
				array(
					'input' => array(
						'content' => $target_paragraph_content,
						'action'  => 'rephrase',
						'post_id' => $post_id,
					),
				)
			),
			'Editor selected whole core/paragraph block rephrase ability'
		)
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		'' !== trim( wp_strip_all_tags( $rewrite_suggestion ) )
		&& trim( wp_strip_all_tags( $target_paragraph_content ) ) !== trim( wp_strip_all_tags( $rewrite_suggestion ) ),
		'Editor content resizing ability returned empty or unchanged paragraph content.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_ok( 'ai/content-resizing returned a suggestion for the selected whole core/paragraph block without local acceptance.' );

	$after_cloud = npcink_cloud_addon_wp_ai_editor_smoke_snapshot( $post_id );
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		$before_cloud === $after_cloud,
		'Cloud suggestion calls changed the local title, body, draft status, or revision IDs before acceptance.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Cloud suggestion calls left title, body, draft status, and revision IDs unchanged (Cloud zero-write).' );

	$accepted_target_block = npcink_cloud_addon_wp_ai_editor_smoke_rewritten_paragraph_block( $rewrite_suggestion );
	$first_apply           = npcink_cloud_addon_wp_ai_editor_smoke_apply_accepted_suggestions(
		$post_id,
		$initial_title,
		$initial_content,
		$title_suggestion,
		$summary,
		$target_block,
		$accepted_target_block,
		$sentinel_block
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require( true === $first_apply['changed'], 'First explicit local acceptance unexpectedly returned a no-op.' );

	$after_first_apply = npcink_cloud_addon_wp_ai_editor_smoke_snapshot( $post_id );
	npcink_cloud_addon_wp_ai_editor_smoke_require( 'draft' === $after_first_apply['status'], 'Local acceptance changed the temporary post out of draft status.' );
	npcink_cloud_addon_wp_ai_editor_smoke_require( $title_suggestion === $after_first_apply['title'], 'Local acceptance did not apply the accepted title.' );
	npcink_cloud_addon_wp_ai_editor_smoke_require( $first_apply['content'] === $after_first_apply['content'], 'Local acceptance readback did not match the expected body.' );
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		1 === substr_count( $after_first_apply['content'], '"aiGeneratedSummary":true' ),
		'Local acceptance did not preserve exactly one generated summary block.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		1 === substr_count( $after_first_apply['content'], $accepted_target_block )
		&& false === strpos( $after_first_apply['content'], $target_block ),
		'Local acceptance did not replace exactly the selected whole core/paragraph block.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		1 === substr_count( $after_first_apply['content'], $sentinel_block ),
		'Local acceptance changed the non-target sentinel paragraph.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Local data-path acceptance applied title, one summary block, and only the selected whole core/paragraph block while preserving the sentinel and draft status.' );

	$second_apply = npcink_cloud_addon_wp_ai_editor_smoke_apply_accepted_suggestions(
		$post_id,
		$initial_title,
		$initial_content,
		$title_suggestion,
		$summary,
		$target_block,
		$accepted_target_block,
		$sentinel_block
	);
	$after_second_apply = npcink_cloud_addon_wp_ai_editor_smoke_snapshot( $post_id );
	npcink_cloud_addon_wp_ai_editor_smoke_require( false === $second_apply['changed'], 'Second local apply helper call was not a no-op.' );
	npcink_cloud_addon_wp_ai_editor_smoke_require(
		$after_first_apply === $after_second_apply,
		'Second local apply helper call changed post fields or created a revision.'
	);
	npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Second local apply helper call was a no-op and created no revision.' );
} catch ( Throwable $error ) {
	$failure = $error->getMessage();
} finally {
	if ( $post_id > 0 ) {
		try {
			$deleted = npcink_cloud_addon_wp_ai_editor_smoke_data(
				npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
					'DELETE',
					'/wp/v2/posts/' . $post_id,
					array(),
					array( 'force' => true )
				),
				'Temporary draft cleanup'
			);
			npcink_cloud_addon_wp_ai_editor_smoke_require(
				is_array( $deleted ) && true === ( $deleted['deleted'] ?? false ),
				'Temporary draft cleanup did not confirm permanent deletion.'
			);
			clean_post_cache( $post_id );
			npcink_cloud_addon_wp_ai_editor_smoke_require( null === get_post( $post_id ), 'Temporary draft still exists after cleanup.' );
			npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Deleted temporary draft ' . $post_id . ' and confirmed cleanup.' );
		} catch ( Throwable $cleanup_error ) {
			$cleanup_message = 'Cleanup failed: ' . $cleanup_error->getMessage();
			$failure         = '' === $failure ? $cleanup_message : $failure . ' ' . $cleanup_message;
		}
	}
}

if ( '' !== $failure ) {
	fwrite( STDERR, '[fail] ' . $failure . "\n" );
	exit( 1 );
}

npcink_cloud_addon_wp_ai_editor_smoke_ok( 'WordPress AI editor data-path smoke completed; it does not claim browser review or Core audit.' );
