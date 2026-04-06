<?php
/**
 * Plugin Name:       Floorboard Helper
 * Plugin URI:        https://floorboardai.com/
 * Description:       A collection of helpers to run FloorboardAI.com
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Keanan Koppenhaver
 * Author URI:        https://keanankoppenhaver.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       floorboard-helper
 * Domain Path:       /languages
 */

add_action( 'init', 'register_floorboard_acf_blocks' );
function register_floorboard_acf_blocks() {
    register_block_type( __DIR__ . '/blocks/drafts' );
}

/**
 * MCP Abilities for content management via Claude Code.
 */
add_action( 'init', 'floorboard_register_mcp_abilities' );
function floorboard_register_mcp_abilities() {
    if ( ! function_exists( 'wp_register_ability' ) ) {
        return;
    }

    wp_register_ability_category(
        'floorboard',
        array(
            'label'       => 'FloorboardAI',
            'description' => 'Content management abilities for FloorboardAI',
        )
    );

    // Get Posts
    wp_register_ability(
        'floorboard/get-posts',
        array(
            'label'       => 'View Posts and Pages',
            'description' => 'Retrieve posts or pages from the site with their content, categories, tags, and metadata.',
            'category'    => 'floorboard',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type' => array(
                        'type'        => 'string',
                        'description' => 'The post type to retrieve (post or page)',
                        'default'     => 'post',
                    ),
                    'count' => array(
                        'type'        => 'integer',
                        'description' => 'Number of posts to retrieve',
                        'default'     => 50,
                    ),
                    'status' => array(
                        'type'        => 'string',
                        'description' => 'Post status filter (publish, draft, any)',
                        'default'     => 'any',
                    ),
                ),
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'posts' => array(
                        'type'  => 'array',
                        'items' => array( 'type' => 'object' ),
                    ),
                ),
            ),
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'execute_callback' => 'floorboard_mcp_get_posts',
            'meta' => array(
                'mcp' => array( 'public' => true ),
                'annotations' => array(
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ),
            ),
        )
    );

    // Create Draft
    wp_register_ability(
        'floorboard/create-draft',
        array(
            'label'       => 'Create Draft Post or Page',
            'description' => 'Create a new draft post or page with Gutenberg blocks. Content should use Gutenberg block markup (e.g. <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->). If plain HTML or markdown is provided, it will be wrapped in appropriate Gutenberg blocks automatically.',
            'category'    => 'floorboard',
            'input_schema' => array(
                'type'       => 'object',
                'required'   => array( 'title', 'content' ),
                'properties' => array(
                    'title' => array(
                        'type'        => 'string',
                        'description' => 'The title of the post or page',
                    ),
                    'content' => array(
                        'type'        => 'string',
                        'description' => 'The content with Gutenberg block markup. Use <!-- wp:paragraph --><p>text</p><!-- /wp:paragraph --> for paragraphs, <!-- wp:heading {"level":2} --><h2>text</h2><!-- /wp:heading --> for headings, <!-- wp:list --><ul><li>item</li></ul><!-- /wp:list --> for lists.',
                    ),
                    'excerpt' => array(
                        'type'        => 'string',
                        'description' => 'A short excerpt or summary',
                    ),
                    'post_type' => array(
                        'type'        => 'string',
                        'description' => 'post or page (default: post)',
                        'default'     => 'post',
                    ),
                    'categories' => array(
                        'type'        => 'array',
                        'description' => 'Category names to assign (created if they do not exist)',
                        'items'       => array( 'type' => 'string' ),
                    ),
                    'tags' => array(
                        'type'        => 'array',
                        'description' => 'Tag names to assign (created if they do not exist)',
                        'items'       => array( 'type' => 'string' ),
                    ),
                ),
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'post_id' => array( 'type' => 'integer' ),
                    'edit_url' => array( 'type' => 'string' ),
                ),
            ),
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'execute_callback' => 'floorboard_mcp_create_draft',
            'meta' => array(
                'mcp' => array( 'public' => true ),
                'annotations' => array(
                    'readonly'    => false,
                    'destructive' => false,
                    'idempotent'  => false,
                ),
            ),
        )
    );

    // Update Post
    wp_register_ability(
        'floorboard/update-post',
        array(
            'label'       => 'Update Existing Post or Page',
            'description' => 'Update the title, content, excerpt, or status of an existing post or page.',
            'category'    => 'floorboard',
            'input_schema' => array(
                'type'       => 'object',
                'required'   => array( 'post_id' ),
                'properties' => array(
                    'post_id' => array(
                        'type'        => 'integer',
                        'description' => 'The ID of the post to update',
                    ),
                    'title' => array(
                        'type'        => 'string',
                        'description' => 'New title',
                    ),
                    'content' => array(
                        'type'        => 'string',
                        'description' => 'New content (Gutenberg block markup)',
                    ),
                    'excerpt' => array(
                        'type'        => 'string',
                        'description' => 'New excerpt',
                    ),
                    'status' => array(
                        'type'        => 'string',
                        'description' => 'New status: draft, publish, pending, private',
                    ),
                ),
            ),
            'output_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ),
                    'post_id' => array( 'type' => 'integer' ),
                ),
            ),
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'execute_callback' => 'floorboard_mcp_update_post',
            'meta' => array(
                'mcp' => array( 'public' => true ),
                'annotations' => array(
                    'readonly'    => false,
                    'destructive' => false,
                    'idempotent'  => true,
                ),
            ),
        )
    );
}

/**
 * Get posts callback.
 */
function floorboard_mcp_get_posts( $input ) {
    $post_type = isset( $input['post_type'] ) ? sanitize_text_field( $input['post_type'] ) : 'post';
    $count     = isset( $input['count'] ) ? intval( $input['count'] ) : 50;
    $status    = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'any';

    $posts = get_posts( array(
        'post_type'      => $post_type,
        'posts_per_page' => $count,
        'post_status'    => $status,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    $results = array();
    foreach ( $posts as $post ) {
        $results[] = array(
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'content'    => $post->post_content,
            'excerpt'    => $post->post_excerpt,
            'status'     => $post->post_status,
            'date'       => $post->post_date,
            'url'        => get_permalink( $post->ID ),
            'edit_url'   => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
            'categories' => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
            'tags'       => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
        );
    }

    return array( 'posts' => $results );
}

/**
 * Create draft callback.
 */
function floorboard_mcp_create_draft( $input ) {
    $title     = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    $content   = isset( $input['content'] ) ? $input['content'] : '';
    $excerpt   = isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '';
    $post_type = isset( $input['post_type'] ) ? sanitize_text_field( $input['post_type'] ) : 'post';

    if ( empty( $title ) || empty( $content ) ) {
        return new WP_Error( 'missing_fields', 'Title and content are required.' );
    }

    // If content doesn't contain Gutenberg block comments, wrap paragraphs in blocks
    if ( strpos( $content, '<!-- wp:' ) === false ) {
        $content = floorboard_convert_html_to_blocks( $content );
    }

    $post_id = wp_insert_post( array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_status'  => 'draft',
        'post_type'    => $post_type,
        'post_author'  => get_current_user_id(),
    ), true );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    // Handle categories
    if ( ! empty( $input['categories'] ) && $post_type === 'post' ) {
        $cat_ids = array();
        foreach ( $input['categories'] as $cat_name ) {
            $cat_name = sanitize_text_field( $cat_name );
            $term     = term_exists( $cat_name, 'category' );
            if ( ! $term ) {
                $term = wp_insert_term( $cat_name, 'category' );
            }
            if ( ! is_wp_error( $term ) ) {
                $cat_ids[] = is_array( $term ) ? intval( $term['term_id'] ) : intval( $term );
            }
        }
        if ( ! empty( $cat_ids ) ) {
            wp_set_post_categories( $post_id, $cat_ids );
        }
    }

    // Handle tags
    if ( ! empty( $input['tags'] ) && $post_type === 'post' ) {
        $tag_names = array_map( 'sanitize_text_field', $input['tags'] );
        wp_set_post_tags( $post_id, $tag_names );
    }

    return array(
        'success'  => true,
        'post_id'  => $post_id,
        'title'    => $title,
        'status'   => 'draft',
        'url'      => get_permalink( $post_id ),
        'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
    );
}

/**
 * Update post callback.
 */
function floorboard_mcp_update_post( $input ) {
    $post_id = isset( $input['post_id'] ) ? intval( $input['post_id'] ) : 0;

    if ( ! $post_id || ! get_post( $post_id ) ) {
        return new WP_Error( 'invalid_post', 'Post not found.' );
    }

    $post_data = array( 'ID' => $post_id );

    if ( isset( $input['title'] ) ) {
        $post_data['post_title'] = sanitize_text_field( $input['title'] );
    }
    if ( isset( $input['content'] ) ) {
        $content = $input['content'];
        if ( strpos( $content, '<!-- wp:' ) === false ) {
            $content = floorboard_convert_html_to_blocks( $content );
        }
        $post_data['post_content'] = $content;
    }
    if ( isset( $input['excerpt'] ) ) {
        $post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
    }
    if ( isset( $input['status'] ) ) {
        $post_data['post_status'] = sanitize_text_field( $input['status'] );
    }

    $result = wp_update_post( $post_data, true );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return array(
        'success'  => true,
        'post_id'  => $post_id,
        'title'    => get_the_title( $post_id ),
        'status'   => get_post_status( $post_id ),
        'url'      => get_permalink( $post_id ),
        'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
    );
}

/**
 * Convert plain HTML to Gutenberg blocks.
 *
 * Wraps headings, paragraphs, lists, and blockquotes in appropriate block comments.
 */
function floorboard_convert_html_to_blocks( $html ) {
    $blocks = array();

    // Split on double newlines or HTML block boundaries
    $html = trim( $html );

    // Use DOMDocument to parse properly
    $doc = new DOMDocument();
    $doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );

    $wrapper = $doc->getElementsByTagName( 'div' )->item( 0 );
    if ( ! $wrapper ) {
        return '<!-- wp:paragraph --><p>' . esc_html( $html ) . '</p><!-- /wp:paragraph -->';
    }

    foreach ( $wrapper->childNodes as $node ) {
        if ( $node->nodeType === XML_TEXT_NODE ) {
            $text = trim( $node->textContent );
            if ( ! empty( $text ) ) {
                $blocks[] = '<!-- wp:paragraph -->' . "\n" . '<p>' . esc_html( $text ) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
            }
            continue;
        }

        if ( $node->nodeType !== XML_ELEMENT_NODE ) {
            continue;
        }

        $inner = $doc->saveHTML( $node );
        $tag   = strtolower( $node->nodeName );

        switch ( $tag ) {
            case 'h1':
                $blocks[] = '<!-- wp:heading {"level":1} -->' . "\n" . $inner . "\n" . '<!-- /wp:heading -->';
                break;
            case 'h2':
                $blocks[] = '<!-- wp:heading -->' . "\n" . $inner . "\n" . '<!-- /wp:heading -->';
                break;
            case 'h3':
                $blocks[] = '<!-- wp:heading {"level":3} -->' . "\n" . $inner . "\n" . '<!-- /wp:heading -->';
                break;
            case 'h4':
            case 'h5':
            case 'h6':
                $level = intval( substr( $tag, 1 ) );
                $blocks[] = '<!-- wp:heading {"level":' . $level . '} -->' . "\n" . $inner . "\n" . '<!-- /wp:heading -->';
                break;
            case 'ul':
                $blocks[] = '<!-- wp:list -->' . "\n" . $inner . "\n" . '<!-- /wp:list -->';
                break;
            case 'ol':
                $blocks[] = '<!-- wp:list {"ordered":true} -->' . "\n" . $inner . "\n" . '<!-- /wp:list -->';
                break;
            case 'blockquote':
                $blocks[] = '<!-- wp:quote -->' . "\n" . $inner . "\n" . '<!-- /wp:quote -->';
                break;
            case 'p':
                $blocks[] = '<!-- wp:paragraph -->' . "\n" . $inner . "\n" . '<!-- /wp:paragraph -->';
                break;
            case 'pre':
                $blocks[] = '<!-- wp:code -->' . "\n" . $inner . "\n" . '<!-- /wp:code -->';
                break;
            default:
                $blocks[] = '<!-- wp:paragraph -->' . "\n" . '<p>' . $node->textContent . '</p>' . "\n" . '<!-- /wp:paragraph -->';
                break;
        }
    }

    return implode( "\n\n", $blocks );
}
