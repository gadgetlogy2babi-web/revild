<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Revild_Conflict_Detector {

    public const META_CONFLICT = '_revild_conflict_source';

    private string $conflict_source = '';
    private bool $buffering = false;

    /** ソース特定用：既知プラグインの検出条件 */
    private const PLUGIN_CHECKS = [
        'Yoast SEO'       => [ 'type' => 'class', 'value' => 'WPSEO_Schema_Context' ],
        'Rank Math'        => [ 'type' => 'class', 'value' => 'RankMath\\Schema\\DB' ],
        'All in One SEO'   => [ 'type' => 'const', 'value' => 'AIOSEO_VERSION' ],
        'Schema Pro'       => [ 'type' => 'const', 'value' => 'SCHEMA_PRO_VERSION' ],
        'WooCommerce'      => [ 'type' => 'const', 'value' => 'WC_VERSION' ],
    ];

    public function __construct() {
        if ( ! is_admin() ) {
            add_action( 'wp_head', [ $this, 'start_buffer' ], 0 );
            add_action( 'wp_head', [ $this, 'check_buffer' ], 9998 );
        }
    }

    /**
     * wp_head の最初期に出力バッファリングを開始
     */
    public function start_buffer(): void {
        if ( ! is_singular() ) {
            return;
        }
        $this->buffering = true;
        ob_start();
    }

    /**
     * wp_head の最後尾（ReviLD 出力直前）でバッファを解析
     */
    public function check_buffer(): void {
        if ( ! $this->buffering ) {
            return;
        }
        $this->buffering = false;

        $buffer = ob_get_clean();
        echo $buffer;

        $this->conflict_source = '';
        $post_id = get_queried_object_id();

        // <script type="application/ld+json"> ブロックを抽出して解析
        if ( preg_match_all(
            '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
            $buffer,
            $matches
        ) ) {
            foreach ( $matches[1] as $json_str ) {
                $data = json_decode( $json_str, true );
                if ( is_array( $data ) && self::contains_product_or_review( $data ) ) {
                    $this->conflict_source = $this->identify_source();
                    break;
                }
            }
        }

        // 結果をポストメタに保存（変化があった場合のみ書き込み）
        $stored = (string) get_post_meta( $post_id, self::META_CONFLICT, true );
        if ( $this->conflict_source !== '' && $stored !== $this->conflict_source ) {
            update_post_meta( $post_id, self::META_CONFLICT, $this->conflict_source );
        } elseif ( $this->conflict_source === '' && $stored !== '' ) {
            delete_post_meta( $post_id, self::META_CONFLICT );
        }
    }

    /**
     * JSON-LD データ内に Product または Review の @type が含まれるか再帰的にチェック
     */
    private static function contains_product_or_review( array $data ): bool {
        // @type を直接チェック
        $type = $data['@type'] ?? null;

        if ( is_string( $type ) && in_array( $type, [ 'Product', 'Review' ], true ) ) {
            return true;
        }
        if ( is_array( $type ) && ( in_array( 'Product', $type, true ) || in_array( 'Review', $type, true ) ) ) {
            return true;
        }

        // @graph 配列をチェック（Yoast SEO, Rank Math 等が使用）
        if ( ! empty( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
            foreach ( $data['@graph'] as $node ) {
                if ( is_array( $node ) && self::contains_product_or_review( $node ) ) {
                    return true;
                }
            }
        }

        // ルートが配列のケース [{"@type":"Product",...}]
        if ( isset( $data[0] ) && is_array( $data[0] ) ) {
            foreach ( $data as $item ) {
                if ( is_array( $item ) && self::contains_product_or_review( $item ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * アクティブなプラグインからソースを特定
     */
    private function identify_source(): string {
        foreach ( self::PLUGIN_CHECKS as $name => $check ) {
            $found = match ( $check['type'] ) {
                'class' => class_exists( $check['value'] ),
                'const' => defined( $check['value'] ),
                default => false,
            };
            if ( $found ) {
                return $name;
            }
        }
        return __( '他のプラグイン', 'revild' );
    }

    /**
     * 今回のページ表示で競合が検出されたか
     */
    public function has_conflict(): bool {
        return $this->conflict_source !== '';
    }

    /**
     * 競合元のプラグイン名
     */
    public function get_conflict_source(): string {
        return $this->conflict_source;
    }

    /**
     * 管理画面用：保存済みの競合情報を取得
     */
    public static function get_stored_conflict( int $post_id ): string {
        return (string) get_post_meta( $post_id, self::META_CONFLICT, true );
    }
}
