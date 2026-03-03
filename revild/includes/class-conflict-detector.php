<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Revild_Conflict_Detector {

    private array $conflicts = [];

    private const CHECKS = [
        'Yoast SEO'   => [ 'type' => 'class', 'value' => 'WPSEO_Schema_Context' ],
        'Rank Math'    => [ 'type' => 'class', 'value' => 'RankMath\\Schema\\DB' ],
        'AIOSEO'       => [ 'type' => 'const', 'value' => 'AIOSEO_VERSION' ],
        'Schema Pro'   => [ 'type' => 'const', 'value' => 'SCHEMA_PRO_VERSION' ],
        'WooCommerce'  => [ 'type' => 'const', 'value' => 'WC_VERSION' ],
    ];

    public function __construct() {
        $this->detect();
    }

    private function detect(): void {
        foreach ( self::CHECKS as $name => $check ) {
            $found = match ( $check['type'] ) {
                'class' => class_exists( $check['value'] ),
                'const' => defined( $check['value'] ),
                default => false,
            };

            if ( $found ) {
                $this->conflicts[] = $name;
            }
        }
    }

    public function has_conflict(): bool {
        return ! empty( $this->conflicts );
    }

    public function get_conflicts(): array {
        return $this->conflicts;
    }

    public function register_admin_notice(): void {
        if ( ! $this->has_conflict() ) {
            return;
        }

        add_action( 'admin_notices', function () {
            $names = esc_html( implode( ', ', $this->conflicts ) );
            echo '<div class="notice notice-warning"><p>';
            printf(
                /* translators: %s: detected plugin names */
                esc_html__( '⚠️ %s を検出したため、ReviLD の Product スキーマ出力を停止しています。', 'revild' ),
                $names
            );
            echo '</p></div>';
        } );
    }
}
