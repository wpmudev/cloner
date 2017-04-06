<?php

if ( ! function_exists( 'copier_add_membership_caps' ) ) {
	/**
	 * WPMU DEV Membership integration
	 * 
	 * @param type 
	 * @return type
	 */
	function copier_add_membership_caps( $blog_id, $user_id ) {
		if ( ! class_exists( 'membershipadmin' ) )
			return;

		$user_id = absint( $user_id );
		if ( ! $user_id )
			return;

		switch_to_blog( $blog_id );
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			restore_current_blog();
			return;
		}

		$user->add_cap('membershipadmin');
		$user->add_cap('membershipadmindashboard');
		$user->add_cap('membershipadminmembers');
		$user->add_cap('membershipadminlevels');
		$user->add_cap('membershipadminsubscriptions');
		$user->add_cap('membershipadmincoupons');
		$user->add_cap('membershipadminpurchases');
		$user->add_cap('membershipadmincommunications');
		$user->add_cap('membershipadmingroups');
		$user->add_cap('membershipadminpings');
		$user->add_cap('membershipadmingateways');
		$user->add_cap('membershipadminoptions');
		$user->add_cap('membershipadminupdatepermissions');
		update_user_meta( $user_id, 'membership_permissions_updated', 'yes');
		restore_current_blog();
	}
	add_action( 'wpmudev_copier-copy-options', 'copier_add_membership_caps', 10, 2 );

}
