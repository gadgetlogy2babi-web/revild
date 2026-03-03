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
        'show_conflict_warning' => true,
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_page' ] );
        add_action( 'admin_init', [ $this, 'register' ] );
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

        // --- 競合検知 ---
        add_settings_section( 'revild_conflict', __( '競合検知', 'revild' ), '__return_false', 'revild' );

        $this->add_checkbox( 'show_conflict_warning', __( '競合プラグイン検出時に警告を表示する', 'revild' ), 'revild_conflict' );
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

    public function sanitize( $input ): array {
        $out = [];

        $bools = [ 'show_review_box', 'show_product_name', 'show_meta', 'show_rating', 'show_pros', 'show_cons', 'show_conflict_warning' ];
        foreach ( $bools as $key ) {
            $out[ $key ] = ! empty( $input[ $key ] );
        }

        $out['insert_position'] = ( isset( $input['insert_position'] ) && $input['insert_position'] === 'after' ) ? 'after' : 'before';

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
