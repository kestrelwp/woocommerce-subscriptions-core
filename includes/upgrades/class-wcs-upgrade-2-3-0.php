<?php
/**
 * Repair subscriptions that have been suspended in PayPal but not WooCommerce.
 *
 * If a subscription was suspended at PayPal.com when running Subscriptions v2.1.4 or newer (with the patch
 * from #1831), then it will not have been correctly suspended in WooCommerce.
 *
 * The root issue has been in v2.2.8, with #2199, but the existing subscriptions affected will still need
 * to be updated to ensure their status is correct.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Upgrades
 * @version  2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCS_Repair_Suspended_PayPal_Subscriptions extends WCS_Background_Updater {

	/**
	 * WC Logger instance for logging messages.
	 *
	 * @var WC_Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param WC_Logger $logger The WC Logger instance.
	 * @since 2.3.0
	 */
	public function __construct( WC_Logger $logger ) {
		$this->scheduled_hook = 'wcs_repair_subscriptions_suspended_paypal_not_woocommerce';
		$this->logger         = $logger;
	}

	/**
	 * Schedule the @see $this->scheduled_hook action to start repairing subscriptions in
	 * @see $this->time_limit seconds (60 seconds by default).
	 *
	 * @since 2.3.0
	 */
	public function schedule_repair() {
		$this->schedule_background_update();
	}

	/**
	 * Repair a subscription that was suspended in PayPal, but not suspended in WooCommerce.
	 *
	 * @param int $subscription_id The ID of a shop_subscription/WC_Subscription object.
	 */
	protected function update_item( $subscription_id ) {
		try {
			$subscription = wcs_get_subscription( $subscription_id );

			if ( ! $subscription ) {
				throw new Exception( 'Failed to instantiate subscription object' );
			}

			$subscription->update_status( 'on-hold', __( 'Subscription suspended by Database repair script. This subscription was suspended via PayPal.', 'woocommerce-subscriptions' ) );

			$this->log( sprintf( 'Subscription ID %d suspended from 2.3.0 PayPal database repair script.', $subscription_id ) );
		} catch ( Exception $e ) {
			$this->log( sprintf( '--- Exception caught repairing subscription %d - exception message: %s ---', $subscription_id, $e->getMessage() ) );
		}
	}

	/**
	 * Get a list of subscriptions to repair.
	 *
	 * @since 2.3.0
	 * @return array A list of subscription ids which may need to be repaired.
	 */
	protected function get_items_to_update() {
		return get_posts( array(
			'posts_per_page' => 20,
			'post_type'      => 'shop_subscription',
			'post_status'    => wcs_sanitize_subscription_status_key( 'active' ),
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_schedule_next_payment',
					'value'   => date( 'Y-m-d H:i:s', wcs_strtotime_dark_knight( '-3 days' ) ),
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
				array(
					'key'   => '_payment_method',
					'value' => 'paypal',
				),
				array(
					'key'     => '_paypal_subscription_id',
					'value'   => 'B-%',
					'compare' => 'NOT LIKE',
				),
			),
		) );
	}

	/**
	 * Add a message to the wcs-upgrade-subscriptions-paypal-suspended log
	 *
	 * @param string $message The message to be logged
	 * @since 2.3.0
	 */
	protected function log( $message ) {
		$this->logger->add( 'wcs-upgrade-subscriptions-paypal-suspended', $message );
	}
}
