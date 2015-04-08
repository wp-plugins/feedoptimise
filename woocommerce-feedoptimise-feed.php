<?php

	class woocommerce_feedoptimise_feed
	{

	    function render()
	    {
	    	header('Content-type: application/json');
	    	$this->_render_product_feed();
	    }

		 /**
	     * Render the product feed requests - calls the sub-classes according
	     * to the feed required.
	     *
	     * @access public
	     */
	    protected function _render_product_feed() {

	        global $wpdb, $wp_query, $post;

	        // Don't cache feed under WP Super-Cache
	        define('DONOTCACHEPAGE', TRUE);

	        // Cater for large stores
	        $wpdb->hide_errors();
	        @set_time_limit ( 0 );
	        @ob_clean();
	        
	        // wp_suspend_cache_addition is buggy prior to 3.4
	        if ( version_compare ( get_bloginfo('version'), '3.4', '>=' ) ) {
	            wp_suspend_cache_addition ( true ) ;
	        }

	        // Query for the products
	        $chunk_size = apply_filters ( 'woocommerce_gpf_chunk_size', 20 );

	        $args['post_type'] 		= 'product';
	        $args['numberposts'] 	= $chunk_size;
	        $args['offset'] 		= 0;
	        $args['post_status']	= 'publish';

	        $products = get_posts ($args);
	        
	        while ( count ( $products ) ) {

	            foreach ($products as $post) {

	                setup_postdata($post);

	                // 2.0 compat
	                if ( function_exists( 'get_product' ) )
	                    $woocommerce_product = get_product( $post );
	                else
	                    $woocommerce_product = new WC_Product( $post->ID );

	                if ( $woocommerce_product->visibility == 'hidden' )
	                    continue;

	                if ( isset ( $tmp_product_data['exclude_product'] ) )
	                    continue;

	                if ( $woocommerce_product->get_parent() ) 
	                {
	                	continue;
	                }

	                $feed_item = $this->_getFeedItem($woocommerce_product);

	                if ( $woocommerce_product->has_child() ) {

	                	$feed_item->variants = array();

	                	$children = $woocommerce_product->get_children();

	                    foreach ( $children as $child ) {
	                    
	                        $child_product = $woocommerce_product->get_child( $child );

	                        $child_price = $child_product->get_price();

	                        if (($feed_item->price_inc_tax == 0) && ($child_price > 0)) {

	                            $feed_item->price_ex_tax = $child_product->get_price_excluding_tax();
	                            $feed_item->price_inc_tax = $child_product->get_price();

	                        } else if ( ($child_price > 0) && ($child_price < $feed_item->price_inc_tax) ) {

	                                $feed_item->price_inc_tax = $child_product->get_price();
	                                $feed_item->price_ex_tax = $child_product->get_price_excluding_tax();

	                        }


	                        $variant = $this->_getFeedItem($child_product);
	                        if($variant->is_in_stock) 
	                        {
	                        	$variant_in_stock = true;
	                        }

	                        $feed_item->variants[] = $variant;

	                    }

	                    if(!isset($variant_in_stock))
	                    {
	                    	continue;
	                    }
	                }

	               //$this->feed->render_item ( $feed_item );

	                echo json_encode($feed_item)."\n";

	            }

	            $args['offset'] += $chunk_size;
	            $products = get_posts ( $args );

	        }

	         exit();
	    }

	    protected function _getFeedItem($woocommerce_product)
	    {
	    	$feed_item = new stdClass();

	    	$feed_item->pid 			= $woocommerce_product->id;
	    	$feed_item->sku 			= $woocommerce_product->get_sku();
	    	$feed_item->type			= $woocommerce_product->product_type;
	    	

	        $feed_item->price_ex_tax = $woocommerce_product->get_price_excluding_tax();
	        $feed_item->price_inc_tax = $woocommerce_product->get_price();

	        // Get main item information
	        
	        $feed_item->title = get_the_title($woocommerce_product->id);

	        if($feed_item->type!='variation')
	        {
	        	$feed_item->description = apply_filters ('the_content', get_the_content($woocommerce_product->id));
	        	$feed_item->product_url = get_permalink($woocommerce_product->id);
	        }

	        $feed_item->image_url_original = $this->get_the_post_thumbnail_src ( $woocommerce_product->id, 'original' );
	        if(!$feed_item->image_url_original) unset($feed_item->image_url_original );

	         $feed_item->image_url_thumb = $this->get_the_post_thumbnail_src ( $woocommerce_product->id, 'thumbnail' );
	        if(!$feed_item->image_url_thumb) unset($feed_item->image_url_thumb );

	        $feed_item->shipping_weight = $woocommerce_product->get_weight();

	        $feed_item->is_in_stock = $woocommerce_product->is_in_stock();

	        $feed_item->stock 		= get_post_meta( $woocommerce_product->id ,'_stock', true );
	        $feed_item->backorders 	= get_post_meta( $woocommerce_product->id ,'_backorders', true );

	        

	        $categories               = wp_get_object_terms($woocommerce_product->id, 'product_cat');
	        $feed_item->categories    = array();
	        foreach($categories as $c)
	        {
	        	$bread = $this->_woo_get_term_parents($c->term_id,'product_cat',false,' > ', false, array() );

	        	$feed_item->categories[] = trim($bread,' >');
	        }

	        //$feed_item->categories    = implode(' > ',$feed_item->categories);

	        /*
	        $post = get_post($woocommerce_product->id);

	        $feed_item->brand         = $this->get_product_meta($post,'mybrand');

	        
	        $feed_item->manufacturer  = $this->get_product_meta($post,'manufacturer');
	        $feed_item->vendor        = $this->get_product_meta($post,'vendor');
	        $feed_item->ean           = $this->get_product_meta($post,'ean');
	        $feed_item->gtin          = $this->get_product_meta($post,'gtin');
	        $feed_item->upc           = $this->get_product_meta($post,'upc');
	        */

	        $feed_item->custom_fields  		= $this->_getCustomData($woocommerce_product,'fields');

	        if($feed_item->type!='variation')
	        {
	        	$feed_item->custom_attrs  	= $this->_getCustomData($woocommerce_product,'attributes');
	        }
	        else
	        {
	        	$feed_item->custom_attrs 	= $woocommerce_product->get_variation_attributes( );
	        }
	        

	        // Get other images
	        $feed_item->additional_images = array();

	        $main_thumbnail = get_post_meta ( $woocommerce_product->id, '_thumbnail_id', true );
	        $images = get_children( array ( 'post_parent' => $woocommerce_product->id,
	                                        'post_status' => 'inherit',
	                                         'post_type' => 'attachment',
	                                        'post_mime_type' => 'image',
	                                          'exclude' => isset($main_thumbnail) ? $main_thumbnail : '',
	                                          'order' => 'ASC',
	                                          'orderby' => 'menu_order' ) );

	       

	                if ( is_array ( $images ) && count ( $images ) ) {

	                    foreach ( $images as $image ) {

	                    	$full_image_src = wp_get_attachment_image_src( $image->ID, 'original' );

	                        $feed_item->additional_images[] = $full_image_src[0];

	                    }

	                }

	        $_feed_item = new stdClass();

	        foreach($feed_item as $key => $value)
	        {
	        	if($value==="" OR (is_array($value) && !count($value))) continue;

	        	$_feed_item->{$key}	= $value;
	        }

	        return $_feed_item;
	    }

	    protected function _getCustomData($product,$type='fields')
	    {
	    	$id = $product->id;
	    	$custom = array();
	    	$meta = get_post_meta($id);

	    	if($type=='fields')
	    	{
		    	foreach($meta as $key => $value)
		    	{
		    		if(substr($key,0,1)=='_') continue;

		    		$custom[$key]	= count($value) === 1 ? $value[0] : $value;
		    		//if(!$custom[$key]) unset($custom[$key]);
		    	}
		    }

		    if($type=='attributes')
	    	{
		    	if(isset($meta['_product_attributes']))
		    	{
		    		$attributes = (unserialize($meta['_product_attributes'][0]));

		    		foreach($attributes as $attr)
			    	{
			    		$custom[$attr['name']]	= $product->get_attribute( $attr['name'] );
			    		//if(!$custom[$key]) unset($custom[$key]);
			    	}
		    	}
		    }

	    	return $custom;
	    }

	    /**
	     * Helper function to retrieve a custom field from a product, compatible
	     * both with WC < 2.0 and WC >= 2.0
	     *
	     * @param WC_Product $product the product object
	     * @param string $field_name the field name, without a leading underscore
	     *
	     * @return mixed the value of the member named $field_name, or null
	     */
	    private function get_product_meta ( $product, $field_name ) {	

	      if ( version_compare( WOOCOMMERCE_VERSION, "2.0.0" ) >= 0 ) {

	        // even in WC >= 2.0 product variations still use the product_custom_fields array apparently
	        if ( $product->variation_id && isset( $product->product_custom_fields[ '_' . $field_name ][0] ) && $product->product_custom_fields[ '_' . $field_name ][0] !== '' ) {
	          return $product->product_custom_fields[ '_' . $field_name ][0];
	        }


	        // use magic __get
	        return $product->$field_name;

	      } else {

	        // use product custom fields array

	        // variation support: return the value if it's defined at the variation level
	        if ( isset( $product->variation_id ) && $product->variation_id ) {
	          if ( ( $value = get_post_meta( $product->variation_id, '_' . $field_name, true ) ) !== '' ) return $value;
	          // otherwise return the value from the parent
	          return get_post_meta( $product->id, '_' . $field_name, true );
	        }
	        // regular product
	        return isset( $product->product_custom_fields[ '_' . $field_name ][0] ) ? $product->product_custom_fields[ '_' . $field_name ][0] : null;
	      }
	      
	    }

	    /**
	     * Retrieve Post Thumbnail URL
	     *
	     * @param int     $post_id (optional) Optional. Post ID.
	     * @param string  $size    (optional) Optional. Image size.  Defaults to 'post-thumbnail'.
	     * @return string|bool Image src, or false if the post does not have a thumbnail.
	     */
	    protected function get_the_post_thumbnail_src( $post_id = null, $size = 'post-thumbnail' ) {

	        $post_thumbnail_id = get_post_thumbnail_id( $post_id );

	        if ( ! $post_thumbnail_id ) {
	            return false;
	        }

	        list( $src ) = wp_get_attachment_image_src( $post_thumbnail_id, $size, false );

	        return $src;
	    }

	    protected function _woo_get_term_parents( $id, $taxonomy, $link = false, $separator = '/', $nicename = false, $visited = array() ) {
			$chain = '';
			$parent = &get_term( $id, $taxonomy );
			if ( is_wp_error( $parent ) )
				return $parent;

			if ( $nicename ) {
				$name = $parent->slug;
			} else {
				$name = $parent->name;
			}
		
			if ( $parent->parent && ( $parent->parent != $parent->term_id ) && !in_array( $parent->parent, $visited ) ) {
				$visited[] = $parent->parent;
				$chain .= $this->_woo_get_term_parents( $parent->parent, $taxonomy, $link, $separator, $nicename, $visited );
			}

			if ( $link ) {
			$chain .= '<a href="' . get_term_link( $parent, $taxonomy ) . '" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $parent->name ) ) . '">'.$parent->name.'</a>' . $separator;
			} else {
			$chain .= $name.$separator;
			}
			return $chain;
		} // End woo_get_term_parents()

	}
?>