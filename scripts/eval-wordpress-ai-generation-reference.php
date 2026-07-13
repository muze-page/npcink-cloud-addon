<?php
/**
 * Read-only local A/B evaluation for WordPress AI Site Knowledge references.
 *
 * Run with:
 * composer run eval:wp-ai-generation-reference
 *
 * Optional:
 * WP_AI_EVAL_POST_IDS=7520,5810,7957 composer run eval:wp-ai-generation-reference
 * WP_AI_EVAL_POST_LIMIT=15 composer run eval:wp-ai-generation-reference
 * WP_AI_EVAL_DELAY_MS=3200 composer run eval:wp-ai-generation-reference
 * WP_AI_EVAL_MIN_POSTS=1 composer run eval:wp-ai-generation-reference
 * WP_AI_EVAL_TASKS=title,summary,meta,classification composer run eval:wp-ai-generation-reference
 * WP_AI_EVAL_OUTPUT_JSON=/tmp/wp-ai-generation-reference-eval.json composer run eval:wp-ai-generation-reference
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "[fail] Run this file through WP-CLI eval-file inside a WordPress install.\n" );
	exit( 1 );
}

const NPCINK_WP_AI_EVAL_TASKS = array(
	'title'          => '/wp-abilities/v1/abilities/ai/title-generation/run',
	'excerpt'        => '/wp-abilities/v1/abilities/ai/excerpt-generation/run',
	'summary'        => '/wp-abilities/v1/abilities/ai/summarization/run',
	'meta'           => '/wp-abilities/v1/abilities/ai/meta-description/run',
	'classification' => '/wp-abilities/v1/abilities/ai/content-classification/run',
);

/**
 * Returns a bounded list of explicit or automatically selected public post ids.
 *
 * @return array<int,int>
 */
function npcink_wp_ai_eval_post_ids(): array {
	$raw   = trim( (string) ( getenv( 'WP_AI_EVAL_POST_IDS' ) ?: '' ) );
	$limit = max( 1, min( 30, absint( getenv( 'WP_AI_EVAL_POST_LIMIT' ) ?: 5 ) ) );
	if ( '' === $raw ) {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$ids = array();
		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			$content = trim( wp_strip_all_tags( strip_shortcodes( (string) ( $post->post_content ?? '' ) ) ) );
			if ( npcink_wp_ai_eval_length( $content ) < 200 ) {
				continue;
			}
			$ids[] = (int) $post->ID;
			if ( count( $ids ) >= $limit ) {
				break;
			}
		}
		return $ids;
	}

	$ids = array();
	foreach ( explode( ',', $raw ) as $value ) {
		$post_id = absint( trim( $value ) );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post || 'publish' !== (string) $post->post_status || 'post' !== (string) $post->post_type ) {
			continue;
		}
		$ids[] = $post_id;
		if ( count( $ids ) >= $limit ) {
			break;
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Writes an optional Eval Lab input artifact atomically.
 *
 * @param string $json JSON payload.
 * @return string Written path or an empty string when file output is disabled.
 */
function npcink_wp_ai_eval_write_artifact( string $json ): string {
	$path = trim( (string) ( getenv( 'WP_AI_EVAL_OUTPUT_JSON' ) ?: '' ) );
	if ( '' === $path ) {
		return '';
	}
	if ( ! str_starts_with( $path, '/' ) ) {
		$path = trailingslashit( getcwd() ?: ABSPATH ) . ltrim( $path, '/' );
	}
	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		throw new RuntimeException( 'Unable to create evaluation artifact directory.' );
	}
	$temp = $path . '.tmp-' . wp_generate_uuid4();
	if ( false === file_put_contents( $temp, $json . "\n", LOCK_EX ) || ! rename( $temp, $path ) ) {
		@unlink( $temp );
		throw new RuntimeException( 'Unable to write evaluation artifact.' );
	}
	return $path;
}

/**
 * Returns the requested task subset for a quick local smoke.
 *
 * @return array<string,string>
 */
function npcink_wp_ai_eval_tasks(): array {
	$requested = array_filter( array_map( 'trim', explode( ',', (string) ( getenv( 'WP_AI_EVAL_TASKS' ) ?: '' ) ) ) );
	if ( empty( $requested ) ) {
		return NPCINK_WP_AI_EVAL_TASKS;
	}
	return array_intersect_key( NPCINK_WP_AI_EVAL_TASKS, array_fill_keys( $requested, true ) );
}

/**
 * Sets the process-local generation-reference override.
 *
 * @param bool $enabled Desired state.
 * @return void
 */
function npcink_wp_ai_eval_set_reference( bool $enabled ): void {
	$GLOBALS['npcink_wp_ai_eval_reference_override'] = $enabled;
}

/**
 * Executes one WordPress ability through REST.
 *
 * @param string              $route Ability route.
 * @param array<string,mixed> $input Ability input.
 * @return array{status:int,data:mixed,error:string}
 */
function npcink_wp_ai_eval_run_ability( string $route, array $input ): array {
	static $last_call_at = 0.0;
	$delay_ms = max( 0, min( 10000, absint( getenv( 'WP_AI_EVAL_DELAY_MS' ) ?: 3200 ) ) );
	if ( $last_call_at > 0 && $delay_ms > 0 ) {
		$elapsed_ms = ( microtime( true ) - $last_call_at ) * 1000;
		if ( $elapsed_ms < $delay_ms ) {
			usleep( (int) ceil( ( $delay_ms - $elapsed_ms ) * 1000 ) );
		}
	}
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
	$response     = rest_do_request( $request );
	$last_call_at = microtime( true );
	if ( is_wp_error( $response ) ) {
		return array(
			'status' => 500,
			'data'   => null,
			'error'  => $response->get_error_code() . ': ' . $response->get_error_message(),
		);
	}
	$status = (int) $response->get_status();
	$data   = $response->get_data();
	$error  = '';
	if ( 200 !== $status ) {
		$error = is_array( $data ) ? (string) ( $data['message'] ?? wp_json_encode( $data ) ) : (string) $data;
	}
	return array(
		'status' => $status,
		'data'   => $data,
		'error'  => substr( $error, 0, 300 ),
	);
}

/**
 * Extracts ordinary task text from an ability response.
 *
 * @param mixed $data Response data.
 * @return string
 */
function npcink_wp_ai_eval_text( $data ): string {
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
		$data['data']['result']['output_text'] ?? null,
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
 * Extracts classification labels.
 *
 * @param mixed $data Response data.
 * @return array<int,string>
 */
function npcink_wp_ai_eval_labels( $data ): array {
	if ( ! is_array( $data ) ) {
		return array();
	}
	$suggestions = $data['suggestions'] ?? $data['data']['result']['suggestions'] ?? $data['result']['suggestions'] ?? array();
	if ( ! is_array( $suggestions ) ) {
		return array();
	}
	$labels = array();
	foreach ( $suggestions as $suggestion ) {
		$label = is_string( $suggestion ) ? $suggestion : ( is_array( $suggestion ) ? (string) ( $suggestion['term'] ?? $suggestion['name'] ?? $suggestion['label'] ?? '' ) : '' );
		$label = trim( wp_strip_all_tags( $label ) );
		if ( '' !== $label ) {
			$labels[] = $label;
		}
	}
	return array_values( array_unique( $labels ) );
}

/**
 * Returns text length using multibyte support when available.
 *
 * @param string $text Text.
 * @return int
 */
function npcink_wp_ai_eval_length( string $text ): int {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
}

/**
 * Truncates local evaluation context without breaking multibyte text.
 *
 * @param string $text Text.
 * @param int    $max_chars Maximum characters.
 * @return string
 */
function npcink_wp_ai_eval_truncate( string $text, int $max_chars ): string {
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
	if ( npcink_wp_ai_eval_length( $text ) <= $max_chars ) {
		return $text;
	}
	return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_chars, 'UTF-8' ) : substr( $text, 0, $max_chars );
}

/**
 * Builds bounded source context used only by the explicit local evaluator.
 *
 * @param WP_Post $post Post.
 * @return array<string,mixed>
 */
function npcink_wp_ai_eval_source_context( $post ): array {
	$taxonomies = array();
	foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
		$terms = wp_get_post_terms( (int) $post->ID, $taxonomy, array( 'fields' => 'names' ) );
		$taxonomies[ $taxonomy ] = is_wp_error( $terms ) || ! is_array( $terms )
			? array()
			: array_slice( array_values( array_unique( array_map( 'strval', $terms ) ) ), 0, 20 );
	}
	return array(
		'title'      => npcink_wp_ai_eval_truncate( wp_strip_all_tags( (string) $post->post_title ), 300 ),
		'excerpt'    => npcink_wp_ai_eval_truncate( wp_strip_all_tags( (string) $post->post_excerpt ), 1000 ),
		'content'    => npcink_wp_ai_eval_truncate( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 6000 ),
		'taxonomies' => $taxonomies,
	);
}

/**
 * Returns a numeric median.
 *
 * @param array<int,int> $values Values.
 * @return float
 */
function npcink_wp_ai_eval_median( array $values ): float {
	if ( empty( $values ) ) {
		return 0.0;
	}
	sort( $values, SORT_NUMERIC );
	$count = count( $values );
	$mid   = (int) floor( $count / 2 );
	return 1 === $count % 2 ? (float) $values[ $mid ] : ( $values[ $mid - 1 ] + $values[ $mid ] ) / 2;
}

/**
 * Builds site style and taxonomy baselines from public content.
 *
 * @return array<string,mixed>
 */
function npcink_wp_ai_eval_baselines(): array {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);
	$title_lengths   = array();
	$excerpt_lengths = array();
	$corpus          = array();
	foreach ( is_array( $posts ) ? $posts : array() as $post ) {
		$title = trim( wp_strip_all_tags( (string) ( $post->post_title ?? '' ) ) );
		$excerpt = trim( wp_strip_all_tags( (string) ( $post->post_excerpt ?? '' ) ) );
		if ( '' !== $title ) {
			$title_lengths[] = npcink_wp_ai_eval_length( $title );
			$corpus[]        = $title;
		}
		if ( '' !== $excerpt ) {
			$excerpt_lengths[] = npcink_wp_ai_eval_length( $excerpt );
			$corpus[]          = $excerpt;
		}
	}
	$terms = get_terms(
		array(
			'taxonomy'   => array( 'category', 'post_tag' ),
			'hide_empty' => false,
			'fields'     => 'names',
		)
	);
	$terms = is_wp_error( $terms ) || ! is_array( $terms ) ? array() : array_values( array_unique( array_map( 'strval', $terms ) ) );
	return array(
		'title_median_length'   => npcink_wp_ai_eval_median( $title_lengths ),
		'excerpt_median_length' => npcink_wp_ai_eval_median( $excerpt_lengths ),
		'corpus'                => array_slice( $corpus, 0, 200 ),
		'terms'                 => $terms,
	);
}

/**
 * Returns the maximum similar_text percentage against historical text.
 *
 * @param string            $text Output text.
 * @param array<int,string> $corpus Historical text.
 * @return float
 */
function npcink_wp_ai_eval_copy_similarity( string $text, array $corpus ): float {
	$maximum = 0.0;
	foreach ( $corpus as $reference ) {
		$percent = 0.0;
		similar_text( strtolower( $text ), strtolower( $reference ), $percent );
		$maximum = max( $maximum, $percent );
	}
	return round( $maximum, 2 );
}

/**
 * Returns numbers present in output but absent from current article content.
 *
 * @param string $output Output.
 * @param string $source Current article source.
 * @return array<int,string>
 */
function npcink_wp_ai_eval_novel_numbers( string $output, string $source ): array {
	preg_match_all( '/\d+(?:[.,]\d+)?/u', $output, $output_matches );
	preg_match_all( '/\d+(?:[.,]\d+)?/u', $source, $source_matches );
	return array_values( array_diff( array_unique( $output_matches[0] ?? array() ), array_unique( $source_matches[0] ?? array() ) ) );
}

/**
 * Removes local sample-provider decoration before evaluating generated content.
 *
 * @param string $output Raw output.
 * @return string
 */
function npcink_wp_ai_eval_normalized_output( string $output ): string {
	$normalized = preg_replace( '/^\[hosted:[^\]]+\]\s*/u', '', trim( $output ) );
	return is_string( $normalized ) ? trim( $normalized ) : trim( $output );
}

/**
 * Evaluates one variant for one post.
 *
	 * @param WP_Post             $post Post.
	 * @param bool                $reference_enabled Reference state.
 * @param array<string,mixed>  $baselines Site baselines.
 * @param array<string,string> $tasks Task routes.
	 * @return array<string,mixed>
 */
function npcink_wp_ai_eval_variant( $post, bool $reference_enabled, array $baselines, array $tasks ): array {
	npcink_wp_ai_eval_set_reference( $reference_enabled );
	$content = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
	$title   = trim( wp_strip_all_tags( (string) $post->post_title ) );
	$results = array();
	foreach ( $tasks as $task => $route ) {
		$input = in_array( $task, array( 'title', 'excerpt', 'summary' ), true )
			? array( 'content' => $content, 'context' => (string) $post->ID )
			: array( 'content' => $content, 'title' => $title, 'post_id' => (int) $post->ID );
		if ( 'classification' === $task ) {
			$input['taxonomy']       = 'category';
			$input['strategy']       = 'existing_only';
			$input['max_suggestions'] = 3;
		}
		$response = npcink_wp_ai_eval_run_ability( $route, $input );
		$labels   = 'classification' === $task ? npcink_wp_ai_eval_labels( $response['data'] ) : array();
		$text     = 'classification' === $task ? implode( ', ', $labels ) : npcink_wp_ai_eval_text( $response['data'] );
		$sample_output = 1 === preg_match( '/^\[hosted:[^\]]+\]/u', trim( $text ) );
		$evaluated_text = npcink_wp_ai_eval_normalized_output( $text );
		$target_length = 'title' === $task ? (float) $baselines['title_median_length'] : (float) $baselines['excerpt_median_length'];
		$term_keys = array_map( 'strtolower', (array) $baselines['terms'] );
		$matched_terms = array_filter( $labels, static function ( string $label ) use ( $term_keys ): bool {
			return in_array( strtolower( $label ), $term_keys, true );
		} );
		$results[ $task ] = array(
			'status'              => $response['status'],
			'error'               => $response['error'],
			'output'              => npcink_wp_ai_eval_truncate( $text, 800 ),
			'output_length'       => npcink_wp_ai_eval_length( $evaluated_text ),
			'length_distance'     => $target_length > 0 ? round( abs( npcink_wp_ai_eval_length( $evaluated_text ) - $target_length ) / $target_length, 4 ) : null,
			'copy_similarity_pct' => '' !== $evaluated_text ? npcink_wp_ai_eval_copy_similarity( $evaluated_text, (array) $baselines['corpus'] ) : 0.0,
			'novel_numbers'       => npcink_wp_ai_eval_novel_numbers( $evaluated_text, $content ),
			'boilerplate'         => 1 === preg_match( '/以下是|下面是|here are|作为.{0,8}AI/u', $evaluated_text ),
			'sample_output'       => $sample_output,
			'labels'              => $labels,
			'taxonomy_reuse_rate' => ! empty( $labels ) ? round( count( $matched_terms ) / count( $labels ), 4 ) : null,
		);
	}
	return $results;
}

wp_set_current_user( absint( getenv( 'WP_AI_SMOKE_USER' ) ?: 1 ) );
if ( ! current_user_can( 'edit_posts' ) ) {
	fwrite( STDERR, "[fail] Evaluation user cannot edit posts.\n" );
	exit( 1 );
}

$post_ids = npcink_wp_ai_eval_post_ids();
$minimum_posts = max( 1, min( 30, absint( getenv( 'WP_AI_EVAL_MIN_POSTS' ) ?: 3 ) ) );
$tasks         = npcink_wp_ai_eval_tasks();
if ( count( $post_ids ) < $minimum_posts ) {
	fwrite( STDERR, "[fail] The requested minimum number of valid published post ids is unavailable.\n" );
	exit( 1 );
}
if ( empty( $tasks ) ) {
	fwrite( STDERR, "[fail] No supported evaluation tasks were requested.\n" );
	exit( 1 );
}

$original_reference_enabled = Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled();
$stored_settings             = get_option( Npcink_Cloud_Addon_Settings::option_name(), array() );
$stored_settings             = is_array( $stored_settings ) ? $stored_settings : array();
$GLOBALS['npcink_wp_ai_eval_reference_override'] = $original_reference_enabled;
add_filter(
	'pre_option_' . Npcink_Cloud_Addon_Settings::option_name(),
	static function () use ( $stored_settings ): array {
		$settings = $stored_settings;
		$settings['site_knowledge_generation_reference_enabled'] = ! empty( $GLOBALS['npcink_wp_ai_eval_reference_override'] );
		return $settings;
	},
	PHP_INT_MAX
);
$baselines                  = npcink_wp_ai_eval_baselines();
$pairs                      = array();

try {
	foreach ( $post_ids as $index => $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			continue;
		}
		$order = 0 === $index % 2 ? array( false, true ) : array( true, false );
		$variants = array();
		foreach ( $order as $reference_enabled ) {
			$variants[ $reference_enabled ? 'reference' : 'baseline' ] = npcink_wp_ai_eval_variant( $post, $reference_enabled, $baselines, $tasks );
		}
		$pairs[] = array(
			'post_id'  => $post_id,
			'post_title_length' => npcink_wp_ai_eval_length( (string) $post->post_title ),
			'source_context' => npcink_wp_ai_eval_source_context( $post ),
			'variants' => $variants,
		);
	}
} finally {
	npcink_wp_ai_eval_set_reference( $original_reference_enabled );
}

$aggregate = array(
	'pairs'                    => count( $pairs ),
	'task_pairs'               => count( $pairs ) * count( $tasks ),
	'successful_outputs'       => 0,
	'non_boilerplate_outputs'  => 0,
	'style_wins'               => 0,
	'style_losses'             => 0,
	'style_ties'               => 0,
	'copy_guardrail_failures'  => 0,
	'novel_number_failures'    => 0,
	'sample_outputs'           => 0,
	'classification_reuse_lift_sum' => 0.0,
	'classification_reuse_comparisons' => 0,
	'by_task'                  => array(),
);
foreach ( $pairs as $pair ) {
	foreach ( array_keys( $tasks ) as $task ) {
		if ( ! isset( $aggregate['by_task'][ $task ] ) ) {
			$aggregate['by_task'][ $task ] = array(
				'pair_count'        => 0,
				'successful_pairs'  => 0,
			);
		}
		++$aggregate['by_task'][ $task ]['pair_count'];
		$baseline  = $pair['variants']['baseline'][ $task ];
		$reference = $pair['variants']['reference'][ $task ];
		foreach ( array( $baseline, $reference ) as $variant ) {
			$successful = 200 === $variant['status'] && '' !== $variant['output'];
			if ( $successful ) {
				++$aggregate['successful_outputs'];
				if ( ! $variant['boilerplate'] ) {
					++$aggregate['non_boilerplate_outputs'];
				}
				if ( $variant['copy_similarity_pct'] >= 80 ) {
					++$aggregate['copy_guardrail_failures'];
				}
				if ( ! empty( $variant['novel_numbers'] ) ) {
					++$aggregate['novel_number_failures'];
				}
				if ( $variant['sample_output'] ) {
					++$aggregate['sample_outputs'];
				}
			}
		}
		$pair_successful = 200 === $baseline['status'] && '' !== $baseline['output'] && 200 === $reference['status'] && '' !== $reference['output'];
		if ( $pair_successful ) {
			++$aggregate['by_task'][ $task ]['successful_pairs'];
		}
		if ( $pair_successful && 'classification' !== $task && null !== $baseline['length_distance'] && null !== $reference['length_distance'] ) {
			if ( $reference['length_distance'] < $baseline['length_distance'] ) {
				++$aggregate['style_wins'];
			} elseif ( $reference['length_distance'] > $baseline['length_distance'] ) {
				++$aggregate['style_losses'];
			} else {
				++$aggregate['style_ties'];
			}
		}
		if ( $pair_successful && 'classification' === $task && null !== $baseline['taxonomy_reuse_rate'] && null !== $reference['taxonomy_reuse_rate'] ) {
			$aggregate['classification_reuse_lift_sum'] += $reference['taxonomy_reuse_rate'] - $baseline['taxonomy_reuse_rate'];
			++$aggregate['classification_reuse_comparisons'];
		}
	}
}
$total_outputs = $aggregate['task_pairs'] * 2;
$style_comparisons = $aggregate['style_wins'] + $aggregate['style_losses'] + $aggregate['style_ties'];
$aggregate['successful_output_rate'] = $total_outputs > 0 ? round( $aggregate['successful_outputs'] / $total_outputs, 4 ) : null;
$aggregate['non_boilerplate_output_rate'] = $aggregate['successful_outputs'] > 0 ? round( $aggregate['non_boilerplate_outputs'] / $aggregate['successful_outputs'], 4 ) : null;
$aggregate['style_win_rate'] = $style_comparisons > 0 ? round( $aggregate['style_wins'] / $style_comparisons, 4 ) : null;
$aggregate['classification_reuse_lift_mean'] = $aggregate['classification_reuse_comparisons'] > 0 ? round( $aggregate['classification_reuse_lift_sum'] / $aggregate['classification_reuse_comparisons'], 4 ) : null;
unset( $aggregate['classification_reuse_lift_sum'], $aggregate['classification_reuse_comparisons'] );

$restored_reference_enabled = Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled();
$minimum_task_types          = 4;
$minimum_cases_per_task      = 3;
$collection_readiness        = array(
	'minimum_task_pairs'      => 15,
	'minimum_task_types'      => $minimum_task_types,
	'minimum_cases_per_task'  => $minimum_cases_per_task,
	'toggle_restored'         => $original_reference_enabled === $restored_reference_enabled,
	'task_pairs_gate_passed'  => $aggregate['task_pairs'] >= 15,
	'task_types_gate_passed'  => count( $tasks ) >= $minimum_task_types,
	'per_task_gate_passed'    => empty(
		array_filter(
			$aggregate['by_task'],
			static fn( array $item ): bool => (int) $item['pair_count'] < $minimum_cases_per_task
		)
	),
	'usable_pairs_gate_passed' => empty(
		array_filter(
			$aggregate['by_task'],
			static fn( array $item ): bool => (int) $item['successful_pairs'] < $minimum_cases_per_task
		)
	),
);
$collection_readiness['ready_for_blind_judging'] = $collection_readiness['toggle_restored']
	&& $collection_readiness['task_pairs_gate_passed']
	&& $collection_readiness['task_types_gate_passed']
	&& $collection_readiness['per_task_gate_passed']
	&& $collection_readiness['usable_pairs_gate_passed'];

$payload = array(
	'contract_version'             => 'wp_ai_generation_reference_eval.v2',
	'generated_at'                 => gmdate( 'c' ),
	'write_posture'                => 'read_only_evaluation',
	'request_delay_ms'             => max( 0, min( 10000, absint( getenv( 'WP_AI_EVAL_DELAY_MS' ) ?: 3200 ) ) ),
	'post_ids'                     => $post_ids,
	'minimum_posts'                => $minimum_posts,
	'tasks'                        => array_keys( $tasks ),
	'original_reference_enabled'   => $original_reference_enabled,
	'restored_reference_enabled'   => $restored_reference_enabled,
	'collection_readiness'         => $collection_readiness,
	'thresholds'                   => array(
		'style_win_rate'            => 0.6,
		'successful_output_rate'    => 1.0,
		'copy_similarity_pct_max'   => 80,
		'novel_number_failures_max' => 0,
		'sample_outputs_max'        => 0,
	),
	'baselines'                    => array(
		'title_median_length'   => $baselines['title_median_length'],
		'excerpt_median_length' => $baselines['excerpt_median_length'],
		'taxonomy_term_count'   => count( (array) $baselines['terms'] ),
	),
	'aggregate'                    => $aggregate,
	'pairs'                        => $pairs,
);
$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
if ( ! is_string( $json ) ) {
	fwrite( STDERR, "[fail] Unable to encode evaluation artifact.\n" );
	exit( 1 );
}
$written_path = npcink_wp_ai_eval_write_artifact( $json );
if ( '' !== $written_path ) {
	fwrite( STDERR, '[ok] Eval Lab input written to ' . $written_path . "\n" );
}
echo $json;
echo "\n";
