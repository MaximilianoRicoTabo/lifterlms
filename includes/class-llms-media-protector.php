<?php
/**
 * LLMS_Media_Protector class
 *
 * @package LifterLMS/Classes
 *
 * @since [version]
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Media_Protector class.
 *
 * Allows uploaded media files to be protected from unauthorized downloading.
 *
 * WordPress uses the terms "media" and "attachment" interchangeably to describe uploaded files.
 * When a file is uploaded to WordPress, a post is created with type = 'attachment' and the file name and path relative
 * to the upload directory, normally `WP_CONTENT_DIR . '/uploads'`, is saved as '_wp_attached_file' metadata.
 *
 * Example of uploading a file:
 *
 *     $media = new LLMS_Media_Protector( '/social-learning' );
 *     $id    = $media->handle_upload( 'image', 0, 'llms_sl_authorize_media_view', $post_data );
 *
 * Example of protecting a file:
 *
 *     add_filter( 'llms_sl_authorize_media_view', array( $this, 'authorize_media_view' ), 10, 3 );
 *
 *     public function authorize_media_view( $is_authorized, $media_id, $url ) {
 *         $is_authorized = current_user_can( 'view_others_students' );
 *         return $is_authorized;
 *     }
 *
 * @since [version]
 *
 * @todo Add a rewrite rule that sends all 'uploads/llms-uploads/' requests to {@see LLMS_Media_Protector::serve_file()}.
 * @todo Add handling of HTTP range requests. See {@see https://datatracker.ietf.org/doc/html/rfc7233} and
 *       {@see https://developer.mozilla.org/en-US/docs/Web/HTTP/Range_requests}.
 */
class LLMS_Media_Protector {

	/**
	 * The meta key used to specify the filter hook name that authorizes viewing of a media file.
	 *
	 * @TODO Should the key be prefixed with an underscore '_' to denote private?
	 *
	 * @since [version]
	 *
	 * @var string
	 */
	public const AUTHORIZATION_FILTER_KEY = 'llms_media_authorization_filter';

	/**
	 * The name of the query parameter for whether the media image should be treated as an icon.
	 *
	 * @since [version]
	 *
	 * @var string
	 */
	public const QUERY_PARAMETER_ICON = 'llms_media_icon';

	/**
	 * The name of the query parameter for the media post ID.
	 *
	 * @since [version]
	 *
	 * @var string
	 */
	public const QUERY_PARAMETER_ID = 'llms_media_id';

	/**
	 * The name of the query parameter for the requested media image size.
	 *
	 * @since [version]
	 *
	 * @var string
	 */
	public const QUERY_PARAMETER_SIZE = 'llms_media_image_size';

	/**
	 * Serve the media file by reading and outputting it with the readfile() function.
	 *
	 * This is the least efficient way to serve a file because it uses a PHP process instead of a HTTP server thread.
	 * For small files or a small number of protected files on a page, this may not be noticeable. However, the server's
	 * configuration may need to be changed to allow more PHP processes to run, which will use more memory.
	 *
	 * @since [version]
	 *
	 * @var int
	 */
	public const SERVE_READ_FILE = 1;

	/**
	 * Serve the media file by redirecting the HTTP client with a "Location" header.
	 *
	 * This is the least secure way to serve a file because an unprotected URL is given to the HTTP client.
	 * It is unlikely, yet possible, that the URL could then be used by an unauthorized user to view the file.
	 *
	 * @since [version]
	 *
	 * @var int
	 */
	public const SERVE_REDIRECT = 2;

	/**
	 * Serve the media file by sending an "X-Sendfile" style header and let the HTTP server serve the file.
	 *
	 * This is the most efficient and most secure way to serve a file. It requires one of the following HTTP servers.
	 * - {@see https://httpd.apache.org/ Apache httpd} with {@see https://tn123.org/mod_xsendfile/ mod_xsendfile}
	 * - {@see http://cherokee-project.com/doc/other_goodies.html Cherokee}
	 * - {@see https://redmine.lighttpd.net/projects/lighttpd/wiki/X-LIGHTTPD-send-file lighttpd}
	 * - {@see https://www.nginx.com/resources/wiki/start/topics/examples/x-accel/ NGINX}
	 *
	 * @since [version]
	 *
	 * @var int
	 */
	public const SERVE_SEND_FILE = 3;

	/**
	 * An optional path added to the base upload path.
	 *
	 * If it is not empty, it will have a leading slash and will not have a trailing slash.
	 * Normally, the full path is `WP_CONTENT_DIR . "/uploads/$base/$additional/$year/$month/$file_name"`.
	 *
	 * @since [version]
	 *
	 * @var string
	 */
	protected $additional_upload_path = '';

	/**
	 * A base path for uploaded LifterLMS files.
	 *
	 * If it is not empty, it will have a leading slash and will not have a trailing slash.
	 * Normally, the full path is `WP_CONTENT_DIR . "/uploads/$base/$additional/$year/$month/$file_name"`.
	 *
	 * @since [version]
	 *
	 * @var string
	 */
	protected $base_upload_path = '';

	/**
	 * Set up this class.
	 *
	 * @since [version]
	 *
	 * @param string $additional_upload_path This path is added to the base upload path.
	 * @param string $base_upload_path       This path is appended to the WordPress upload path, which defaults to
	 *                                       `WP_CONTENT_DIR . '/uploads'` in {@see _wp_upload_dir()}.
	 * @return void
	 */
	public function __construct( string $additional_upload_path = '', string $base_upload_path = '/llms-uploads' ) {

		$this->set_base_upload_path( $base_upload_path );
		$this->set_additional_upload_path( $additional_upload_path );
	}

	/**
	 * Adds directives to .htaccess that allows Apache mod_xsendfile to be enabled and detected.
	 *
	 * Hooked to the {@see 'mod_rewrite_rules'} filter in {@see WP_Rewrite::mod_rewrite_rules()}
	 * by {@see LLMS_Media_Protector::register_callbacks()}.
	 *
	 * @since [version]
	 *
	 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
	 * @return string
	 */
	public function add_mod_xsendfile_directives( $rules ): string {

		$directives = <<<'NOWDOC'

# BEGIN LifterLMS mod_xsendfile
<IfModule mod_xsendfile.c>
  <Files *.php>
    XSendFile On
    SetEnv MOD_X_SENDFILE_ENABLED 1
  </Files>
</IfModule>
# END LifterLMS mod_xsendfile


NOWDOC;

		return $directives . $rules;
	}

	/**
	 * Adds an image to the media library to use when the current user is not allowed to view an image.
	 *
	 * @since [version]
	 *
	 * @global WP_Filesystem_Base $wp_filesystem Usually an instance of (@see WP_Filesystem_Direct}.
	 *
	 * @return int The post ID of the attachment image.
	 */
	protected function add_unauthorized_placeholder_image_to_media_library(): int {

		global $wp_filesystem;
		/** @var WP_Filesystem_Base $wp_filesystem */

		// Make sure that the file with WP_Filesystem() has been loaded.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$uploads = wp_get_upload_dir();
		$file    = $uploads['basedir'] . $this->base_upload_path . '/unauthorized-placeholder.png';
		$url     = $uploads['baseurl'] . $this->base_upload_path . '/unauthorized-placeholder.png';

		if ( false === $wp_filesystem->exists( $file ) ) {
			$result = $wp_filesystem->copy(
				LLMS_PLUGIN_DIR . 'assets/images/unauthorized-placeholder.png',
				$file,
				false,
				0644
			);
			if ( false === $result ) {
				return 0;
			}
		}

		$attach_id = wp_insert_attachment( array(
			'file'           => $file,
			'guid'           => $url,
			'post_name'      => 'llms-unauthorized-placeholder-image',
			'post_title'     => __( 'LifterLMS unauthorized placeholder image', 'lifterlms' ),
			'post_content'   => __(
				'This image is automatically added by LifterLMS and is the default image displayed to users ' .
				'that are not authorized to view a LifterLMS protected image.',
				'lifterlms'
			),
			'post_mime_type' => 'image/png',
			'meta_input'     => array(
				'_wp_attachment_image_alt' => __( 'Unauthorized to view this image.', 'lifterlms' ),
			),
		) );

		if ( 0 !== $attach_id ) {
			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once ABSPATH . 'wp-admin/includes/image.php';

			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
			wp_update_attachment_metadata( $attach_id, $attach_data );
		}

		return $attach_id;
	}

	/**
	 * Adds query parameters to a protected media URL.
	 *
	 * Hooked to the {@see 'wp_get_attachment_image_src'} filter in {@see wp_get_attachment_image_src()}
	 * by {@see LLMS_Media_Protector::register_callbacks()}.
	 *
	 * @since [version]
	 *
	 * @param array|false  $image    {
	 *     Array of image data, or boolean false if no image is available.
	 *
	 *     @type string $0 Image source URL.
	 *     @type int    $1 Image width in pixels.
	 *     @type int    $2 Image height in pixels.
	 *     @type bool   $3 Whether the image is a resized image.
	 * }
	 * @param int          $media_id The post ID of the image.
	 * @param string|int[] $size     Requested image size. Can be any registered image size name,
	 *                               or an array of width and height values in pixels (in that order).
	 * @param bool         $icon     Whether the image should be treated as an icon.
	 * @return array
	 */
	public function authorize_media_image_src( $image, $media_id, $size, $icon ) {

		$is_authorized = $this->is_authorized_to_view( get_current_user_id(), $media_id );
		if ( is_null( $is_authorized ) ) {
			// The media file is not protected.
			return $image;
		} elseif ( false === $is_authorized ) {
			// Get attachment ID of placeholder
			$media_id = $this->get_placeholder_id( $media_id );
			return wp_get_attachment_image_src( $media_id, $size );
		}

		$image[0] = add_query_arg(
			array(
				self::QUERY_PARAMETER_ID   => $media_id,
				self::QUERY_PARAMETER_SIZE => rawurlencode( is_array( $size ) ? json_encode( $size ) : $size ),
				self::QUERY_PARAMETER_ICON => $icon ? 1 : 0,
			),
			trailingslashit( home_url() )
		);

		return $image;
	}

	/**
	 * Returns the unchanged URL if the media file is not protected,
	 * else if the user is authorized, returns a URL that triggers {@see LLMS_Media_Protector::serve_file()} when requested,
	 * else returns a URL to a placeholder file.
	 *
	 * The result of this filter is cached for the duration of the current HTTP request.
	 *
	 * Hooked to the {@see 'wp_get_attachment_url'} filter in {@see wp_get_attachment_url()}
	 * by {@see LLMS_Media_Protector::register_callbacks()}.
	 *
	 * @since [version]
	 *
	 * @param string $url      URL for the given media file.
	 * @param int    $media_id The post ID of the media file.
	 * @return string
	 */
	public function authorize_media_url( $url, $media_id ): string {

		$is_authorized = $this->is_authorized_to_view( get_current_user_id(), $media_id );
		if ( true === $is_authorized ) {
			$url = add_query_arg(
				array(
					self::QUERY_PARAMETER_ID => $media_id
				),
				trailingslashit( home_url() )
			);
		} elseif ( false === $is_authorized ) {
			$url = $this->get_placeholder_url( $url, $media_id );
		}
		// If $is_authorized is null, do not change $url because it is unprotected.

		return $url;
	}

	/**
	 * Returns a path path with a leading slash and without a trailing slash, or if the given path is empty, an empty string.
	 *
	 * @since [version]
	 *
	 * @param string $path The path to be formatted.
	 * @return string An empty string or a path with a leading slash and without a trailing slash.
	 */
	protected function format_path( string $path ): string {

		if ( '' === $path ) {
			return $path;
		}

		// Add leading slash.
		if ( strpos( $path, '/' ) !== 0 ) {
			$path = '/' . $path;
		}

		// Strip trailing slash.
		$path = untrailingslashit( $path );

		return $path;
	}

	/**
	 * Returns the additional path that is added onto the base path.
	 *
	 * @size [version]
	 *
	 * @return string
	 */
	public function get_additional_upload_path(): string {

		return $this->additional_upload_path;
	}

	/**
	 * Returns the base upload path.
	 *
	 * @since [version]
	 *
	 * @return string
	 */
	public function get_base_upload_path(): string {

		return $this->base_upload_path;
	}

	/**
	 * Returns the absolute path to the media file in the upload directory.
	 *
	 * @since [version]
	 *
	 * @param int $media_id The media post ID.
	 * @return string
	 */
	public function get_media_path( int $media_id ): string {

		$upload_dir = wp_upload_dir();
		$file_name  = get_post_meta( $media_id, '_wp_attached_file', true );

		return $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $file_name;
	}

	/**
	 * Returns an absolute URL to the media file in the upload directory.
	 *
	 * @since [version]
	 *
	 * @param int $media_id The media post ID.
	 * @return string
	 */
	public function get_media_url( int $media_id ): string {

		$upload_dir = wp_upload_dir();
		$file_name  = get_post_meta( $media_id, '_wp_attached_file', true );

		return $upload_dir['baseurl'] . '/' . $file_name;
	}

	/**
	 * Returns the post ID of a placeholder media file.
	 *
	 * @since [version]
	 *
	 * @param int $media_id The post ID of the media file.
	 * @return int
	 */
	protected function get_placeholder_id( int $media_id ): int {

		//@TODO Prevent an infinite loop if the placeholder file somehow gets protected.

		$media = get_post( $media_id );
		switch ( $media->post_mime_type ) {
			case 'image/jpeg':
			case 'image/gif':
			case 'image/png':
			case 'image/bmp':
			case 'image/tiff':
			case 'image/webp':
			case 'image/x-icon':
			case 'image/heic':
				$media_id = $this->get_placeholder_image_id();
				break;
			default:
		}

		/**
		 * Allow the placeholder post ID to be filtered.
		 *
		 * @since [version]
		 *
		 * @param int $media_id The post ID of the media file.
		 */
		$media_id = apply_filters( 'llms_protected_media_placeholder_id', $media_id );

		return $media_id;
	}

	/**
	 * Returns the post ID of the LifterLMS unauthorized placeholder image.
	 *
	 * If it does not exist, it is added to the media library. If adding it to the media library fails, 0 is returned.
	 *
	 * @since [version]
	 *
	 * @return int
	 */
	protected function get_placeholder_image_id(): int {

		$query = new WP_Query( array(
			'pagename' => 'llms-unauthorized-placeholder-image',
		) );
		$posts = $query->get_posts();

		if ( empty( $posts ) ) {
			$media_id = $this->add_unauthorized_placeholder_image_to_media_library();
		} else {
			$media_id = $posts[0]->ID;
		}

		return $media_id;
	}

	/**
	 * Returns a URL to file that takes the place of a file that the user is not authorized to view.
	 *
	 * @since [version]
	 *
	 * @param string $media_url URL for the given media file.
	 * @param int    $media_id  The post ID of the media file.
	 * @return string
	 */
	protected function get_placeholder_url( string $media_url, int $media_id ): string {

		$placeholder_id  = $this->get_placeholder_id( $media_id );
		$placeholder_url = wp_get_attachment_url( $placeholder_id );

		/**
		 * Allow the placeholder URL to be filtered.
		 *
		 * @since [version]
		 *
		 * @param string $placeholder_url The URL of the placeholder media file.
		 * @param int    $placeholder_id  The post ID of the placeholder media file.
		 * @param string $media_url       The URL of the protected media file.
		 * @param int    $media_id        The post ID of protected the media file.
		 */
		$media_url = (string) apply_filters(
			'llms_not_authorized_placeholder_url',
			$placeholder_url,
			$placeholder_id,
			$media_url,
			$media_id
		);

		return $media_url;
	}

	/**
	 * Saves a file submitted from a POST request and creates an attachment post for it.
	 *
	 * @since [version]
	 *
	 * @param string $file_id   Index of the `$_FILES` array that the file was sent. Required.
	 * @param int    $post_id   The post ID of a post to attach the media item to. Required, but can
	 *                          be set to 0, creating a media item that has no relationship to a post.
	 * @param string $hook_name The name of the filter that will be applied by {@see LLMS_Media_Protector::is_authorized_to_view()}.
	 * @param array  $post_data Optional. Set attachment elements that are sent to {@see wp_insert_post()}.
	 *                          The defaults are set in {@see media_handle_upload()}.
	 * @param array  $overrides Optional. Override the {@see wp_handle_upload()} behavior.
	 * @return int|WP_Error Post ID of the media file or a WP_Error object on failure.
	 */
	public function handle_upload(
		string $file_id,
		int $post_id,
		string $hook_name,
		array $post_data = array(),
		array $overrides = array( 'test_form' => false )
	) {

		$post_data['meta_input'][ self::AUTHORIZATION_FILTER_KEY ] = $hook_name;
		add_filter( 'upload_dir', array( $this, 'upload_dir' ), 10, 1 );
		$media_id = media_handle_upload( $file_id, $post_id, $post_data, $overrides );
		remove_filter( 'upload_dir', array( $this, 'upload_dir' ), 10 );

		return $media_id;
	}

	/**
	 * Returns true if the user is authorized to view the requested media file, false if not authorized,
	 * or null if the media file is not protected.
	 *
	 * Authorization is handled by the callback added to the filter hook name given to {@see LLMS_Media_Protector::handle_upload()}.
	 *
	 * @since [version]
	 *
	 * @param int $user_id  The user ID.
	 * @param int $media_id The post ID of the media file.
	 * @return bool|null
	 */
	public function is_authorized_to_view( int $user_id, int $media_id ): ?bool {

		$authorization = wp_cache_get( $media_id, 'llms_media_authorization', false, $found );
		if ( $found ) {
			return $authorization;
		}

		$authorization_filter = get_post_meta( $media_id, self::AUTHORIZATION_FILTER_KEY, true );
		if ( ! $authorization_filter ) {
			wp_cache_add( $media_id, null, 'llms_media_authorization' );

			return null;
		}

		// The default is to allow WordPress super admins and LifterLMS managers to view all protected media files.
		// @todo Consider allowing users with the some of the 'students' capabilities.
		if ( is_super_admin( $user_id ) ) {
			$is_authorized = true;
		} else {
			$user          = wp_get_current_user();
			$is_authorized = in_array( 'llms_manager', $user->roles );
		}

		/**
		 * Allow the plugin that is protecting the file to authorize access to it.
		 *
		 * The default is to allow the user to view the file in case there is a not a callback for the authorization hook.
		 *
		 * @since [version]
		 *
		 * @param bool|null $is_authorized True if the user is authorized to view the media file, false if not authorized,
		 *                                 or null if the file is not protected.
		 * @param int       $media_id      The post ID of the media file.
		 * @param int       $user_id       The ID of the user wanting to view the media file.
		 */
		$is_authorized = apply_filters( $authorization_filter, $is_authorized, $media_id, $user_id );

		// Sanitize value.
		if ( ! is_bool( $is_authorized ) && ! is_null( $is_authorized ) ) {
			$is_authorized = (bool) $is_authorized;
		}

		wp_cache_add( $media_id, $is_authorized, 'llms_media_authorization' );

		return $is_authorized;
	}

	/**
	 * Changes the URLs for image attachments prepared for JavaScript.
	 *
	 * Hooked to the {@see 'wp_prepare_attachment_for_js'} filter in {@see wp_prepare_attachment_for_js()}
	 * by {@see LLMS_Media_Protector::register_callbacks()}.
	 *
	 * @since [version]
	 *
	 * @param array       $response   Array of prepared attachment data.
	 * @param WP_Post     $attachment Attachment object.
	 * @param array|false $meta       Array of attachment meta data, or false if there is none.
	 * @return array
	 */
	public function prepare_attachment_for_js( $response, $attachment, $meta ) {

		$is_authorized = $this->is_authorized_to_view( get_current_user_id(), $attachment->ID );
		if ( is_null( $is_authorized ) || ! array_key_exists( 'sizes', $response ) ) {
			return $response;
		}

		foreach ( $response['sizes'] as $size => &$size_meta ) {
			$size_meta['url'] = add_query_arg(
				array(
					self::QUERY_PARAMETER_ID   => $attachment->ID,
					self::QUERY_PARAMETER_SIZE => $size,
				),
				trailingslashit( home_url() )
			);
		}

		return $response;
	}

	/**
	 * Reads and outputs the file.
	 *
	 * This method sends the entire file and does not handle
	 * {@see https://developer.mozilla.org/en-US/docs/Web/HTTP/Range_requests HTTP range requests}.
	 *
	 * @since [version]
	 *
	 * @param string $file_name The file path and name.
	 * @return void
	 */
	protected function read_file( string $file_name ): void {

		// @TODO What about the web server time limit?
		ini_set( 'max_execution_time', '0' );

		// Tell the HTTP client that we do not handle HTTP range requests.
		header( 'Accept-Ranges: none' );

		// Turn off all output buffers to avoid running out of memory with large files.
		// @see https://www.php.net/readfile#refsect1-function.readfile-notes
		wp_ob_end_flush_all();

		$result = readfile( $file_name );
		if ( false === $result  ) {
			// Tell the HTTP client that something unspecific went wrong. readfile() outputs warnings to the PHP error log.
			header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error' );
		}
	}

	/**
	 * Registers the callback functions for action and filter hooks that allow this class to protect uploaded media files.
	 *
	 * @since [version]
	 *
	 * @return self
	 */
	public function register_callbacks(): self {

		add_filter( 'mod_rewrite_rules', array( $this, 'add_mod_xsendfile_directives' ) );

		if ( array_key_exists( self::QUERY_PARAMETER_ID, $_GET ) ) {
			add_action( 'init', array( $this, 'serve_file' ), 10 );
		} else {
			add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 99, 3 );
			add_filter( 'wp_get_attachment_image_src', array( $this, 'authorize_media_image_src' ), 10, 4 );
			add_filter( 'wp_get_attachment_url', array( $this, 'authorize_media_url' ), 10, 2 );
		}

		return $this;
	}

	/**
	 * Outputs an X-Sendfile or X-Accel-Redirect HTTP header which will instruct the HTTP server
	 * to send the file so that PHP doesn't have to.
	 *
	 * If none of the following HTTP servers are detected, {@see LLMS_Media_Protector::read_file()} is called.
	 * - {@see https://tn123.org/mod_xsendfile/ Apache mod_xsendfile}
	 * - {@see https://redmine.lighttpd.net/projects/lighttpd/wiki/Docs_ModCGI Lighttpd}
	 * - {@see https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/ NGINX}
	 * - {@see https://cherokee-project.com/doc/other_goodies.html#x-sendfile Cherokee}
	 *
	 * Add `$_SERVER['MOD_X_SENDFILE_ENABLED'] = '1';` in `wp-config.php` if web server auto-detection isn't working.
	 *
	 * IIS administrators may want to use {@see https://github.com/stakach/IIS-X-Sendfile-plugin}.
	 *
	 * @since [version]
	 *
	 * @param string $file_name The file path and name.
	 * @param int    $media_id  The post ID of the media file. Not used in this implementation, but here for consistency
	 *                          with the other "serve" methods and may be useful in an overriding this method.
	 * @return void
	 */
	protected function send_file( string $file_name, int $media_id ): void {

		$server_software = $_SERVER['SERVER_SOFTWARE'];

		if (
			'1' === $_SERVER['MOD_X_SENDFILE_ENABLED'] ||
			( function_exists( 'apache_get_modules' ) && in_array( 'mod_xsendfile', apache_get_modules(), true ) ) ||
			stristr( $server_software, 'cherokee' ) ||
			stristr( $server_software, 'lighttpd' )
		) {
			header( "X-Sendfile: $file_name" );

		} elseif ( stristr( $server_software, 'nginx' ) ) {
			/**
			 * @TODO Test NGINX.
			 * @see https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/
			 * @see https://woocommerce.com/document/digital-downloadable-product-handling/#nginx-setting
			 */
			// NGINX requires a URI without the server's root path.
			$nginx_file_name = substr( $file_name, strlen( ABSPATH ) - 1 );
			header( "X-Accel-Redirect: $nginx_file_name" );

		} else {
			$this->read_file( $file_name );
		}
	}

	/**
	 * Send headers for the download.
	 *
	 * @since [version]
	 *
	 * @param string $file_name The file path and name.
	 * @param int    $media_id  The post ID of the media file.
	 * @return void
	 */
	protected function send_headers( string $file_name, int $media_id ): void {

		$file_size = @filesize( $file_name ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ( ! $file_size ) {
			return;
		}

		$media_file   = get_post( $media_id );
		$content_type = $media_file->post_mime_type;

		header( "Content-Type: $content_type" );
		header( "Content-Length: $file_size" );
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}

	/**
	 * Sends a header that redirects the HTTP client to the media file's URL.
	 *
	 * @since [version]
	 *
	 * @param int $media_id The post ID of the media file.
	 * @return void
	 */
	protected function send_redirect( int $media_id ): void {

		$url = $this->get_media_url( $media_id );
		header( "Location: $url" );
	}

	/**
	 * Serves the requested media file to the HTTP client.
	 *
	 * This method calls the {@see llms_exit()} function and does not return.
	 *
	 * Hooked to the {@see 'init'} filter by {@see LLMS_Media_Protector::register_callbacks()}.
	 *
	 * @since [version]
	 *
	 * @return void
	 * @throws LLMS_Unit_Test_Exception_Exit
	 */
	public function serve_file() {

		$media_id   = llms_filter_input( INPUT_GET, self::QUERY_PARAMETER_ID, FILTER_SANITIZE_NUMBER_INT );
		$media_file = get_post( $media_id );

		// Validate that the attachment post exists.
		if ( is_null( $media_file ) ) {
			header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
			llms_exit();
		}

		$file_name = $this->get_media_path( $media_id );

		// Valid types are string, int[] and null.
		$size = llms_filter_input( INPUT_GET, self::QUERY_PARAMETER_SIZE, FILTER_SANITIZE_STRING );
		if ( false === $size ) {
			$size = null;
		} elseif ( is_string( $size ) && '[' === $size[0] ) {
			$size = json_decode( $size );
			// Sanitize untrusted external input.
			if ( isset( $size[0] ) ) {
				$size[0] = (int) $size[0];
			}
			if ( isset( $size[1] ) ) {
				$size[1] = (int) $size[1];
			}
		}

		$icon = null;
		if ( isset( $_GET[ self::QUERY_PARAMETER_ICON ] ) ) {
			$icon = (bool) $_GET[ self::QUERY_PARAMETER_ICON ];
		}

		// Optionally, use an alternate image size.
		if ( ! is_null( $size ) || ! is_null( $icon ) ) {
			$image     = wp_get_attachment_image_src( $media_id, $size, $icon );
			$file_name = dirname( $file_name ) . '/' . basename( $image[0] );
		}

		// Validate that the media file exists.
		if ( false === file_exists( $file_name ) ) {
			header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
			llms_exit();
		}

		// Is the user authorized to view the file?
		$is_authorized = $this->is_authorized_to_view( get_current_user_id(), $media_id );
		if ( false === $is_authorized ) {
			$content_type = $media_file->post_mime_type;
			if ( 0 === stripos( $content_type, 'image/' ) ) {
				$unauthorized_image_id = $this->get_placeholder_image_id();
				$image = wp_get_attachment_image_src( $unauthorized_image_id, $size );
				if ( false === $image ) {
					$file_name = LLMS_PLUGIN_DIR . 'assets/images/unauthorized-placeholder.png';
				} else {
					$file_name = $this->get_media_path( $unauthorized_image_id );
					$file_name = dirname( $file_name ) . '/' . basename( $image[0] );
				}

			} else {
				header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden' );
				llms_exit();
			}
		}

		/**
		 * Determine how the media file should be served.
		 *
		 * @since [version]
		 *
		 * @param string $serve_method One of the LLMS_Media_Protector::SERVE_X constants, {@see LLMS_Media_Protector::SERVE_SEND_FILE}.
		 * @param int    $media_id     The post ID of the media file.
		 */
		$serve_method = apply_filters( 'llms_media_serve_method', self::SERVE_SEND_FILE, $media_id );

		switch ( $serve_method ) {
			case self::SERVE_READ_FILE:
				$this->send_headers( $file_name, $media_id );
				$this->read_file( $file_name );
				break;
			case self::SERVE_SEND_FILE:
				$this->send_headers( $file_name, $media_id );
				$this->send_file( $file_name, $media_id );
				break;
			case self::SERVE_REDIRECT:
			default:
				$this->send_redirect( $media_id );
				break;
		}

		llms_exit();
	}

	/**
	 * Sanitizes and sets the additional upload path that is appended to the base upload path.
	 *
	 * @since [version]
	 *
	 * @param string $additional_upload_path
	 * @return self
	 */
	public function set_additional_upload_path( string $additional_upload_path ): self {

		$this->additional_upload_path = $this->format_path( $additional_upload_path );

		return $this;
	}

	/**
	 * Sanitizes and sets the base upload path relative to `WP_CONTENT_DIR . '/uploads'`.
	 *
	 * @since [version]
	 *
	 * @param string $base_upload_path
	 * @return self
	 */
	public function set_base_upload_path( string $base_upload_path ): self {

		$this->base_upload_path = $this->format_path( $base_upload_path );

		return $this;
	}

	/**
	 * Removes the authorization filter on the media file.
	 *
	 * @since [version]
	 *
	 * @param int    $media_id             The post ID of the media file.
	 * @param string $authorization_filter The hook name of the filter that authorizes users to view media files.
	 * @return bool True on success, false on failure.
	 */
	public function unprotect( int $media_id, string $authorization_filter ): bool {

		return delete_post_meta( $media_id, self::AUTHORIZATION_FILTER_KEY, $authorization_filter );
	}

	/**
	 * Filters the 'uploads' directory data.
	 *
	 * @since [version]
	 *
	 * @param array $uploads {
	 *     Array of information about the upload directory.
	 *
	 *     @type string       $path    Base directory and subdirectory or full path to upload directory.
	 *     @type string       $url     Base URL and subdirectory or absolute URL to upload directory.
	 *     @type string       $subdir  Subdirectory if uploads use year/month folders option is on.
	 *     @type string       $basedir Path without subdirectory.
	 *     @type string       $baseurl URL path without subdirectory.
	 *     @type string|false $error   False or error message.
	 * }
	 * @return array
	 */
	public function upload_dir( $uploads ) {

		$uploads['path'] = $uploads['basedir'] . $this->base_upload_path . $this->additional_upload_path . $uploads['subdir'];
		$uploads['url']  = $uploads['baseurl'] . $this->base_upload_path . $this->additional_upload_path . $uploads['subdir'];

		return $uploads;
	}
}
