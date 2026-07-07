<?php
/**
 * Local WP-CLI smoke gate for WordPress AI editor flows.
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
 * Prints a failing smoke assertion and exits.
 *
 * @param string $message Message.
 * @return void
 */
function npcink_cloud_addon_wp_ai_editor_smoke_fail( string $message ): void {
	fwrite( STDERR, '[fail] ' . $message . "\n" );
	exit( 1 );
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

	if ( ! $user || empty( $user->ID ) ) {
		npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Smoke user not found. Set WP_AI_SMOKE_USER to an administrator login or ID.' );
	}

	wp_set_current_user( (int) $user->ID );

	if ( ! current_user_can( 'edit_posts' ) ) {
		npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Smoke user cannot edit posts.' );
	}
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
 * Returns REST response data or fails with a concise error.
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
		npcink_cloud_addon_wp_ai_editor_smoke_fail( $label . ' returned HTTP ' . $status . ': ' . substr( $error, 0, 300 ) );
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
		$data['description'] ?? null,
		$data['description']['text'] ?? null,
		$data['summary'] ?? null,
		$data['title'] ?? null,
		$data['output_text'] ?? null,
		$data['data']['result']['description']['text'] ?? null,
		$data['data']['result']['output_text'] ?? null,
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
 * Extracts taxonomy suggestion labels from a content classification response.
 *
 * @param mixed $data REST response data.
 * @return list<string>
 */
function npcink_cloud_addon_wp_ai_editor_smoke_labels( $data ): array {
	$labels = array();
	if ( ! is_array( $data ) ) {
		return $labels;
	}

	$suggestions = $data['suggestions'] ?? $data['data']['result']['suggestions'] ?? $data['result']['suggestions'] ?? array();
	if ( ! is_array( $suggestions ) ) {
		return $labels;
	}

	foreach ( $suggestions as $suggestion ) {
		if ( is_string( $suggestion ) && '' !== trim( $suggestion ) ) {
			$labels[] = trim( $suggestion );
			continue;
		}
		if ( is_array( $suggestion ) ) {
			$label = (string) ( $suggestion['term'] ?? $suggestion['name'] ?? $suggestion['label'] ?? '' );
			if ( '' !== trim( $label ) ) {
				$labels[] = trim( $label );
			}
		}
	}

	return array_values( array_unique( $labels ) );
}

/**
 * Builds a summary group block like the WordPress AI editor plugin applies.
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

	return '<!-- wp:group {"className":"ai-summarization-summary","aiGeneratedSummary":true} -->' . "\n"
		. '<div class="wp-block-group ai-summarization-summary">' . "\n"
		. $inner
		. '</div>' . "\n"
		. '<!-- /wp:group -->';
}

npcink_cloud_addon_wp_ai_editor_smoke_set_user();

$source_content = trim(
	'<!-- wp:paragraph -->' . "\n"
	. '<p>这是一篇用于验证 WordPress AI 编辑器功能的本地测试文章。文章说明云端运行时如何通过 Npcink Cloud Addon 为 WordPress AI 插件提供标题、摘要、SEO 描述和段落改写建议，同时所有写入仍由本地 WordPress 编辑器中的管理员主动确认。</p>' . "\n"
	. '<!-- /wp:paragraph -->' . "\n\n"
	. '<!-- wp:paragraph -->' . "\n"
	. '<p>测试重点包括：摘要功能是否能在文章顶部插入摘要区块，SEO 描述面板是否能生成并应用描述，分类建议是否只作为本地可接受或忽略的建议展示。</p>' . "\n"
	. '<!-- /wp:paragraph -->'
);

$created = npcink_cloud_addon_wp_ai_editor_smoke_data(
	npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
		'POST',
		'/wp/v2/posts',
		array(
			'title'   => 'Npcink Cloud WordPress AI editor smoke ' . gmdate( 'Ymd-His' ),
			'content' => $source_content,
			'status'  => 'draft',
		)
	),
	'Draft creation',
	201
);

$post_id = absint( is_array( $created ) ? ( $created['id'] ?? 0 ) : 0 );
if ( $post_id <= 0 ) {
	npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Draft creation did not return a post ID.' );
}
npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Created local draft ' . $post_id . ' for editor smoke.' );

$summary = npcink_cloud_addon_wp_ai_editor_smoke_text(
	npcink_cloud_addon_wp_ai_editor_smoke_data(
		npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
			'POST',
			'/wp-abilities/v1/abilities/ai/summarization/run',
			array(
				'input' => array(
					'content' => $source_content,
					'context' => (string) $post_id,
				),
			)
		),
		'Editor summarization ability'
	)
);
if ( '' === $summary ) {
	npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Editor summarization ability returned empty text.' );
}
npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Editor summarization returned text for local draft application.' );

$meta_description = npcink_cloud_addon_wp_ai_editor_smoke_text(
	npcink_cloud_addon_wp_ai_editor_smoke_data(
		npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
			'POST',
			'/wp-abilities/v1/abilities/ai/meta-description/run',
			array(
				'input' => array(
					'content' => $source_content,
					'title'   => 'Npcink Cloud WordPress AI editor smoke',
					'post_id' => $post_id,
				),
			)
		),
		'Editor meta description ability'
	)
);
if ( '' === $meta_description || preg_match( '/以下是|下面是|here are/i', $meta_description ) ) {
	npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Editor meta description ability returned empty or boilerplate text.' );
}
npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Editor meta description returned direct suggestion text.' );

$classification = npcink_cloud_addon_wp_ai_editor_smoke_data(
	npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
		'POST',
		'/wp-abilities/v1/abilities/ai/content-classification/run',
		array(
			'input' => array(
				'content' => $source_content,
				'title'   => 'Npcink Cloud WordPress AI editor smoke',
				'post_id' => $post_id,
			),
		)
	),
	'Editor classification ability'
);
$labels         = npcink_cloud_addon_wp_ai_editor_smoke_labels( $classification );
if ( empty( $labels ) ) {
	npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Editor classification ability returned no suggestion labels.' );
}
npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Editor classification returned suggestion labels without applying terms: ' . implode( ', ', $labels ) . '.' );

$updated_content = npcink_cloud_addon_wp_ai_editor_smoke_summary_block( $summary ) . "\n\n" . $source_content;
$updated         = npcink_cloud_addon_wp_ai_editor_smoke_data(
	npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
		'POST',
		'/wp/v2/posts/' . $post_id,
		array(
			'content' => $updated_content,
			'status'  => 'draft',
			'meta'    => array(
				'ai_generated_summary'  => $summary,
				'wpai_meta_description' => $meta_description,
			),
		)
	),
	'Draft editor application'
);

if ( ! is_array( $updated ) || 'draft' !== (string) ( $updated['status'] ?? '' ) ) {
	npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Draft editor application did not preserve draft status.' );
}
npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Applied summary and SEO description to local draft without publishing.' );

$readback = npcink_cloud_addon_wp_ai_editor_smoke_data(
	npcink_cloud_addon_wp_ai_editor_smoke_rest_request(
		'GET',
		'/wp/v2/posts/' . $post_id,
		array(),
		array( 'context' => 'edit' )
	),
	'Draft readback'
);

$readback_content = is_array( $readback ) ? (string) ( $readback['content']['raw'] ?? $readback['content']['rendered'] ?? '' ) : '';
$readback_meta    = is_array( $readback ) && isset( $readback['meta'] ) && is_array( $readback['meta'] ) ? $readback['meta'] : array();
if (
	! is_array( $readback )
	|| 'draft' !== (string) ( $readback['status'] ?? '' )
	|| false === strpos( $readback_content, 'ai-summarization-summary' )
	|| $meta_description !== (string) ( $readback_meta['wpai_meta_description'] ?? '' )
) {
	npcink_cloud_addon_wp_ai_editor_smoke_fail( 'Draft readback did not preserve summary block, SEO meta, and draft status.' );
}

npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Read back local draft with summary block, SEO meta, and draft status intact.' );
npcink_cloud_addon_wp_ai_editor_smoke_ok( 'Editor smoke complete. Review draft ' . $post_id . ' in WordPress if visual inspection is needed.' );
