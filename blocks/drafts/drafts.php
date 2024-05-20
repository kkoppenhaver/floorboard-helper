<?php
/**
 * Drafts Block template.
 *
 * @param array $block The block settings and attributes.
 */

$drafts_query = new WP_Query([
    'posts_per_page' => 3,  // Limit to 3 posts
    'post_status' => 'draft',  // Filter to only drafts
    'orderby' => 'date',  // Order by date
    'order' => 'DESC',  // In descending order
    'fields' => 'ids' // Get only the IDs
]);
?>

<div class="post-drafts">
    <h3>Coming soon to the blog</h3>
    <?php foreach( $drafts_query->posts as $draft_post ) : ?>
        <h4><?php echo get_the_title( $draft_post ); ?></h4>
    <?php endforeach; ?>
</div>

<?php