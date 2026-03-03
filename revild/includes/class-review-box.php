<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Revild_Review_Box {

    public function __construct() {
        add_filter( 'the_content', [ $this, 'insert_review_box' ], 1000 );
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

    public function insert_review_box( string $content ): string {
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

        // レビューボックス表示トグル
        if ( ! Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_BOX ) ) {
            return $content;
        }

        $name   = get_post_meta( $post_id, Revild_Meta_Box::META_NAME, true );
        $rating = get_post_meta( $post_id, Revild_Meta_Box::META_RATING, true );

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

        // --- 部品を組み立て ---
        $header_parts = [];
        $show_pros = false;
        $show_cons = false;

        // 商品名
        if ( Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_NAME ) ) {
            $header_parts[] = '<div class="revild-product-name">' . esc_html( $name ) . '</div>';
        }

        // ブランド / 型番
        if ( Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_META ) ) {
            $meta_pieces = [];
            if ( $brand !== '' ) {
                $meta_pieces[] = esc_html( $brand );
            }
            if ( $model !== '' ) {
                $meta_pieces[] = esc_html( $model );
            }
            if ( ! empty( $meta_pieces ) ) {
                $header_parts[] = '<div class="revild-meta">' . implode( ' / ', $meta_pieces ) . '</div>';
            }
        }

        // 評価（星）
        if ( Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_RATING ) ) {
            $display_value = rtrim( rtrim( (string) $rating_f, '0' ), '.' );
            $header_parts[] = '<div class="revild-rating">'
                . '<span class="revild-stars">' . self::rating_to_stars( $rating_f ) . '</span>'
                . '<span class="revild-rating-value">' . esc_html( $display_value ) . ' / 5.0</span>'
                . '</div>';
        }

        // 良い点
        if ( $notes_ok && $pros_lines > 0 && Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_PROS ) ) {
            $show_pros = true;
        }

        // 気になる点
        if ( $notes_ok && $cons_lines > 0 && Revild_Meta_Box::is_shown( $post_id, Revild_Meta_Box::META_SHOW_CONS ) ) {
            $show_cons = true;
        }

        // 表示項目が0件なら出力しない
        if ( empty( $header_parts ) && ! $show_pros && ! $show_cons ) {
            return $content;
        }

        // --- compact 判定 ---
        $has_pros_cons = $show_pros || $show_cons;
        $box_class     = 'revild-review-box';
        if ( ! $has_pros_cons ) {
            $box_class .= ' revild-review-box--compact';
        }

        $html = '<div class="' . esc_attr( $box_class ) . '">';

        if ( ! empty( $header_parts ) ) {
            $html .= '<div class="revild-review-header">' . implode( '', $header_parts ) . '</div>';
        }

        if ( $has_pros_cons ) {
            $html .= '<div class="revild-pros-cons">';

            if ( $show_pros ) {
                $html .= '<div class="revild-pros">';
                $html .= '<div class="revild-pros-title">' . esc_html__( '良い点', 'revild' ) . '</div>';
                $html .= '<ul>' . self::lines_to_li( $pros_text ) . '</ul>';
                $html .= '</div>';
            }

            if ( $show_cons ) {
                $html .= '<div class="revild-cons">';
                $html .= '<div class="revild-cons-title">' . esc_html__( '気になる点', 'revild' ) . '</div>';
                $html .= '<ul>' . self::lines_to_li( $cons_text ) . '</ul>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        // 挿入位置
        $position = Revild_Settings::get( 'insert_position' );
        if ( $position === 'after' ) {
            return $content . $html;
        }
        return $html . $content;
    }

    // ========== ユーティリティ ==========

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

    private static function rating_to_stars( float $rating ): string {
        $rating = max( 0.0, min( 5.0, $rating ) );
        $full   = (int) floor( $rating );
        $half   = ( abs( $rating - $full - 0.5 ) < 0.00001 ) ? 1 : 0;
        $blank  = 5 - $full - $half;

        $path = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
        $html = '';

        for ( $i = 0; $i < $full; $i++ ) {
            $html .= '<svg class="revild-star revild-star-full" viewBox="0 0 24 24"><path fill="currentColor" d="' . $path . '"/></svg>';
        }

        if ( $half ) {
            $grad_id = 'revild-half-' . wp_unique_id();
            $html .= '<svg class="revild-star revild-star-half" viewBox="0 0 24 24">'
                . '<defs><linearGradient id="' . esc_attr( $grad_id ) . '">'
                . '<stop offset="50%" stop-color="#f5a623"/><stop offset="50%" stop-color="#ddd"/>'
                . '</linearGradient></defs>'
                . '<path fill="url(#' . esc_attr( $grad_id ) . ')" d="' . $path . '"/></svg>';
        }

        for ( $i = 0; $i < $blank; $i++ ) {
            $html .= '<svg class="revild-star revild-star-empty" viewBox="0 0 24 24"><path fill="currentColor" d="' . $path . '"/></svg>';
        }

        return $html;
    }
}
