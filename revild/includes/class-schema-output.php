<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Revild_Schema_Output {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_jsonld' ] );
    }

    public function output_jsonld(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();

        $name   = get_post_meta( $post_id, Revild_Meta_Box::META_NAME, true );
        $rating = get_post_meta( $post_id, Revild_Meta_Box::META_RATING, true );

        if ( $name === '' || $rating === '' ) {
            return;
        }

        $rating_f = is_numeric( $rating ) ? (float) $rating : 0.0;
        if ( $rating_f <= 0.0 ) {
            return;
        }

        $brand = get_post_meta( $post_id, Revild_Meta_Box::META_BRAND, true );
        $model = get_post_meta( $post_id, Revild_Meta_Box::META_MODEL, true );
        $pros  = get_post_meta( $post_id, Revild_Meta_Box::META_PROS, true );
        $cons  = get_post_meta( $post_id, Revild_Meta_Box::META_CONS, true );

        $thumb_id  = get_post_thumbnail_id( $post_id );
        $img       = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'full' ) : false;
        $image_url = ( $img && ! empty( $img[0] ) ) ? $img[0] : null;

        $author_name    = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
        $publisher_name = get_bloginfo( 'name' );
        $date_published = get_the_date( 'c', $post_id );
        $date_modified  = get_the_modified_date( 'c', $post_id );
        $permalink      = get_permalink( $post_id );
        $post_title     = get_the_title( $post_id );

        // Product
        $data = [
            '@context' => 'https://schema.org/',
            '@type'    => 'Product',
            'name'     => (string) $name,
            'url'      => (string) $permalink,
        ];

        if ( $image_url ) {
            $data['image'] = [ (string) $image_url ];
        }

        if ( $brand !== '' ) {
            $data['brand'] = [ '@type' => 'Brand', 'name' => (string) $brand ];
        }

        if ( $model !== '' ) {
            $data['model'] = (string) $model;
        }

        // Review
        $review = [
            '@type' => 'Review',
            'name'  => (string) $post_title,
            'reviewRating' => [
                '@type'       => 'Rating',
                'ratingValue' => $rating_f,
                'bestRating'  => 5,
                'worstRating' => 1,
            ],
            'author' => [
                '@type' => 'Person',
                'name'  => (string) $author_name,
            ],
            'datePublished' => (string) $date_published,
            'publisher' => [
                '@type' => 'Organization',
                'name'  => (string) $publisher_name,
            ],
        ];

        if ( $date_modified ) {
            $review['dateModified'] = (string) $date_modified;
        }

        // positiveNotes / negativeNotes: 入力があれば常に含める
        $positive = $this->lines_to_itemlist( $pros );
        $negative = $this->lines_to_itemlist( $cons );

        if ( $positive ) {
            $review['positiveNotes'] = $positive;
        }

        if ( $negative ) {
            $review['negativeNotes'] = $negative;
        }

        $data['review'] = $review;

        echo "\n" . '<script type="application/ld+json">'
            . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            . '</script>' . "\n";
    }

    private function lines_to_itemlist( string $text ): ?array {
        $text = trim( $text );
        if ( $text === '' ) {
            return null;
        }

        $lines = preg_split( "/\r\n|\r|\n/", $text );
        $items = [];
        $pos   = 1;

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) {
                continue;
            }

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => $line,
            ];
        }

        if ( empty( $items ) ) {
            return null;
        }

        return [
            '@type'           => 'ItemList',
            'itemListElement' => $items,
        ];
    }
}
