<?php

class CleancodedCombineJS {

        /**
        *Variables
        */
	const nspace = 'wpcjp';
	const pname = 'Combine JS';
        protected $_plugin_file;
        protected $_plugin_dir;
        protected $_plugin_path;
        protected $_plugin_url;

	var $cachetime = '';
	var $create_cache = false;
	var $wpcjp_path = '';
	var $wpcjp_uri = '';
	var $js_domain = '';
	var $js_path = '';
	var $js_path_footer = '';
        var $js_uri = '';
	var $js_footer_uri = '';
        var $js_path_tmp = '';
	var $js_path_footer_tmp = '';
	var $settings_fields = array();
	var $settings_data = array();
	var $js_files_ignore = array( 'admin-bar.js' );
	var $js_handles_found = array();
	var $debug = false;

        /**
        *Constructor
        *
        *@return void
        *@since 0.1
        */
        function __construct() {}

	/**
        *Init function
        *
        *@return void
        *@since 0.1
        */
        function init() {

                // settings data -- leave at top of constructor

                $this->settings_data = unserialize( get_option( self::nspace . '-settings' ) );
		$this->cachetime = $this->get_settings_value( 'cachetime' );
		if ( ! @strlen( $this->cachetime ) ) $this->cachetime = 300;
		$this->js_domain = $this->get_settings_value( 'js_domain' );
		if ( ! @strlen( $this->js_domain ) ) $this->js_domain = get_option( 'siteurl' );

                // add ignore files

                $ignore_list = explode( "\n", $this->settings_data['ignore_files'] );
                foreach ( $ignore_list as $item ) {
                        $this->js_files_ignore[] = $item;
                }

		// set file path and uri

		$upload_dir = wp_upload_dir();
		$this->wpcjp_path = $upload_dir['basedir'] . '/' . self::nspace . '/';
		$this->wpcjp_uri = $upload_dir['baseurl'] . '/' . self::nspace . '/';

		// make sure wpcjp directory exists

		if ( ! file_exists( $this->wpcjp_path ) ) mkdir ( $this->wpcjp_path );

		// set var of js files that have been found
                //$this->js_handles_found = @unserialize( get_option( self::nspace . '-js-handles-found' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( &$this, 'add_settings_page' ), 30 );

			// settings fields

			$this->settings_fields = array(
							'legend_1' => array(
									'label' => __( 'General Settings', self::nspace ),
									'type' => 'legend'
									),
							'js_domain' => array(
									'label' => __( 'JavaScript Domain', self::nspace ),
									'type' => 'text',
									'default' => get_option( 'siteurl' )
									),
							'cachetime' => array(
									'label' => __( 'Cache Expiration', self::nspace ),
									'type' => 'select',
									'values' => array( '60' => '1 minute', '300' => '5 minutes', '900' => '15 minutes', '1800' => '30 minutes', '3600' => '1 hour' ),
									'default' => '300'
									),
							'htaccess_user_pw' => array(
                                                                        'label' => __( 'Username and Password (if behind htaccess authentication -- syntax: username:password)', self::nspace ),
                                                                        'type' => 'text',
                                                                        'default' => 'username:password'
                                                                        ),
							'ignore_files' => array(
                                                                        'label' => __( 'JS Files to Ignore (one per line)', self::nspace ),
                                                                        'type' => 'textarea'
                                                                        )
						);
		}
		elseif ( strstr( $_SERVER['REQUEST_URI'], 'wp-login' ) || strstr( $_SERVER['REQUEST_URI'], 'gf_page=' ) || strstr( $_SERVER['REQUEST_URI'], 'preview=' ) ) {}
		else {
			add_action( 'wp_print_scripts', array( $this, 'gather_js' ), 500 );
			add_action( 'wp_head', array( $this, 'install_combined_js' ), 500 );
			add_action( 'wp_footer', array( $this, 'install_combined_js_footer' ), 500 );

			/* get rid of browser prefetching of next page from link rel="next" tag */

			remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
			remove_action('wp_head', 'adjacent_posts_rel_link');
		}
        }

        /**
        *Cached expired
        *
        *@return boolean
        *@since 0.1
        */
	function cache_expired ( $path ) {
		$mtime = 0;
		if( file_exists( $path ) ) $mtime = @filemtime( $path );
		$now = time();
		$transpired = $now - $mtime;
		if ( $transpired > $this->cachetime ) return true;
		return false;
	}

        /**
        *File exists
        *
        *@return boolean
        *@since 0.1
        */
	function file_exists ( $src ) {
		if ( @strlen( $src ) && file_exists( ABSPATH . $src ) ) return true;
		return false;
	}

        /**
        *Get file from source
        *
        *@return string
        *@since 0.1
        */
	function get_file_from_src ( $src ) {
		$frags = explode( '/', $src );
		return $frags[count( $frags ) -1];
	}

        /**
        *Gather javascript
        *
        *@return void
        *@since 0.1
        */
        function gather_js () {

		if ( is_admin() ) return;

		$this->debug( 'Function gather_js' );

                global $wp_scripts;

		// loop through all scripts and store them in options

		$queue = $wp_scripts->queue;
                $wp_scripts->all_deps( $queue );
                $to_do = $wp_scripts->to_do;
                foreach ( $to_do as $key => $handle ) {
                        $js_src = $this->strip_domain( $wp_scripts->registered[$handle]->src );
                        $js_file = $this->get_file_from_src( $js_src );
                        if ( $wp_scripts->registered[$handle]->extra['data'] ) {
                                echo "<script type='text/javascript'>/* <![CDATA[ */ ";
                                echo $this->compress( $wp_scripts->registered[$handle]->extra['data'], $handle . ' l10n' ) . "\n";
                                echo " /* ]]> */ </script>";
                        }
                        elseif ( $wp_scripts->registered[$handle]->extra['l10n'] ) {
                                $vars = array();
                                foreach ( $wp_scripts->registered[$handle]->extra['l10n'][1] as $key => $val ) {
                                        $vars[] = "\t\t\t" . $key . ': "' . $val . '"';
                                }
				$extra = "<script type='text/javascript'>/* <![CDATA[ */ ";
				$extra .= "var " . $wp_scripts->registered[$handle]->extra['l10n'][0] . " = { " . implode( ",\n", $vars ) . " }; ";
				$extra .= " /* ]]> */ </script>";
				echo $this->compress( $extra, $handle . ' l10n' ) . "\n";
                        }
                        if( ! in_array( $js_file, $this->js_files_ignore ) && @strlen( $js_src ) && $this->file_exists( $js_src ) ) {
				$this->debug( '     -> found ' . $js_src );
				$this->js_handles_found[$handle] = $js_src;
				unset( $wp_scripts->to_do[$key] );
                        }
                }

                // get name of file based on md5 hash of js handles

                $file_name = self::nspace . '-' . md5( implode( '', array_keys( $this->js_handles_found ) ) );

                // set paths

                $this->js_path = $this->wpcjp_path . $file_name . '.js';
                $this->js_path_footer = $this->wpcjp_path . $file_name . '-footer.js';
                $this->js_uri = $this->wpcjp_uri . $file_name . '.js';
                $this->js_footer_uri = $this->wpcjp_uri . $file_name . '-footer.js';
                $this->js_path_tmp = $this->js_path . '.tmp';
                $this->js_path_footer_tmp = $this->js_path_footer . '.tmp';

		if ( $this->cache_expired( $this->js_path ) && $this->cache_expired( $this->js_path_tmp )
			&& $this->cache_expired( $this->js_path_footer ) && $this->cache_expired( $this->js_path_footer_tmp ) )  {
			$this->create_cache = true;
		}

		// loop through and unset scripts

		foreach ( $to_do as $key => $handle ) {
			$js_src = $this->strip_domain( $wp_scripts->registered[$handle]->src );
			$js_file = $this->get_file_from_src( $js_src );
			if( ! in_array( $js_file, $this->js_files_ignore )  && $this->file_exists( $js_src ) ) {
				wp_deregister_script( $handle );
			}
		}

		foreach ( $wp_scripts->queue as $key => $handle ) {
			if ( isset( $this->js_handles_found[$handle] ) ) {
				unset( $wp_scripts->queue[$key] );
			}
		}
        }

	/**
        *Debug function
        *
        *@return void
        *@since 0.1
        */
	function debug ( $msg ) {
		if ( $this->debug ) {
			error_log( 'DEBUG: ' . $msg );
		}
	}

        /**
        *Combine javascript
        *
        *@return void
        *@since 0.1
        */
	function combine_js () {

		$this->debug( 'Function combine_js' );

		// if no scripts found, return

		if ( ! @count( @array_keys( $this->js_handles_found ) ) ) {
			$this->debug( '     -> no handles found' );
			return;
		}

		// loop through found scripts and cache them to file system

		$header_content = $footer_content = '';
		foreach ( $this->js_handles_found as $handle => $js_src ) {
			$js_file = $this->get_file_from_src( $js_src );
			if( $this->file_exists( $js_src ) && ! in_array( $js_file, $this->js_files_ignore ) ) {
				if ( $this->create_cache && $this->cache_expired( $this->js_path ) ) {

					$this->debug( "     -> caching $handle" );

					// if file is a PHP script, pull content via curl

					$js_content = '';
					if ( preg_match( "/\.php/", $js_src ) ) {
						$js_content = $this->curl_file_get_contents ( $js_src );
					}
					else {
						$js_content = file_get_contents( ABSPATH . $js_src );
					}
					if ( $this->get_settings_value( $handle . '_position' ) == 'footer' ) {
						$footer_content .= "/* $handle -- $js_src */\n" . $this->compress( $js_content, $handle, $js_src );
					}
					else {
						$header_content .= "/* $handle -- $js_src */\n" . $this->compress( $js_content, $handle, $js_src );
					}
				}
			}
			else {
				error_log('SRC NOT FOUND: ' . ABSPATH . $js_src );
			}
		}

		// cache content to file system

		$this->cache_content( $header_content, $footer_content );

	}

        /**
        *Cache content
        *
        *@return void
        *@since 0.1
        */
	function cache_content ( $header_content, $footer_content ) {
		$this->debug( 'Function cache_content' );
		if ( @strlen( $header_content ) || @strlen( $footer_content ) ) {
			$header_file = 'js_path_tmp';
			$footer_file = 'js_path_footer_tmp';
			if ( @strlen( $header_content ) ) {
				$this->cache( $header_file, $header_content );
				update_option( self::nspace . '-js-use-header', 1 );
			}
			else update_option( self::nspace . '-js-use-header', 0 );
			if ( @strlen( $footer_content ) ) {
				$this->cache( $footer_file, $footer_content );
				update_option( self::nspace . '-js-use-footer', 1 );
			}
			else update_option( self::nspace . '-js-use-footer', 0 );
		}
	}

        /**
        *Write data to file system
        *
        *@return void
        *@since 0.1
        */
	function cache ( $tmp_file, $content ) {
		if ( ! file_exists( $this->$tmp_file ) ) {
			$fp = fopen( $this->$tmp_file, "w" );
			if ( flock( $fp, LOCK_EX ) ) { // do an exclusive lock
				fwrite( $fp, $content );
				flock( $fp, LOCK_UN ); // release the lock
			}
			fclose( $fp );
		}
	}

        /**
        *Get file content via curl
        *
        *@return string
        *@since 0.1
        */
	function curl_file_get_contents ( $src ) {
                $url = trim( $src );
		$url = preg_replace( "/http(|s):\/\//", "http://" . $this->get_settings_value( 'htaccess_user_pw' ) . "@", $url );
                $c = curl_init();
                curl_setopt( $c, CURLOPT_URL, $url );
                curl_setopt( $c, CURLOPT_FAILONERROR, false );
                curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $c, CURLOPT_VERBOSE, false );
                curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false );
                curl_setopt( $c, CURLOPT_SSL_VERIFYHOST, false );
                if( count( $header ) ) {
                        curl_setopt ( $c, CURLOPT_HTTPHEADER, $header );
                }
                $contents = curl_exec( $c );
                curl_close( $c );
		return $contents;
	}

        /**
        *Strip domain from path
        *
        *@return string
        *@since 0.1
        */
	function strip_domain( $src ) {
		$src = str_replace( array( 'http://', 'https://' ), array( '', '' ), $src );
		$frags = explode( '/', $src );
		array_shift( $frags );
		return implode( '/', $frags );
	}

        /**
        *Minify content
        *
        *@return string
        *@since 0.1
        */
	function compress( $content, $handle, $src='' ) {
		$this->debug( '     -> compress ' . $handle );
		$minify = true;
		if ( preg_match( "/(\-|\.)min/", $src ) ) {
			$minify = false;
		}
		if ( $minify ) {
			require_once $this->get_plugin_path() . '/classes/jsmin.php';
			return JSMin::minify( $content );
		}
		else return $content;
	}

        /**
        *Move temp file cache to actual file cache
        *
        *@return void
        *@since 0.1
        */
	function install_combined_js () {

		// combine javascript

		$this->combine_js();

		// move temp file to real path

		$this->debug( 'Function install_combined_js' );
		if ( $this->create_cache && file_exists( $this->js_path_tmp ) && $this->cache_expired( $this->js_path ) ) {
			$this->debug( '     -> move ' . $this->js_path_tmp . ", " . $this->js_path );
			@rename( $this->js_path_tmp, $this->js_path );
		}
		else {
			$this->debug( '     -> no header install' );
		}

		// add script tag

		$this->debug( 'Function add js to header' );
		if ( get_option( self::nspace . '-js-use-header' ) && file_exists( $this->js_path ) ) {
			$this->debug( '     -> add js tag to header' );
			echo "\t\t" . '<script type="text/javascript" src="' . str_replace( get_option( 'siteurl' ), $this->js_domain, $this->js_uri ) . '" charset="UTF-8"></script>' . "\n";
		}
	}

	/**
        *Move temp file cache to actual file cache
        *
        *@return void
        *@since 0.1
        */
        function install_combined_js_footer () {
		$this->debug( 'Function install_combined_js_footer' );
                if ( $this->create_cache && file_exists( $this->js_path_footer_tmp ) && $this->cache_expired( $this->js_path_footer ) ) {
                        $this->debug( '     -> move ' . $this->js_path_footer_tmp . ", " . $this->js_path_footer );
                        @rename( $this->js_path_footer_tmp, $this->js_path_footer );
                }
                else {
                        $this->debug( '     -> no footer install' );
                }

		// add script tag

		$this->debug( 'Function add_combined_js_footer' );
		if ( get_option( self::nspace . '-js-use-footer' ) && file_exists( $this->js_path_footer ) ) {
			$this->debug( '     -> add js tag to footer' );
			echo "\t\t" . '<script type="text/javascript" src="' . str_replace( get_option( 'siteurl' ), $this->js_domain, $this->js_footer_uri ) . '"></script>' . "\n";
		}
        }

        /**
        *Add settings page
        *
        *@return void
        *@since 0.1
        */
        function add_settings_page () {
                if ( current_user_can( 'manage_options' ) ) {
                        add_options_page( self::pname, self::pname, 'manage_options', self::nspace . '-settings', array( &$this, 'settings_page' ) );
                }
        }

        /**
        *Settings page
        *
        *@return void
        *@since 0.1
        */
        function settings_page () {
                if($_POST['wpcjp_update_settings']) {
                        $this->update_settings();
                }
                $this->show_settings_form();
        }

        /**
        *Show settings form
        *
        *@return void
        *@since 0.1
        */
        function show_settings_form () {
                include( $this->get_plugin_path() . '/views/admin_settings_form.php' );
        }

        /**
        *Get single value from unserialized data
        *
        *@return string
        *@since 0.1
        */
        function get_settings_value( $key = '' ) {
                return $this->settings_data[$key];
        }

        /**
        *Remove option when plugin is deactivated
        *
        *@return void
        *@since 0.1
        */
        function delete_settings () {
                delete_option( $this->option_key );
        }

        /**
        *Is associative array function
        *
        *@return string
        *@since 0.1
        */
        function is_assoc ( $arr ) {
                if ( isset ( $arr[0] ) ) return false;
                return true;
        }

        /**
        *Display a select form element
        *
        *@return string
        *@since 0.1
        */
        function select_field( $name, $values, $value, $use_label = false, $default_value = '', $custom_label = '' ) {
                ob_start();
                $label = '-- please make a selection --';
                if (@strlen($custom_label)) {
                        $label = $custom_label;
                }

                // convert indexed array into associative

                if ( ! $this->is_assoc( $values ) ) {
                        $tmp_values = $values;
                        $values = array();
                        foreach ( $tmp_values as $tmp_value ) {
                                $values[$tmp_value] = $tmp_value;
                        }
                }
?>
        <select name="<?php echo $name; ?>" id="<?php echo $name; ?>">
                <?php if ( $use_label ): ?>
                <option value=""><?php echo $label; ?></option>

                <?php endif; ?>
                <?php foreach ( $values as $val => $label ) : ?>
                        <option value="<?php echo $val; ?>"<?php if ($value == $val || ( $default_value == $val && @strlen( $default_value ) && ! @strlen( $value ) ) ) : ?> selected="selected"<?php endif; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
        </select>
<?php
                $content = ob_get_contents();
                ob_end_clean();
                return $content;
        }

        /**
        *Update settings form
        *
        *@return void
        *@since 0.1
        */
        function update_settings () {
                $data = array();
                foreach( $this->settings_fields as $key => $val ) {
                        if( $val['type'] != 'legend' ) {
                                $data[$key] = $_POST[$key];
                        }
                }
                $this->set_settings( $data );
		$this->delete_cache();
        }

        /**
        *Update serialized array option
        *
        *@return void
        *@since 0.1
        */
        function set_settings ( $data ) {
                update_option( self::nspace . '-settings', serialize( $data ) );
                $this->settings_data = $data;
        }

	/**
        *Delete cache
        *
        *@return void
        *@since 0.1
        */
	function delete_cache () {
		foreach( glob( $this->wpcjp_path . "/*.*" ) as $file ) {
			unlink( $file );
		}
		update_option( self::nspace . '-js-handles-found', serialize( array() ) );
		$this->js_handles_found = @unserialize( get_option( self::nspace . '-js-handles-found' ) );
		update_option( self::nspace . '-js-use-header', '' );
		update_option( self::nspace . '-js-use-footer', '' );
                if ( function_exists( 'wp_cache_clear_cache' ) ) {
                        wp_cache_clear_cache();
                }
	}

        /**
        *Set plugin file
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_file( $plugin_file ) {
                $this->_plugin_file = $plugin_file;
        }

        /**
        *Get plugin file
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_file() {
                return $this->_plugin_file;
        }

        /**
        *Set plugin directory
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_dir( $plugin_dir ) {
                $this->_plugin_dir = $plugin_dir;
        }

        /**
        *Get plugin directory
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_dir() {
                return $this->_plugin_dir;
        }

        /**
        *Set plugin file path
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_path( $plugin_path ) {
                $this->_plugin_path = $plugin_path;
        }

        /**
        *Get plugin file path
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_path() {
                return $this->_plugin_path;
        }

	/**
        *Set plugin URL
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_url( $plugin_url ) {
                $this->_plugin_url = $plugin_url;
        }

        /**
        *Get plugin URL
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_url() {
                return $this->_plugin_url;
        }

}

?>
