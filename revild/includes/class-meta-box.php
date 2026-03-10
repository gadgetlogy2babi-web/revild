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

    // 記事個別の表示上書き
    public const META_HAS_OVERRIDE   = 'revild_has_override';
    public const META_SHOW_NAME      = 'revild_show_name';
    public const META_SHOW_META      = 'revild_show_meta';
    public const META_SHOW_RATING    = 'revild_show_rating';
    public const META_SHOW_PROS      = 'revild_show_pros';
    public const META_SHOW_CONS      = 'revild_show_cons';
    public const META_DISABLE_JSONLD = 'revild_disable_jsonld';

    /** 表示トグルのメタキー → グローバル設定キー の対応 */
    private const TOGGLE_MAP = [
        'revild_show_name'       => 'show_product_name',
        'revild_show_meta'       => 'show_meta',
        'revild_show_rating'     => 'show_rating',
        'revild_show_pros'       => 'show_pros',
        'revild_show_cons'       => 'show_cons',
    ];

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_post_meta' ], 10, 2 );
        add_action( 'wp_ajax_revild_reset_override', [ $this, 'ajax_reset_override' ] );
    }

    /**
     * 記事個別 or グローバルの表示設定を返す
     */
    public static function is_shown( int $post_id, string $meta_key ): bool {
        if ( self::has_override( $post_id ) ) {
            return get_post_meta( $post_id, $meta_key, true ) === '1';
        }

        $global_key = self::TOGGLE_MAP[ $meta_key ] ?? null;
        if ( $global_key === null ) {
            return false;
        }
        return (bool) Revild_Settings::get( $global_key );
    }

    public static function has_override( int $post_id ): bool {
        return get_post_meta( $post_id, self::META_HAS_OVERRIDE, true ) === '1';
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

        $has_override   = self::has_override( $post->ID );
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

        <?php
        $toggles = [
            self::META_SHOW_NAME   => __( '商品名', 'revild' ),
            self::META_SHOW_META   => __( 'ブランド名 / 型番', 'revild' ),
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
            <small><?php esc_html_e( 'ℹ この記事の表示設定はグローバル設定を上書きします。グローバル設定は「設定 → ReviLD」から変更できます。', 'revild' ); ?></small>
        </p>
        <p style="background:#f0f6fc;padding:6px 8px;border-radius:4px;margin:4px 0 0;">
            <small><?php esc_html_e( 'ℹ レビューボックスで非表示にした項目も、JSON-LDには出力されます。Googleのガイドラインでは、JSON-LDの内容がページ上のどこかに表示されている必要があります。記事本文に該当する情報が含まれていることを確認してください。', 'revild' ); ?></small>
        </p>
        <p style="color:#666;font-size:12px;margin:8px 0 0;">
            <?php esc_html_e( '💡 ショートコード [revild] を本文中に記述すると、好きな位置にレビューボックスを表示できます。ショートコードを使用した場合、自動挿入は無効になります。', 'revild' ); ?>
        </p>

        <?php if ( $has_override ) : ?>
        <p style="margin:8px 0 0;">
            <button type="button" class="button button-link-delete" id="revild-reset-override" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                <?php esc_html_e( 'グローバル設定に戻す', 'revild' ); ?>
            </button>
        </p>
        <script>
            document.getElementById('revild-reset-override')?.addEventListener('click', function() {
                if (!confirm('<?php echo esc_js( __( 'この記事の個別表示設定を削除し、グローバル設定に戻しますか？', 'revild' ) ); ?>')) return;
                var fd = new FormData();
                fd.append('action', 'revild_reset_override');
                fd.append('post_id', this.dataset.postId);
                fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'revild_reset_override' ) ); ?>');
                fetch(ajaxurl, { method: 'POST', body: fd }).then(function() { location.reload(); });
            });
        </script>
        <?php endif; ?>

        <hr style="margin:10px 0;">

        <?php
        $conflict_source = Revild_Conflict_Detector::get_stored_conflict( $post->ID );
        if ( $conflict_source !== '' ) :
        ?>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:8px 10px;border-radius:2px;margin:0 0 8px;font-size:12px;">
            <?php
            printf(
                /* translators: %s: plugin name that outputs conflicting schema */
                esc_html__( '⚠️ %s が Product スキーマを出力しているため、ReviLD の JSON-LD 出力を自動停止しました。', 'revild' ),
                esc_html( $conflict_source )
            );
            ?>
        </div>
        <?php endif; ?>

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

        // 表示トグル（個別上書きとして保存）
        $toggle_keys = array_keys( self::TOGGLE_MAP );

        foreach ( $toggle_keys as $key ) {
            $value = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '0';
            update_post_meta( $post_id, $key, $value === '1' ? '1' : '0' );
        }

        update_post_meta( $post_id, self::META_HAS_OVERRIDE, '1' );

        // JSON-LD 停止トグル
        $disable = isset( $_POST['revild_disable_jsonld'] ) ? sanitize_text_field( $_POST['revild_disable_jsonld'] ) : '0';
        update_post_meta( $post_id, self::META_DISABLE_JSONLD, $disable === '1' ? '1' : '0' );
    }

    public function ajax_reset_override(): void {
        check_ajax_referer( 'revild_reset_override' );

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error();
        }

        delete_post_meta( $post_id, self::META_HAS_OVERRIDE );

        foreach ( array_keys( self::TOGGLE_MAP ) as $key ) {
            delete_post_meta( $post_id, $key );
        }

        wp_send_json_success();
    }
}
