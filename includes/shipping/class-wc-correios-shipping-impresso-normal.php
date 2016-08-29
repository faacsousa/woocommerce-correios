<?php
/**
 * Correios Impresso Normal shipping method.
 *
 * @package WooCommerce_Correios/Classes/Shipping
 * @since   3.0.0
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Impresso Normal shipping method class.
 */
class WC_Correios_Shipping_Impresso_Normal extends WC_Correios_Shipping {
	/**
	 * Service code.
	 * 20010 - Impresso Normal.
	 *
	 * @var string
	 */
	protected $code = '20010';

	/**
	 * Additional cost per kg or fraction.
	 * Cost based in 25/08/2016 from:
	 * http://www.correios.com.br/para-voce/consultas-e-solicitacoes/precos-e-prazos/servicos-nacionais_pasta/impresso-normal
	 *
	 */
	const ADDITIONAL_COST_PER_KG = 3.70;

	/**
	 * Weight limit for this shipping method.
	 * Value based in 25/08/2016 from:
	 * http://www.correios.com.br/para-voce/consultas-e-solicitacoes/precos-e-prazos/servicos-nacionais_pasta/impresso-normal
	 *
	 */
	const SHIPPING_METHOD_WEIGHT_LIMIT = 20000.000;

	/**
	 * Initialize Impresso Normal.
	 *
	 * @param int $instance_id Shipping zone instance.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'correios-impresso-normal';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Impresso Normal', 'woocommerce-correios' );
		$this->method_description = sprintf( __( '%s is a shipping method from Correios.', 'woocommerce-correios' ), $this->method_title );
		$this->more_link          = 'http://www.correios.com.br/para-voce/correios-de-a-a-z/impresso-normal';
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Define user set variables.
		$this->enabled        = $this->get_option( 'enabled' );
		$this->title          = $this->get_option( 'title' );
		$this->shipping_class = $this->get_option( 'shipping_class' );
		$this->registry_type  = $this->get_option( 'registry_type' );
		$this->fee            = $this->get_option( 'fee' );
		$this->receipt_notice = $this->get_option( 'receipt_notice' );
		$this->own_hands      = $this->get_option( 'own_hands' );
		$this->debug          = $this->get_option( 'debug' );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Save admin options.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Get shipping classes options.
	 *
	 * @return array
	 */
	protected function get_shipping_classes_options() {
		$shipping_classes = WC()->shipping->get_shipping_classes();
		$options          = array(
			'' => __( '-- Select a shipping class --', 'woocommerce-correios' ),
		);

		if ( ! empty( $shipping_classes ) ) {
			$options += wp_list_pluck( $shipping_classes, 'name', 'slug' );
		}

		return $options;
	}

	/**
	 * Get registry type options.
	 *
	 * @return array
	 */
	protected function get_registry_types_options() {
		$options          = array(
			''   => __( '-- Select a registry type --', 'woocommerce-correios' ),
			'RN' => __( 'Registro Nacional', 'woocommerce-correios' ),
			'RM' => __( 'Registro Módico', 'woocommerce-correios' ),
		);

		return $options;
	}

	/**
	 * Admin options fields.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-correios' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this shipping method', 'woocommerce-correios' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-correios' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-correios' ),
				'desc_tip'    => true,
				'default'     => $this->method_title,
			),
			'behavior_options' => array(
				'title'   => __( 'Behavior Options', 'woocommerce-correios' ),
				'type'    => 'title',
				'default' => '',
			),
			'shipping_class' => array(
				'title'       => __( 'Shipping Class', 'woocommerce-correios' ),
				'type'        => 'select',
				'description' => __( 'Select for which shipping class this method will be applied.', 'woocommerce-correios' ),
				'desc_tip'    => true,
				'default'     => '',
				'class'       => 'wc-enhanced-select',
				'options'     => $this->get_shipping_classes_options(),
			),
			'registry_type' => array(
				'title'       => __( 'Registry Type', 'woocommerce-correios' ),
				'type'        => 'select',
				'description' => __( 'Select for which registry type this method will be applied.', 'woocommerce-correios' ),
				'desc_tip'    => true,
				'default'     => '',
				'class'       => 'wc-enhanced-select',
				'options'     => $this->get_registry_types_options(),
				'default'     => 'RM',
			),
			'fee' => array(
				'title'       => __( 'Handling Fee', 'woocommerce-correios' ),
				'type'        => 'price',
				'description' => __( 'Enter an amount, e.g. 2.50, or a percentage, e.g. 5%. Leave blank to disable.', 'woocommerce-correios' ),
				'desc_tip'    => true,
				'placeholder' => '0.00',
				'default'     => '',
			),
			'optional_services' => array(
				'title'       => __( 'Optional Services', 'woocommerce-correios' ),
				'type'        => 'title',
				'description' => __( 'Use these options to add the value of each service provided by the Correios.', 'woocommerce-correios' ),
				'default'     => '',
			),
			'receipt_notice' => array(
				'title'       => __( 'Receipt Notice', 'woocommerce-correios' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable receipt notice', 'woocommerce-correios' ),
				'description' => __( 'This controls whether to add costs of the receipt notice service.', 'woocommerce-correios' ),
				'desc_tip'    => true,
				'default'     => 'no',
			),
			'own_hands' => array(
				'title'       => __( 'Own Hands', 'woocommerce-correios' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable own hands', 'woocommerce-correios' ),
				'description' => __( 'This controls whether to add costs of the own hands service', 'woocommerce-correios' ),
				'desc_tip'    => true,
				'default'     => 'no',
			),
			'testing' => array(
				'title'   => __( 'Testing', 'woocommerce-correios' ),
				'type'    => 'title',
				'default' => '',
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-correios' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-correios' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log %s events, such as WebServices requests.', 'woocommerce-correios' ), $this->method_title ) . $this->get_log_link(),
			),
		);
	}

	/**
	 * Get costs.
	 * Costs based in 25/08/2016 from:
	 * http://www.correios.com.br/para-voce/consultas-e-solicitacoes/precos-e-prazos/servicos-nacionais_pasta/impresso-normal
	 *
	 * @return array
	 */
	protected function get_costs() {
		return apply_filters( 'woocommerce_correios_impresso_normal_costs', array(
			'20' => 0.95,
			'50' => 1.45,
			'100' => 1.95,
			'150' => 2.35,
			'200' => 2.75,
			'250' => 3.20,
			'300' => 3.65,
			'350' => 4.05,
			'400' => 4.50,
			'450' => 4.95,
			'500' => 5.40,
			'550' => 5.75,
			'600' => 6.15,
			'650' => 6.55,
			'700' => 6.90,
			'750' => 7.25,
			'800' => 7.65,
			'850' => 8.05,
			'900' => 8.50,
			'950' => 8.85,
			'1000' => 9.25,
		), $this->id, $this->instance_id );
	}

	/**
	 * Get shipping cost.
	 *
	 * @param  array $package Order package.
	 *
	 * @return float
	 */
	protected function get_shipping_cost( $package ) {
		$cost                  = 0;
		$total_weight          = 0;
		$additional_weight_kgs = 0;
		$additional_weight_gs  = 0;

		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'Calculating cost for Impresso Normal' );
		}

		if ( '' === $this->shipping_class ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, 'Error: No shipping class has been configured!' );
			}

			return 0;
		}

		foreach ( $package['contents'] as $value ) {
			$product = $value['data'];
			$qty     = $value['quantity'];
			$weight  = 0;

			// Check if all or some items in the cart don't supports this shipping method.
			if ( $this->shipping_class !== $product->get_shipping_class() ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, 'One or all items in the cart do not supports the configured shipping class' );
				}

				return 0;
			}

			if ( $qty > 0 && $product->needs_shipping() ) {
				$weight = wc_get_weight( str_replace( ',', '.', $product->weight ), 'g' );

				if ( $qty > 1 ) {
					$weight *= $qty;
				}
			}

			$total_weight += $weight;
		}

		if ( $total_weight <= self::SHIPPING_METHOD_WEIGHT_LIMIT ) {
			if ( $total_weight > 1000 ) {
				//  Get the additional kgs over 1 kg  //
				$additional_weight_kgs = (int)($total_weight / 1000);

				//  To ensure it will get the maximum price for 1 kg  //
				$additional_weight_gs = 1000;
			} else {
				//  Get the cart weight itself  //
				$additional_weight_gs = $total_weight;
			}

			foreach ( $this->get_costs() as $cost_weights => $costs ) {
				if ( $additional_weight_gs <= $cost_weights ) {
					$cost = $costs;

					if ( $additional_weight_kgs > 0 ) {
						$cost += $additional_weight_kgs * self::ADDITIONAL_COST_PER_KG;
					}

					if ( $total_weight > WC_Correios_Shipping::REASONABLE_REGISTRY_WEIGHT_LIMIT || 'yes' === $this->own_hands || 'RN' === $this->registry_type ) {
						$cost += WC_Correios_Shipping::NATIONAL_REGISTRY_COST;
					} else {
						$cost += WC_Correios_Shipping::REASONABLE_REGISTRY_COST;
					}

					if ( 'yes' === $this->receipt_notice ) {
						$cost += WC_Correios_Shipping::RECEIPT_NOTICE_COST;
					}

					if ( 'yes' === $this->own_hands ) {
						$cost += WC_Correios_Shipping::OWN_HANDS_COST;
					}

					break;
				}
			}

			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, sprintf( 'Total cost for %sg and %s: %s', $total_weight, $this->registry_type, $cost ) );
			}
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, sprintf( 'The cart weight of %.3f exceeds the shipping method supported weight limit of %.3f', $total_weight, self::SHIPPING_METHOD_WEIGHT_LIMIT ) );
			}
		}

		return $cost;
	}

	/**
	 * Calculates the shipping rate.
	 *
	 * @param array $package Order package.
	 */
	public function calculate_shipping( $package = array() ) {
		// Check if valid to be calculeted.
		if ( '' === $package['destination']['postcode'] || 'BR' !== $package['destination']['country'] ) {
			return;
		}

		// Cost.
		$cost = $this->get_shipping_cost( $package );

		if ( 0 === $cost ) {
			return;
		}

		// Apply fees.
		$fee = $this->get_fee( $this->fee, $cost );

		// Create the rate and apply filters.
		$rate = apply_filters( 'woocommerce_correios_' . $this->id . '_rate', array(
			'id'    => $this->id . $this->instance_id,
			'label' => $this->title,
			'cost'  => $cost + $fee,
		), $this->instance_id );

		// Add rate to WooCommerce.
		$this->add_rate( $rate );
	}
}
