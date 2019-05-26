<?php
class splitoneLoader{
     private $pluginDir;
     
     public function __construct($pluginDir){
          $this->pluginDir = $pluginDir . "split-page-in-one/";

          //load composer
          require __DIR__ . '/vendor/autoload.php';
     }

     public function __invoke(){
     }

     public function executeShortcode($atts){
		$detect = new Mobile_Detect;
		
		$title = false;
		if(!empty($atts['title'])){
			$title = ($atts['title'] == "true")?true:false;
		}

          if ( $detect->isMobile() ) {
               if(isset($atts['mobile'])){
                   return $this->insert_pages_handle_shortcode_insert($atts['mobile'], $title);
               }
          }
          else if( $detect->isTablet() ){
               if(isset($atts['mobile'])){
                    return $this->insert_pages_handle_shortcode_insert($atts['mobile'], $title);
               }
          }
          else{
               if(isset($atts['desktop'])){
                    return $this->insert_pages_handle_shortcode_insert($atts['desktop'], $title);
               }
          }
     }

     private function getContent($id){
          $post   = get_post( intval($id) );
          $output =  apply_filters( 'the_content', $post->post_content );
          $output = str_replace(']]>', ']]>', $output);
          return $output;
     }


	/**
	 * Shortcode hook: Replace the [insert ...] shortcode with the inserted page's content.
	 *
	 * @param  array  $atts    Shortcode attributes.
	 * @param  string $content Content to replace shortcode.
	 * @return string          Content to replace shortcode.
	 */
	private function insert_pages_handle_shortcode_insert( $page_id, $title = false, $content = null ) {
		global $wp_query, $post, $wp_current_filter;

		$atts = ['page' => $page_id];
		$display = ($title)?"all":"content";

		// Shortcode attributes.
		$attributes = shortcode_atts(
			array(
				'page' => '0',
				'display' => $display,
				'class' => '',
				'id' => '',
				'inline' => false,
				'public' => false,
				'querystring' => '',
			),
			$atts,
			'insert'
		);

		// Validation checks.
		if ( '0' === $attributes['page'] ) {
			return $content;
		}

		// Short circuit if trying to embed same page in itself.
		if (
			! is_null( $post ) && property_exists( $post, 'ID' ) &&
			(
				( intval( $attributes['page'] ) > 0 && intval( $attributes['page'] ) === $post->ID ) ||
				$attributes['page'] === $post->post_name
			)
		) {
			return $content;
		}
          
		// Get options set in WordPress dashboard (Settings > Insert Pages).
		// $options = get_option( 'wpip_settings' );
		// if ( false === $options || ! is_array( $options ) || ! array_key_exists( 'wpip_format', $options ) || ! array_key_exists( 'wpip_wrapper', $options ) || ! array_key_exists( 'wpip_insert_method', $options ) || ! array_key_exists( 'wpip_tinymce_filter', $options ) ) {
		// 	$options = wpip_set_defaults();
		// }
		
		$options = ['wpip_format' => 'slug',
					'wpip_wrapper' => 'block',
					'wpip_insert_method' => 'legacy',
					'wpip_tinymce_filter' => 'normal'];

		$attributes['inline'] = ( false !== $attributes['inline'] && 'false' !== $attributes['inline'] ) || array_search( 'inline', $atts, true ) === 0 || ( array_key_exists( 'wpip_wrapper', $options ) && 'inline' === $options['wpip_wrapper'] );
		/**
		 * Filter the flag indicating whether to wrap the inserted content in inline tags (span).
		 *
		 * @param bool $use_inline_wrapper Indicates whether to wrap the content in span tags.
		 */
		$attributes['inline'] = apply_filters( 'insert_pages_use_inline_wrapper', $attributes['inline'] );
		$attributes['wrapper_tag'] = $attributes['inline'] ? 'span' : 'div';

		$attributes['public'] = ( false !== $attributes['public'] && 'false' !== $attributes['public'] ) || array_search( 'public', $atts, true ) === 0 || is_user_logged_in();

		/**
		 * Filter the querystring values applied to every inserted page. Useful
		 * for admins who want to provide the same querystring value to all
		 * inserted pages sitewide.
		 *
		 * @since 3.2.9
		 *
		 * @param string $querystring The querystring value for the inserted page.
		 */
		$attributes['querystring'] = apply_filters(
			'insert_pages_override_querystring',
			str_replace( '{', '[', str_replace( '}', ']', htmlspecialchars_decode( $attributes['querystring'] ) ) )
		);

		$attributes['should_apply_the_content_filter'] = true;
		/**
		 * Filter the flag indicating whether to apply the_content filter to post
		 * contents and excerpts that are being inserted.
		 *
		 * @param bool $apply_the_content_filter Indicates whether to apply the_content filter.
		 */
		$attributes['should_apply_the_content_filter'] = apply_filters( 'insert_pages_apply_the_content_filter', $attributes['should_apply_the_content_filter'] );

		// Disable the_content filter if using inline tags, since wpautop
		// inserts p tags and we can't have any inside inline elements.
		if ( $attributes['inline'] ) {
			$attributes['should_apply_the_content_filter'] = false;
		}

		$attributes['should_apply_nesting_check'] = true;
		/**
		 * Filter the flag indicating whether to apply deep nesting check
		 * that can prevent circular loops. Note that some use cases rely
		 * on inserting pages that themselves have inserted pages, so this
		 * check should be disabled for those individuals.
		 *
		 * @param bool $apply_nesting_check Indicates whether to apply deep nesting check.
		 */
		$attributes['should_apply_nesting_check'] = apply_filters( 'insert_pages_apply_nesting_check', $attributes['should_apply_nesting_check'] );

		/**
		 * Filter the chosen display method, where display can be one of:
		 * title, link, excerpt, excerpt-only, content, post-thumbnail, all, {custom-template.php}
		 * Useful for admins who want to restrict the display sitewide.
		 *
		 * @since 3.2.7
		 *
		 * @param string $display The display method for the inserted page.
		 */
		$attributes['display'] = apply_filters( 'insert_pages_override_display', $attributes['display'] );

		// Don't allow inserted pages to be added to the_content more than once (prevent infinite loops).
		if ( $attributes['should_apply_nesting_check'] ) {
			$done = false;
			foreach ( $wp_current_filter as $filter ) {
				if ( 'the_content' === $filter ) {
					if ( $done ) {
						return $content;
					} else {
						$done = true;
					}
				}
			}
		}

		// Get the WP_Post object from the provided slug or ID.
		if ( ! is_numeric( $attributes['page'] ) ) {
			// Get list of post types that can be inserted (page, post, custom
			// types), excluding builtin types (nav_menu_item, attachment).
			$insertable_post_types = array_filter(
				get_post_types(),
				array( $this, 'is_post_type_insertable' )
			);
			$inserted_page = get_page_by_path( $attributes['page'], OBJECT, $insertable_post_types );

			// If get_page_by_path() didn't find the page, check to see if the slug
			// was provided instead of the full path (useful for hierarchical pages
			// that are nested under another page).
			if ( is_null( $inserted_page ) ) {
				global $wpdb;
				$page = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND (post_status = 'publish' OR post_status = 'private') LIMIT 1",
						$attributes['page']
					)
				);
				if ( $page ) {
					$inserted_page = get_post( $page );
				}
			}

			$attributes['page'] = $inserted_page ? $inserted_page->ID : $attributes['page'];
		} else {
			$inserted_page = get_post( intval( $attributes['page'] ) );
		}

		// If inserted page's status is private, don't show to anonymous users
		// unless 'public' option is set.
		if ( is_object( $inserted_page ) && 'private' === $inserted_page->post_status && ! $attributes['public'] ) {
			$inserted_page = null;
		}

		// Set any querystring params included in the shortcode.
		parse_str( $attributes['querystring'], $querystring );
		$original_get = $_GET; // phpcs:ignore WordPress.Security.NonceVerification
		$original_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $querystring as $param => $value ) {
			$_GET[ $param ] = $value;
			$_REQUEST[ $param ] = $value;
		}

		// Use "Normal" insert method (get_post).
		if ( 'legacy' !== $options['wpip_insert_method'] ) {

			// If we couldn't retrieve the page, fire the filter hook showing a not-found message.
			if ( null === $inserted_page ) {
				/**
				 * Filter the html that should be displayed if an inserted page was not found.
				 *
				 * @param string $content html to be displayed. Defaults to an empty string.
				 */
				$content = apply_filters( 'insert_pages_not_found_message', $content );

				// Short-circuit since we didn't find the page.
				return $content;
			}

			// Start output buffering so we can save the output to a string.
			ob_start();

			// If Beaver Builder, SiteOrigin Page Builder, Elementor, or WPBakery
			// Page Builder (Visual Composer) are enabled, load any cached styles
			// associated with the inserted page.
			// Note: Temporarily set the global $post->ID to the inserted page ID,
			// since both builders rely on the id to load the appropriate styles.
			if (
				class_exists( 'FLBuilder' ) ||
				class_exists( 'SiteOrigin_Panels' ) ||
				class_exists( '\Elementor\Post_CSS_File' ) ||
				defined( 'VCV_VERSION' ) ||
				defined( 'WPB_VC_VERSION' )
			) {
				// If we're not in The Loop (i.e., global $post isn't assigned),
				// temporarily populate it with the post to be inserted so we can
				// retrieve generated styles for that post. Reset $post to null
				// after we're done.
				if ( is_null( $post ) ) {
					$old_post_id = null;
					$post = $inserted_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				} else {
					$old_post_id = $post->ID;
					$post->ID = $inserted_page->ID;
				}

				if ( class_exists( 'FLBuilder' ) ) {
					FLBuilder::enqueue_layout_styles_scripts( $inserted_page->ID );
				}

				if ( class_exists( 'SiteOrigin_Panels' ) ) {
					$renderer = SiteOrigin_Panels::renderer();
					$renderer->add_inline_css( $inserted_page->ID, $renderer->generate_css( $inserted_page->ID ) );
				}

				if ( class_exists( '\Elementor\Post_CSS_File' ) ) {
					$css_file = new \Elementor\Post_CSS_File( $inserted_page->ID );
					$css_file->enqueue();
				}

				// Enqueue custom style from WPBakery Page Builder (Visual Composer).
				if ( defined( 'VCV_VERSION' ) ) {
					$bundle_url = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileUrl', true );
					if ( $bundle_url ) {
						$version = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileHash', true );
						if ( ! preg_match( '/^http/', $bundle_url ) ) {
							if ( ! preg_match( '/assets-bundles/', $bundle_url ) ) {
								$bundle_url = '/assets-bundles/' . $bundle_url;
							}
						}
						if ( preg_match( '/^http/', $bundle_url ) ) {
							$bundle_url = set_url_scheme( $bundle_url );
						} elseif ( defined( 'VCV_TF_ASSETS_IN_UPLOADS' ) && constant( 'VCV_TF_ASSETS_IN_UPLOADS' ) ) {
							$upload_dir = wp_upload_dir();
							$bundle_url = set_url_scheme( $upload_dir['baseurl'] . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' ) );
						} else {
							$bundle_url = content_url() . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' );
						}
						wp_enqueue_style(
							'vcv:assets:source:main:styles:' . sanitize_title( $bundle_url ),
							$bundle_url,
							array(),
							VCV_VERSION . '.' . $version
						);
					}
				}

				// Visual Composer custom CSS.
				if ( defined( 'WPB_VC_VERSION' ) ) {
					// Post custom CSS.
					$post_custom_css = get_post_meta( $inserted_page->ID, '_wpb_post_custom_css', true );
					if ( ! empty( $post_custom_css ) ) {
						$post_custom_css = wp_strip_all_tags( $post_custom_css );
						echo '<style type="text/css" data-type="vc_custom-css">';
						echo $post_custom_css;
						echo '</style>';
					}
					// Shortcodes custom CSS.
					$shortcodes_custom_css = get_post_meta( $inserted_page->ID, '_wpb_shortcodes_custom_css', true );
					if ( ! empty( $shortcodes_custom_css ) ) {
						$shortcodes_custom_css = wp_strip_all_tags( $shortcodes_custom_css );
						echo '<style type="text/css" data-type="vc_shortcodes-custom-css">';
						echo $shortcodes_custom_css;
						echo '</style>';
					}
				}

				if ( is_null( $old_post_id ) ) {
					$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				} else {
					$post->ID = $old_post_id;
				}
			}

			/**
			 * Show either the title, link, content, everything, or everything via a
			 * custom template.
			 *
			 * Note: if the sharing_display filter exists, it means Jetpack is
			 * installed and Sharing is enabled; this plugin conflicts with Sharing,
			 * because Sharing assumes the_content and the_excerpt filters are only
			 * getting called once. The fix here is to disable processing of filters
			 * on the_content in the inserted page.
			 *
			 * @see https://codex.wordpress.org/Function_Reference/the_content#Alternative_Usage
			 */
			switch ( $attributes['display'] ) {
				case 'title':
					$title_tag = $attributes['inline'] ? 'span' : 'h1';
					echo "<$title_tag class='insert-page-title'>";
					echo get_the_title( $inserted_page->ID );
					echo "</$title_tag>";
					break;

				case 'link':
					?><a href="<?php echo esc_url( get_permalink( $inserted_page->ID ) ); ?>"><?php echo get_the_title( $inserted_page->ID ); ?></a>
					<?php
					break;

				case 'excerpt':
					?><h1><a href="<?php echo esc_url( get_permalink( $inserted_page->ID ) ); ?>"><?php echo get_the_title( $inserted_page->ID ); ?></a></h1>
					<?php
					echo $this->insert_pages_trim_excerpt( get_post_field( 'post_excerpt', $inserted_page->ID ), $inserted_page->ID, $attributes['should_apply_the_content_filter'] );
					break;

				case 'excerpt-only':
					echo $this->insert_pages_trim_excerpt( get_post_field( 'post_excerpt', $inserted_page->ID ), $inserted_page->ID, $attributes['should_apply_the_content_filter'] );
					break;

				case 'content':
					// If Elementor is installed, try to render the page with it. If there is no Elementor content, fall back to normal rendering.
					if ( class_exists( '\Elementor\Plugin' ) ) {
						$elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content( $inserted_page->ID );
						if ( strlen( $elementor_content ) > 0 ) {
							echo $elementor_content;
							break;
						}
					}

					// Render the content normally.
					$content = get_post_field( 'post_content', $inserted_page->ID );
					if ( $attributes['should_apply_the_content_filter'] ) {
						$content = apply_filters( 'the_content', $content );
					}
					echo $content;
					break;

				case 'post-thumbnail':
					?><a href="<?php echo esc_url( get_permalink( $inserted_page->ID ) ); ?>"><?php echo get_the_post_thumbnail( $inserted_page->ID ); ?></a>
					<?php
					break;

				case 'all':
					// Title.
					$title_tag = $attributes['inline'] ? 'span' : 'h1';
					echo "<$title_tag class='insert-page-title'>";
					echo get_the_title( $inserted_page->ID );
					echo "</$title_tag>";
					// Content.
					$content = get_post_field( 'post_content', $inserted_page->ID );
					if ( $attributes['should_apply_the_content_filter'] ) {
						$content = apply_filters( 'the_content', $content );
					}
					echo $content;
					/**
					 * Meta.
					 *
					 * @see https://core.trac.wordpress.org/browser/tags/4.4/src/wp-includes/post-template.php#L968
					 */
					$keys = get_post_custom_keys( $inserted_page->ID );
					if ( $keys ) {
						echo "<ul class='post-meta'>\n";
						foreach ( (array) $keys as $key ) {
							$keyt = trim( $key );
							if ( is_protected_meta( $keyt, 'post' ) ) {
								continue;
							}
							$value = get_post_custom_values( $key, $inserted_page->ID );
							if ( is_array( $value ) ) {
								$values = array_map( 'trim', $value );
								$value = implode( $values, ', ' );
							}
							/**
							 * Filter the HTML output of the li element in the post custom fields list.
							 *
							 * @since 2.2.0
							 *
							 * @param string $html  The HTML output for the li element.
							 * @param string $key   Meta key.
							 * @param string $value Meta value.
							 */
							echo apply_filters( 'the_meta_key', "<li><span class='post-meta-key'>$key:</span> $value</li>\n", $key, $value );
						}
						echo "</ul>\n";
					}
					break;

					default: // Display is either invalid, or contains a template file to use.
						/**
						 * Legacy/compatibility code: In order to use custom templates,
						 * we use query_posts() to provide the template with the global
						 * state it requires for the inserted page (in other words, all
						 * template tags will work with respect to the inserted page
						 * instead of the parent page / main loop). Note that this may
						 * cause some compatibility issues with other plugins.
						 *
						 * @see https://codex.wordpress.org/Function_Reference/query_posts
						 */
						if ( is_numeric( $attributes['page'] ) ) {
							$args = array(
								'p' => intval( $attributes['page'] ),
								'post_type' => get_post_types(),
							);
						} else {
							$args = array(
								'name' => esc_attr( $attributes['page'] ),
								'post_type' => get_post_types(),
							);
						}
						// We save the previous query state here instead of using
						// wp_reset_query() because wp_reset_query() only has a single stack
						// variable ($GLOBALS['wp_the_query']). This allows us to support
						// pages inserted into other pages (multiple nested pages, which
						// requires insert_pages_apply_nesting_check to be turned off).
						$old_query = $GLOBALS['wp_query'];
						$inserted_page = query_posts( $args );
						if ( have_posts() ) {
							$template = locate_template( $attributes['display'] );
							// Only allow templates that don't have any directory traversal in
							// them (to prevent including php files that aren't in the active
							// theme directory or the /wp-includes/theme-compat/ directory).
							$path_in_theme_or_childtheme_or_compat = (
								// Template is in current theme folder.
								0 === strpos( realpath( $template ), realpath( get_stylesheet_directory() ) ) ||
								// Template is in current or parent theme folder.
								0 === strpos( realpath( $template ), realpath( get_template_directory() ) ) ||
								// Template is in theme-compat folder.
								0 === strpos( realpath( $template ), realpath( ABSPATH . WPINC . '/theme-compat/' ) )
							);
							if ( strlen( $template ) > 0 && $path_in_theme_or_childtheme_or_compat ) {
								include $template; // Execute the template code.
							} else { // Couldn't find template, so fall back to printing a link to the page.
								the_post();
								?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								<?php
							}
						}
						// Restore previous query and update the global template variables.
						$GLOBALS['wp_query'] = $old_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
						wp_reset_postdata();
			}

			// Save output buffer contents.
			$content = ob_get_clean();

		} else { // Use "Legacy" insert method (query_posts).

				// Construct query_posts arguments.
				if ( is_numeric( $attributes['page'] ) ) {
					$args = array(
						'p' => intval( $attributes['page'] ),
						'post_type' => get_post_types(),
						'post_status' => $attributes['public'] ? array( 'publish', 'private' ) : array( 'publish' ),
					);
				} else {
					$args = array(
						'name' => esc_attr( $attributes['page'] ),
						'post_type' => get_post_types(),
						'post_status' => $attributes['public'] ? array( 'publish', 'private' ) : array( 'publish' ),
					);
				}
				// We save the previous query state here instead of using
				// wp_reset_query() because wp_reset_query() only has a single stack
				// variable ($GLOBALS['wp_the_query']). This allows us to support
				// pages inserted into other pages (multiple nested pages, which
				// requires insert_pages_apply_nesting_check to be turned off).
				$old_query = $GLOBALS['wp_query'];
				$posts = query_posts( $args );
				if ( have_posts() ) {
					// Start output buffering so we can save the output to string.
					ob_start();

					// If Beaver Builder, SiteOrigin Page Builder, Elementor, or WPBakery
					// Page Builder (Visual Composer) are enabled, load any cached styles
					// associated with the inserted page.
					// Note: Temporarily set the global $post->ID to the inserted page ID,
					// since both builders rely on the id to load the appropriate styles.
					if (
						class_exists( 'FLBuilder' ) ||
						class_exists( 'SiteOrigin_Panels' ) ||
						class_exists( '\Elementor\Post_CSS_File' ) ||
						defined( 'VCV_VERSION' ) ||
						defined( 'WPB_VC_VERSION' )
					) {
						// If we're not in The Loop (i.e., global $post isn't assigned),
						// temporarily populate it with the post to be inserted so we can
						// retrieve generated styles for that post. Reset $post to null
						// after we're done.
						if ( is_null( $post ) ) {
							$old_post_id = null;
							$post = $inserted_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
						} else {
							$old_post_id = $post->ID;
							$post->ID = $inserted_page->ID;
						}

						if ( class_exists( 'FLBuilder' ) ) {
							FLBuilder::enqueue_layout_styles_scripts( $inserted_page->ID );
						}

						if ( class_exists( 'SiteOrigin_Panels' ) ) {
							$renderer = SiteOrigin_Panels::renderer();
							$renderer->add_inline_css( $inserted_page->ID, $renderer->generate_css( $inserted_page->ID ) );
						}

						if ( class_exists( '\Elementor\Post_CSS_File' ) ) {
							$css_file = new \Elementor\Post_CSS_File( $inserted_page->ID );
							$css_file->enqueue();
						}

						// Enqueue custom style from WPBakery Page Builder (Visual Composer).
						if ( defined( 'VCV_VERSION' ) ) {
							$bundle_url = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileUrl', true );
							if ( $bundle_url ) {
								$version = get_post_meta( $inserted_page->ID, 'vcvSourceCssFileHash', true );
								if ( ! preg_match( '/^http/', $bundle_url ) ) {
									if ( ! preg_match( '/assets-bundles/', $bundle_url ) ) {
										$bundle_url = '/assets-bundles/' . $bundle_url;
									}
								}
								if ( preg_match( '/^http/', $bundle_url ) ) {
									$bundle_url = set_url_scheme( $bundle_url );
								} elseif ( defined( 'VCV_TF_ASSETS_IN_UPLOADS' ) && constant( 'VCV_TF_ASSETS_IN_UPLOADS' ) ) {
									$upload_dir = wp_upload_dir();
									$bundle_url = set_url_scheme( $upload_dir['baseurl'] . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' ) );
								} else {
									$bundle_url = content_url() . '/' . VCV_PLUGIN_ASSETS_DIRNAME . '/' . ltrim( $bundle_url, '/\\' );
								}
								wp_enqueue_style(
									'vcv:assets:source:main:styles:' . sanitize_title( $bundle_url ),
									$bundle_url,
									array(),
									VCV_VERSION . '.' . $version
								);
							}
						}

						// Visual Composer custom CSS.
						if ( defined( 'WPB_VC_VERSION' ) ) {
							// Post custom CSS.
							$post_custom_css = get_post_meta( $inserted_page->ID, '_wpb_post_custom_css', true );
							if ( ! empty( $post_custom_css ) ) {
								$post_custom_css = wp_strip_all_tags( $post_custom_css );
								echo '<style type="text/css" data-type="vc_custom-css">';
								echo $post_custom_css;
								echo '</style>';
							}
							// Shortcodes custom CSS.
							$shortcodes_custom_css = get_post_meta( $inserted_page->ID, '_wpb_shortcodes_custom_css', true );
							if ( ! empty( $shortcodes_custom_css ) ) {
								$shortcodes_custom_css = wp_strip_all_tags( $shortcodes_custom_css );
								echo '<style type="text/css" data-type="vc_shortcodes-custom-css">';
								echo $shortcodes_custom_css;
								echo '</style>';
							}
						}

						if ( is_null( $old_post_id ) ) {
							$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						} else {
							$post->ID = $old_post_id;
						}
					}
				/**
				 * Show either the title, link, content, everything, or everything via a
				 * custom template.
				 *
				 * Note: if the sharing_display filter exists, it means Jetpack is
				 * installed and Sharing is enabled; this plugin conflicts with Sharing,
				 * because Sharing assumes the_content and the_excerpt filters are only
				 * getting called once. The fix here is to disable processing of filters
				 * on the_content in the inserted page.
				 *
				 * @see https://codex.wordpress.org/Function_Reference/the_content#Alternative_Usage
				 */
				switch ( $attributes['display'] ) {
					case 'title':
						the_post();
						$title_tag = $attributes['inline'] ? 'span' : 'h1';
						echo "<$title_tag class='insert-page-title'>";
						the_title();
						echo "</$title_tag>";
						break;
					case 'link':
						the_post();
						?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						<?php
						break;
					case 'excerpt':
						the_post();
						?><h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
						<?php
						if ( $attributes['should_apply_the_content_filter'] ) {
							the_excerpt();
						} else {
							echo get_the_excerpt();
						}
						break;
					case 'excerpt-only':
						the_post();
						if ( $attributes['should_apply_the_content_filter'] ) {
							the_excerpt();
						} else {
							echo get_the_excerpt();
						}
						break;
					case 'content':
						// If Elementor is installed, try to render the page with it. If there is no Elementor content, fall back to normal rendering.
						if ( class_exists( '\Elementor\Plugin' ) ) {
							$elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content( $inserted_page->ID );
							if ( strlen( $elementor_content ) > 0 ) {
								echo $elementor_content;
								break;
							}
						}
						// Render the content normally.
						the_post();
						if ( $attributes['should_apply_the_content_filter'] ) {
							the_content();
						} else {
							echo get_the_content();
						}
						break;
					case 'post-thumbnail':
						?><a href="<?php echo esc_url( get_permalink( $inserted_page->ID ) ); ?>"><?php echo get_the_post_thumbnail( $inserted_page->ID ); ?></a>
						<?php
						break;
					case 'all':
						the_post();
						$title_tag = $attributes['inline'] ? 'span' : 'h1';
						echo "<$title_tag class='insert-page-title'>";
						the_title();
						echo "</$title_tag>";
						if ( $attributes['should_apply_the_content_filter'] ) {
							the_content();
						} else {
							echo get_the_content();
						}
						the_meta();
						break;
					default: // Display is either invalid, or contains a template file to use.
						$template = locate_template( $attributes['display'] );
						// Only allow templates that don't have any directory traversal in
						// them (to prevent including php files that aren't in the active
						// theme directory or the /wp-includes/theme-compat/ directory).
						$path_in_theme_or_childtheme_or_compat = (
							// Template is in current theme folder.
							0 === strpos( realpath( $template ), realpath( get_stylesheet_directory() ) ) ||
							// Template is in current or parent theme folder.
							0 === strpos( realpath( $template ), realpath( get_template_directory() ) ) ||
							// Template is in theme-compat folder.
							0 === strpos( realpath( $template ), realpath( ABSPATH . WPINC . '/theme-compat/' ) )
						);
						if ( strlen( $template ) > 0 && $path_in_theme_or_childtheme_or_compat ) {
							include $template; // Execute the template code.
						} else { // Couldn't find template, so fall back to printing a link to the page.
							the_post();
							?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							<?php
						}
						break;
				}
				// Save output buffer contents.
				$content = ob_get_clean();
			} else {
				/**
				 * Filter the html that should be displayed if an inserted page was not found.
				 *
				 * @param string $content html to be displayed. Defaults to an empty string.
				 */
				$content = apply_filters( 'insert_pages_not_found_message', $content );
			}
			// Restore previous query and update the global template variables.
			$GLOBALS['wp_query'] = $old_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_reset_postdata();
		}
          
		// Unset any querystring params included in the shortcode.
		$_GET = $original_get;
		$_REQUEST = $original_request;

		return $content;
     }
}