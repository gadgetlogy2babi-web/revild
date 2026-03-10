<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Revild_Settings {

    public const OPTION_KEY = 'revild_settings';

    public const DEFAULTS = [
        'show_review_box'       => true,
        'show_product_name'     => false,
        'show_meta'             => false,
        'show_rating'           => true,
        'show_pros'             => true,
        'show_cons'             => true,
        'insert_position'       => 'before',
        'custom_css'            => '',
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_page' ] );
        add_action( 'admin_init', [ $this, 'register' ] );
        add_action( 'wp_head', [ $this, 'output_custom_css' ], 100 );
    }

    public static function get( ?string $key = null ) {
        $saved = get_option( self::OPTION_KEY, [] );
        $opts  = wp_parse_args( $saved, self::DEFAULTS );

        if ( $key !== null ) {
            return $opts[ $key ] ?? null;
        }
        return $opts;
    }

    public function add_page(): void {
        add_options_page(
            __( 'ReviLD 設定', 'revild' ),
            'ReviLD',
            'manage_options',
            'revild',
            [ $this, 'render_page' ]
        );
    }

    public function register(): void {
        register_setting( 'revild', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );

        // --- 表示設定 ---
        add_settings_section( 'revild_display', __( '表示設定', 'revild' ), '__return_false', 'revild' );

        $this->add_checkbox( 'show_review_box', __( 'レビューボックスを表示する', 'revild' ), 'revild_display',
            __( 'OFFにすると全記事でレビューボックスを非表示にします（JSON-LDは出力されます）。', 'revild' ) );
        $this->add_checkbox( 'show_product_name', __( '商品名を表示', 'revild' ), 'revild_display' );
        $this->add_checkbox( 'show_meta', __( 'ブランド名 / 型番を表示', 'revild' ), 'revild_display' );
        $this->add_checkbox( 'show_rating', __( '評価（星）を表示', 'revild' ), 'revild_display' );
        $this->add_checkbox( 'show_pros', __( '良い点を表示', 'revild' ), 'revild_display' );
        $this->add_checkbox( 'show_cons', __( '気になる点を表示', 'revild' ), 'revild_display' );

        // --- 挿入位置 ---
        add_settings_section( 'revild_position', __( '挿入位置', 'revild' ), '__return_false', 'revild' );

        add_settings_field(
            'revild_insert_position',
            __( 'レビューボックスの挿入位置', 'revild' ),
            [ $this, 'render_position_field' ],
            'revild',
            'revild_position'
        );

        // --- カスタムCSS ---
        add_settings_section( 'revild_custom_css', __( 'カスタムCSS', 'revild' ), '__return_false', 'revild' );

        add_settings_field(
            'revild_custom_css',
            __( 'カスタムCSS', 'revild' ),
            [ $this, 'render_custom_css_field' ],
            'revild',
            'revild_custom_css'
        );

    }

    private function add_checkbox( string $key, string $label, string $section, string $description = '' ): void {
        add_settings_field(
            'revild_' . $key,
            $label,
            function () use ( $key, $description ) {
                $opts    = self::get();
                $checked = ! empty( $opts[ $key ] );
                echo '<label>';
                echo '<input type="hidden" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="0" />';
                echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( $checked, true, false ) . ' />';
                echo '</label>';
                if ( $description !== '' ) {
                    echo '<p class="description">' . esc_html( $description ) . '</p>';
                }
            },
            'revild',
            $section
        );
    }

    public function render_position_field(): void {
        $current = self::get( 'insert_position' );
        ?>
        <label>
            <input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[insert_position]" value="before" <?php checked( $current, 'before' ); ?> />
            <?php esc_html_e( '記事の先頭', 'revild' ); ?>
        </label><br>
        <label>
            <input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[insert_position]" value="after" <?php checked( $current, 'after' ); ?> />
            <?php esc_html_e( '記事の末尾', 'revild' ); ?>
        </label>
        <?php
    }

    public function render_custom_css_field(): void {
        $css = self::get( 'custom_css' );
        ?>
        <textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_css]" rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $css ); ?></textarea>
        <p class="description"><?php esc_html_e( 'レビューボックスのデザインを変更できます。プラグインのCSSを上書きする形で適用されます。', 'revild' ); ?></p>
        <details style="margin-top:8px;">
            <summary style="cursor:pointer;color:#666;font-size:13px;"><?php esc_html_e( '利用可能なクラス一覧', 'revild' ); ?></summary>
            <pre style="background:#f6f7f7;padding:10px;margin-top:4px;font-size:12px;line-height:1.8;"><code>.revild-review-box … <?php esc_html_e( '外枠', 'revild' ); ?>

.revild-review-header … <?php esc_html_e( '商品名・評価エリア', 'revild' ); ?>

.revild-product-name … <?php esc_html_e( '商品名', 'revild' ); ?>

.revild-meta … <?php esc_html_e( 'ブランド / 型番', 'revild' ); ?>

.revild-rating … <?php esc_html_e( '評価', 'revild' ); ?>

.revild-stars … <?php esc_html_e( '星', 'revild' ); ?>

.revild-rating-value … <?php esc_html_e( '評価の数値', 'revild' ); ?>

.revild-pros-cons … <?php esc_html_e( '良い点・気になる点のコンテナ', 'revild' ); ?>

.revild-pros … <?php esc_html_e( '良い点', 'revild' ); ?>

.revild-cons … <?php esc_html_e( '気になる点', 'revild' ); ?></code></pre>
        </details>
        <?php
    }

    public function output_custom_css(): void {
        $css = self::get( 'custom_css' );
        if ( $css === '' ) {
            return;
        }
        echo '<style id="revild-custom-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
    }

    public function sanitize( $input ): array {
        $out = [];

        $bools = [ 'show_review_box', 'show_product_name', 'show_meta', 'show_rating', 'show_pros', 'show_cons' ];
        foreach ( $bools as $key ) {
            $out[ $key ] = ! empty( $input[ $key ] );
        }

        $out['insert_position'] = ( isset( $input['insert_position'] ) && $input['insert_position'] === 'after' ) ? 'after' : 'before';

        $out['custom_css'] = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';

        return $out;
    }

    public function render_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'ReviLD 設定', 'revild' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'revild' );
                do_settings_sections( 'revild' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
