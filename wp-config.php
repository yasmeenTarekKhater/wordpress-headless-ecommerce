<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'yasmeen' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1:3307' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'Qn2ib`L`!2)RXjs5uekod@-iEZC;m+QZr]e+ ,Tt&7MX`VT/sQu{10pI3[z9~v>e' );
define( 'SECURE_AUTH_KEY',  '+P6Wk!iN$Nb8v01hSAa3*c;R#aQqZjVXM)@h4||@4183EuQdTV)t$Hq}FiUhmZ+p' );
define( 'LOGGED_IN_KEY',    '+_40:^1J5_Z&)B+h]p(t,p1Xfz;JKhg,1;39uYA0pD@k*|VXNk*A_C0;Lv/[$c1r' );
define( 'NONCE_KEY',        'P(:dm<9DV59E&CBKTf-S$,Efd[UDk5cW>K}W9^@s]osBU*5<)SlG@:TP_29|k<Z|' );
define( 'AUTH_SALT',        '{-4h}+d+4$(=.u|;2kftJ~/*C^zJ-$M^QPGf1z~_DnSDWK5?(=JQK@o+BZQ:D^@+' );
define( 'SECURE_AUTH_SALT', '6GG6{w?b=Q0usU(F+MdH|@x;E<-h`Dw>^<HGQ-d*019dUxb_]{dl ?CU0pAeIhg)' );
define( 'LOGGED_IN_SALT',   'i8&*yr.|oS^-Ou_,U^BL-og-#+9zIm,.a{:M9N-d#<7WExKx*r4ly=U33R$fr!=&' );
define( 'NONCE_SALT',       '%)gPV281b1q^cwtE^</ICJk35yAn_p{u8Sr]j-0YNK[MjMbZrJYOa%x=?7|gxZfz' );
define('HEADLESS_FRONTEND_URL','http://localhost:5173');
/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );

/* Add any custom values between this line and the "stop editing" line. */
define('WP_DEBUG_LOG', true);


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
