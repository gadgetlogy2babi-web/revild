<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Revild_Review_Box {

    public function __construct() {
        add_filter( 'the_content', [ $this, 'prepend_review_box' ], 1000 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ] );
    }

    public function enqueue_style(): void {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();
        $name    = get_post_meta( $post_id, Revild_Meta_Box::META_NAME, true );
        $rating  = get_post_meta( $post_id, Revild_Meta_Box::META_RATING, true );

        if ( $name === '' || $rating === '' ) {
            return;
        }

        wp_enqueue_style(
            'revild',
            REVILD_PLUGIN_URL . 'assets/css/revild.css',
            [],
            REVILD_VERSION
        );
    }

    public function prepend_review_box( string $content ): string {
        if ( is_admin() || is_feed() || ! is_singular() ) {
            return $content;
        }

        if ( ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        if ( str_contains( $content, 'revild-review-box' ) ) {
            return $content;
        }

        $post_id = get_queried_object_id();
        $name    = get_post_meta( $post_id, Revild_Meta_Box::META_NAME, true );
        $rating  = get_post_meta( $post_id, Revild_Meta_Box::META_RATING, true );

        if ( $name === '' || $rating === '' ) {
            return $content;
        }

        $rating_f = is_numeric( $rating ) ? (float) $rating : 0.0;
        if ( $rating_f <= 0.0 ) {
            return $content;
        }

        $brand     = get_post_meta( $post_id, Revild_Meta_Box::META_BRAND, true );
        $model     = get_post_meta( $post_id, Revild_Meta_Box::META_MODEL, true );
        $pros_text = get_post_meta( $post_id, Revild_Meta_Box::META_PROS, true );
        $cons_text = get_post_meta( $post_id, Revild_Meta_Box::META_CONS, true );

        $pros_lines = self::count_lines( $pros_text );
        $cons_lines = self::count_lines( $cons_text );
        $notes_ok   = ( $pros_lines + $cons_lines ) >= 2;

        // --- 表示する部品を組み立て ---
        $header_parts = [];
        $show_pros = false;
        $show_cons = false;

        // 商品名
        if ( Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_NAME ) ) {
            $header_parts[] = '<div class="revild-product-name">' . esc_html( $name ) . '</div>';
        }

        // ブランド名 / 型番（まとめて .revild-meta に）
        $meta_pieces = [];
        if ( $brand !== '' && Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_BRAND ) ) {
            $meta_pieces[] = esc_html( $brand );
        }
        if ( $model !== '' && Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_MODEL ) ) {
            $meta_pieces[] = esc_html( $model );
        }
        if ( ! empty( $meta_pieces ) ) {
            $header_parts[] = '<div class="revild-meta">' . implode( ' / ', $meta_pieces ) . '</div>';
        }

        // 評価（星）
        if ( Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_RATING ) ) {
            $display_value = rtrim( rtrim( (string) $rating_f, '0' ), '.' );
            $header_parts[] = '<div class="revild-rating">'
                . '<span class="revild-stars" aria-hidden="true">' . $this->rating_to_stars( $rating_f ) . '</span>'
                . '<span class="revild-rating-value">' . esc_html( $display_value ) . ' / 5.0</span>'
                . '</div>';
        }

        // 良い点（2項目ルール適用）
        $pros_html = '';
        if ( $notes_ok && $pros_lines > 0 && Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_PROS ) ) {
            $pros_html = '<div class="revild-pros">'
                . '<h4 class="revild-pros-title">' . esc_html__( '良い点', 'revild' ) . '</h4>'
                . '<ul>' . self::lines_to_li( $pros_text ) . '</ul>'
                . '</div>';
            $show_pros = true;
        }

        // 気になる点（2項目ルール適用）
        $cons_html = '';
        if ( $notes_ok && $cons_lines > 0 && Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_CONS ) ) {
            $cons_html = '<div class="revild-cons">'
                . '<h4 class="revild-cons-title">' . esc_html__( '気になる点', 'revild' ) . '</h4>'
                . '<ul>' . self::lines_to_li( $cons_text ) . '</ul>'
                . '</div>';
            $show_cons = true;
        }

        // 表示項目が0件ならボックス自体を出力しない
        if ( empty( $header_parts ) && ! $show_pros && ! $show_cons ) {
            return $content;
        }

        // --- HTML 組み立て ---
        $html = '<div class="revild-review-box">';

        if ( ! empty( $header_parts ) ) {
            $html .= '<div class="revild-review-header">' . implode( '', $header_parts ) . '</div>';
        }

        if ( $show_pros || $show_cons ) {
            $html .= '<div class="revild-pros-cons">' . $pros_html . $cons_html . '</div>';
        }

        $html .= '</div>';

        return $html . $content;
    }

    public static function count_lines( string $text ): int {
        $text = trim( $text );
        if ( $text === '' ) {
            return 0;
        }

        $count = 0;
        foreach ( preg_split( "/\r\n|\r|\n/", $text ) as $line ) {
            if ( trim( $line ) !== '' ) {
                $count++;
            }
        }
        return $count;
    }

    private static function lines_to_li( string $text ): string {
        $out = '';
        foreach ( preg_split( "/\r\n|\r|\n/", $text ) as $line ) {
            $line = trim( $line );
            if ( $line === '' ) {
                continue;
            }
            $out .= '<li>' . esc_html( $line ) . '</li>';
        }
        return $out;
    }

    private function rating_to_stars( float $rating ): string {
        $rating = max( 0.0, min( 5.0, $rating ) );

        $full  = (int) floor( $rating );
        $half  = ( abs( $rating - $full - 0.5 ) < 0.00001 ) ? 1 : 0;
        $blank = 5 - $full - $half;

        $html = str_repeat( $this->star_svg( '#ffb500', 'full' ), $full );

        if ( $half ) {
            $html .= $this->star_svg( '#ffb500', 'half' );
        }

        if ( $blank > 0 ) {
            $html .= str_repeat( $this->star_svg( '#cccccc', 'full' ), $blank );
        }

        return $html;
    }

    private function star_svg( string $color, string $mode = 'full' ): string {
        $grad_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'revild-grad-' ) : ( 'revild-grad-' . wp_rand() );

        $defs = '';
        $fill = $color;

        if ( $mode === 'half' ) {
            $defs = '<defs><linearGradient id="' . esc_attr( $grad_id ) . '">'
                  . '<stop offset="0%" stop-color="' . esc_attr( $color ) . '"/>'
                  . '<stop offset="50%" stop-color="' . esc_attr( $color ) . '"/>'
                  . '<stop offset="50%" stop-color="#cccccc"/>'
                  . '</linearGradient></defs>';
            $fill = 'url(#' . $grad_id . ')';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="revild-star">'
             . $defs
             . '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" fill="' . esc_attr( $fill ) . '"/>'
             . '</svg>';
    }
}
