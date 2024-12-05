<?php

namespace Elementor\Modules\AtomicWidgets\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Style_Parser {
	const VALID_STATES = [
		'hover',
		'active',
		'focus',
		null,
	];

	private array $schema;
	private array $errors_bag = [];
	private $should_validate_id = true;

	public function __construct( array $schema ) {
		$this->schema = $schema;
	}

	public static function make( array $schema ): self {
		return new static( $schema );
	}

	public function without_id() {
		$this->should_validate_id = false;

		return $this;
	}

	/**
	 * @param array $style
	 * the style object to validate
	 *
	 * @return array{
	 *     0: bool,
	 *     1: array<string, mixed>,
	 *     2: array<string>
	 * }
	 */
	public function validate( array $style ): array {
		$validated_style = $style;

		if ( $this->should_validate_id && ( ! isset( $style['id'] ) || ! is_string( $style['id'] ) ) ) {
			$this->errors_bag[] = 'id';
		}

		if ( ! isset( $style['variants'] ) || ! is_array( $style['variants'] ) ) {
			$this->errors_bag[] = 'variants';
			unset ( $validated_style['variants'] );
			return [
				false,
				$validated_style,
				$this->errors_bag,
			];
		}
		foreach ( $style['variants'] as $variant_index => $variant ) {
			if ( ! isset( $variant['meta'] ) ) {
				$this->errors_bag[] = 'meta';
				continue;
			}

			$is_variant_valid = $this->validate_meta( $variant['meta'] );

			if ( $is_variant_valid ) {

				[, $validated_props, $variant_errors] = Props_Parser::make( $this->schema )->validate( $variant['props'] );
				$this->errors_bag = array_merge( $this->errors_bag, $variant_errors );

				if ( ! empty( $validated_props ) ) {
					$validated_style['variants'][ $variant_index ]['meta'] = $variant['meta'];
					$validated_style['variants'][ $variant_index ]['props'] = $validated_props;
				}
			}
		}

		$is_valid = empty( $this->errors_bag );
		return [
			$is_valid,
			$validated_style,
			$this->errors_bag,
		];
	}

	public function validate_meta( $meta ): bool {
		if ( ! is_array( $meta ) ) {
			$this->errors_bag[] = 'meta';
			return false;
		}

		if ( ! array_key_exists( 'state', $meta ) || ! in_array( $meta['state'], self::VALID_STATES, true ) ) {
			$this->errors_bag[] = 'meta';
			return false;
		}

		// TODO: Validate breakpoint based on the existing breakpoints in the system [EDS-528]
		if ( ! isset( $meta['breakpoint'] ) || ! is_string( $meta['breakpoint'] ) ) {
			$this->errors_bag[] = 'meta';
			return false;
		}

		return true;
	}

	/**
	 * @param array $style
	 * the style object to sanitize
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( array $style ): array {
		foreach ( $style['variants'] as $variant_index => $variant ) {
			$style['variants'][ $variant_index ]['props'] = Props_Parser::make( $this->schema )->sanitize( $variant['props'] );
		}

		return $style;
	}

	/**
	 * @param array $style
	 * the style object to parse
	 *
	 * @return array<string, mixed>
	 */
	public function parse( array $style ): array {
		[ , $validated_style ] = $this->validate( $style );

		foreach ( $validated_style['variants'] as $variant_index => $variant ) {
			$validated_style['variants'][ $variant_index ]['props'] = Props_Parser::make( $this->schema )->sanitize( $variant );
		}

		return $validated_style;
	}
}
