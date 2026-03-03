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

    // 表示トグル
    public const META_SHOW_NAME   = 'revild_show_name';
    public const META_SHOW_BRAND  = 'revild_show_brand';
    public const META_SHOW_MODEL  = 'revild_show_model';
    public const META_SHOW_RATING = 'revild_show_rating';
    public const META_SHOW_PROS   = 'revild_show_pros';
    public const META_SHOW_CONS   = 'revild_show_cons';

    // 記事単位の JSON-LD 停止トグル
    public const META_DISABLE_JSONLD = 'revild_disable_jsonld';

    /** デフォルト表示設定 */
    public const SHOW_DEFAULTS = [
        'revild_show_name'   => '0',
        'revild_show_brand'  => '0',
        'revild_show_model'  => '0',
        'revild_show_rating' => '1',
        'revild_show_pros'   => '1',
        'revild_show_cons'   => '1',
    ];

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_post_meta' ], 10, 2 );
    }

    public static function is_shown( int $post_id, string $meta_key ): bool {
        $value = get_post_meta( $post_id, $meta_key, true );
        if ( $value === '' ) {
            return ( self::SHOW_DEFAULTS[ $meta_key ] ?? '0' ) === '1';
        }
        return $value === '1';
    }

    public static function is_jsonld_disabled( int $post_id ): bool {
        return get_post_meta( $post_id, self::META_DISABLE_JSONLD, true ) === '1';
    }

    public function add_meta_box(): void {
        foreach ( [ 'post', 'page' ] as $type ) {
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

        $disable_jsonld = self::is_jsonld_disabled( $post->ID );
        ?>

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

        <p style="background:#f0f6fc;padding:6px 8px;border-radius:4px;margin:4px 0 0;">
            <small><?php esc_html_e( 'ℹ 良い点・気になる点は合計2項目以上でJSON-LDに反映されます。', 'revild' ); ?></small>
        </p>

        <hr style="margin:10px 0;">

        <p><strong><?php esc_html_e( '表示設定', 'revild' ); ?></strong></p>
        <p><small><?php esc_html_e( 'レビューボックスに表示する項目を選択してください。', 'revild' ); ?></small></p>

        <?php
        $toggles = [
            self::META_SHOW_NAME   => __( '商品名', 'revild' ),
            self::META_SHOW_BRAND  => __( 'ブランド名', 'revild' ),
            self::META_SHOW_MODEL  => __( '型番・モデル', 'revild' ),
            self::META_SHOW_RATING => __( '評価（星）', 'revild' ),
            self::META_SHOW_PROS   => __( '良い点', 'revild' ),
            self::META_SHOW_CONS   => __( '気になる点', 'revild' ),
        ];

        foreach ( $toggles as $key => $label ) :
            $checked = self::is_shown( $post->ID, $key );
            ?>
            <label style="display:block;margin:2px 0;">
                <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="0" />
                <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked ); ?> />
                <?php echo esc_html( $label ); ?>
            </label>
        <?php endforeach; ?>

        <p style="background:#f0f6fc;padding:6px 8px;border-radius:4px;margin:8px 0 0;">
            <small><?php esc_html_e( 'ℹ レビューボックスで非表示にした項目も、JSON-LDには出力されます。Googleのガイドラインでは、JSON-LDの内容がページ上のどこかに表示されている必要があります。記事本文に該当する情報が含まれていることを確認してください。', 'revild' ); ?></small>
        </p>

        <hr style="margin:10px 0;">

        <label style="display:block;margin:4px 0;">
            <input type="hidden" name="revild_disable_jsonld" value="0" />
            <input type="checkbox" name="revild_disable_jsonld" value="1" <?php checked( $disable_jsonld ); ?> />
            <?php esc_html_e( 'この記事の JSON-LD 出力を停止する', 'revild' ); ?>
        </label>
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

        // テキストフィールド
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

        // Rating
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

        // 表示トグル
        $toggle_keys = [
            self::META_SHOW_NAME,
            self::META_SHOW_BRAND,
            self::META_SHOW_MODEL,
            self::META_SHOW_RATING,
            self::META_SHOW_PROS,
            self::META_SHOW_CONS,
        ];

        foreach ( $toggle_keys as $key ) {
            $value = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '0';
            update_post_meta( $post_id, $key, $value === '1' ? '1' : '0' );
        }

        // JSON-LD 停止トグル
        $disable = isset( $_POST['revild_disable_jsonld'] ) ? sanitize_text_field( $_POST['revild_disable_jsonld'] ) : '0';
        update_post_meta( $post_id, self::META_DISABLE_JSONLD, $disable === '1' ? '1' : '0' );
    }
}
