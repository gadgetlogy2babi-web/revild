<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Revild_Meta_Box {

    public const META_NAME   = 'review_name';
    public const META_RATING = 'review_rating';
    public const META_BRAND  = 'review_brand';
    public const META_MODEL  = 'review_model';
    public const META_PROS   = 'review_pros';
    public const META_CONS   = 'review_cons';

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_post_meta' ], 10, 2 );
    }

    public function add_meta_box(): void {
        $post_types = [ 'post', 'page' ];

        foreach ( $post_types as $type ) {
            add_meta_box(
                'revild_review',
                __( 'レビュー', 'revild' ),
                [ $this, 'render' ],
                $type,
                'side',
                'low'
            );
        }
    }

    public function render( \WP_Post $post ): void {
        wp_nonce_field( 'revild_save', 'revild_nonce' );

        $name   = get_post_meta( $post->ID, self::META_NAME, true );
        $rating = get_post_meta( $post->ID, self::META_RATING, true );
        $brand  = get_post_meta( $post->ID, self::META_BRAND, true );
        $model  = get_post_meta( $post->ID, self::META_MODEL, true );
        $pros   = get_post_meta( $post->ID, self::META_PROS, true );
        $cons   = get_post_meta( $post->ID, self::META_CONS, true );
        ?>
        <p><small>
            <?php esc_html_e( '良い点/気になる点は JSON-LD に含まれますが記事本文には自動表示されません。', 'revild' ); ?>
        </small></p>

        <p>
            <label for="revild_name"><?php esc_html_e( 'レビュー対象（必須）', 'revild' ); ?></label><br>
            <input id="revild_name" type="text" name="revild_name" value="<?php echo esc_attr( $name ); ?>" style="width:100%;" />
        </p>

        <p>
            <label for="revild_rating"><?php esc_html_e( '評価（必須：1.0〜5.0、0.5刻み）', 'revild' ); ?></label><br>
            <select id="revild_rating" name="revild_rating" style="width:100%;">
                <option value="" hidden><?php esc_html_e( '選択してください', 'revild' ); ?></option>
                <?php
                for ( $i = 2; $i <= 10; $i++ ) {
                    $val = (string) ( $i / 2 );
                    echo '<option value="' . esc_attr( $val ) . '" ' . selected( (string) $rating, $val, false ) . '>' . esc_html( $val ) . '</option>';
                }
                ?>
            </select>
        </p>

        <hr style="margin:10px 0;">

        <p>
            <label for="revild_brand"><?php esc_html_e( 'ブランド/メーカー（任意）', 'revild' ); ?></label><br>
            <input id="revild_brand" type="text" name="revild_brand" value="<?php echo esc_attr( $brand ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( '例：SONY / Apple', 'revild' ); ?>" />
        </p>

        <p>
            <label for="revild_model"><?php esc_html_e( '型番/モデル（任意）', 'revild' ); ?></label><br>
            <input id="revild_model" type="text" name="revild_model" value="<?php echo esc_attr( $model ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( '例：WH-1000XM5', 'revild' ); ?>" />
        </p>

        <p>
            <label for="revild_pros"><?php esc_html_e( '良い点（任意：1行1項目）', 'revild' ); ?></label><br>
            <textarea id="revild_pros" name="revild_pros" rows="4" style="width:100%;" placeholder="<?php esc_attr_e( "例：\nノイキャンが強力\nバッテリーが長い", 'revild' ); ?>"><?php echo esc_textarea( $pros ); ?></textarea>
        </p>

        <p>
            <label for="revild_cons"><?php esc_html_e( '気になる点（任意：1行1項目）', 'revild' ); ?></label><br>
            <textarea id="revild_cons" name="revild_cons" rows="4" style="width:100%;" placeholder="<?php esc_attr_e( "例：\n価格が高い\nケースが大きめ", 'revild' ); ?>"><?php echo esc_textarea( $cons ); ?></textarea>
        </p>
        <?php
    }

    public function save_post_meta( int $post_id, \WP_Post $post ): void {
        if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! isset( $_POST['revild_nonce'] ) || ! wp_verify_nonce( $_POST['revild_nonce'], 'revild_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            'revild_name'   => [ 'meta' => self::META_NAME,   'sanitize' => 'sanitize_text_field' ],
            'revild_brand'  => [ 'meta' => self::META_BRAND,  'sanitize' => 'sanitize_text_field' ],
            'revild_model'  => [ 'meta' => self::META_MODEL,  'sanitize' => 'sanitize_text_field' ],
            'revild_pros'   => [ 'meta' => self::META_PROS,   'sanitize' => 'sanitize_textarea_field' ],
            'revild_cons'   => [ 'meta' => self::META_CONS,   'sanitize' => 'sanitize_textarea_field' ],
        ];

        foreach ( $fields as $key => $info ) {
            $value = isset( $_POST[ $key ] ) ? call_user_func( $info['sanitize'], wp_unslash( $_POST[ $key ] ) ) : '';
            $value !== '' ? update_post_meta( $post_id, $info['meta'], $value ) : delete_post_meta( $post_id, $info['meta'] );
        }

        // Rating requires validation
        $rating_raw = isset( $_POST['revild_rating'] ) ? wp_unslash( $_POST['revild_rating'] ) : '';
        $review_rating = 0.0;

        if ( $rating_raw !== '' && is_numeric( $rating_raw ) ) {
            $review_rating = (float) $rating_raw;
            $is_in_range  = ( $review_rating >= 1.0 && $review_rating <= 5.0 );
            $is_half_step = ( abs( ( $review_rating * 2 ) - round( $review_rating * 2 ) ) < 0.00001 );

            if ( ! $is_in_range || ! $is_half_step ) {
                $review_rating = 0.0;
            }
        }

        $review_rating > 0
            ? update_post_meta( $post_id, self::META_RATING, (string) $review_rating )
            : delete_post_meta( $post_id, self::META_RATING );
    }
}
