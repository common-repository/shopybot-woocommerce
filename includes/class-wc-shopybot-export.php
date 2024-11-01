<?php

class WC_Shopybot_Export {

	private $id;              // plugin ID

	private $currentpage;     // current export page
	private $vendors;         // brand
	private $salesNote;       // sale notes
	private $yaml_finished;   // is export finished
	private $debug;           // debug on/off
	private $isgroupidattr;   // adding group_id to var. products

	function __construct($export_id, $settings) {
		$this->id          = $export_id;
		$this->shellPrefix = $export_id;

		$this->currentpage   = (get_option($this->id . '_page')) ? get_option($this->id . '_page') : 1;
		$this->pages         = (get_option($this->id . '_pages')) ? get_option($this->id . '_pages') : 1;
		$this->yaml_finished = false;
		$this->debug         = false;
		$this->posts         = get_option($this->id . '_get_ids');
		$this->md5offer      = array();

		$def_settings = array(
			'isdeliver'       => true,
			'isexportattr'    => true,
			'isexporpictures' => true,
			'ispickup'        => 'true',
			'isstore'         => 'true',
			'cpa'             => true,
			'isgroupidattr'   => true,
			'bid'             => true,
			'isbid'           => false,
			'vendors'         => true,
			'salesNote'       => ''
		);

		foreach($def_settings as $set => $val) {
			if(isset($settings[ $set ])) {
				$this->{$set} = $settings[ $set ];
			} else {
				$this->{$set} = $val;
			}
		}

		add_action('init', array($this, 'init'));

		if(isset($_GET['tab']) && $_GET['tab'] == $this->id and isset($_REQUEST['save'])) {
			$this->action_unlock();
		}
	}

	/**
	 * Init
	 */
	public function init() {
		add_action("added_post_meta", array($this, 'generateOffer'), 10, 2);
		add_action('updated_postmeta', array($this, 'generateOffer'), 10, 2);
		add_action('wp_insert_post', array($this, 'wp_insert_post'), 1, 2);
		add_action('set_object_terms', array($this, 'set_object_terms'), 1);

		add_action('wp_ajax_shopybot_woocommerce_ajaxUpdateOffers', array($this, 'ajaxUpdateOffers'));

		$this->shell();
		$this->getYmlAction();
	}

	public function getYmlAction() {
		if(get_option('permalink_structure') != '') {
			$url = parse_url($this->siteURL() . $_SERVER['REQUEST_URI']);

			if(preg_match('/'.$this->id . '.xml.gz'.'/', $url['path'], $matches)) {
				$this->getYml(true);
				die;
			}

			if(preg_match('/'.$this->id . '.xml'.'/', $url['path'], $matches)) {
				$this->getYml();
				die;
			}
		} else {
			if(isset($_GET[ $this->id . '_export' ])) {

				$gzip = (isset($_GET['gzip'])) ? true : false;
				$this->getYml($gzip);
				die;
			}
		}
	}

	private function siteURL() {
		$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$domainName = $_SERVER['HTTP_HOST'];

		return $protocol . $domainName;
	}


	public function bread($text) {
		if(is_string($text)) {
			$result = $text . "\n";
		} elseif(is_array($text) or is_object($text)) {
			$result = print_r($text, true) . "\n";
		}

		$this->bread[] = $result;

		if($this->debug) {
			echo $result;
		}
	}

	final public function inProcess() {
		$inProcess = get_option($this->id . '_in_process');

		if(empty($inProcess)) {
			update_option($this->id . '_in_process', 'no');

			return false;
		}

		if($inProcess == 'no') {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Sets the export status: yes/no
	 */
	final public function inProcessSet($set) {
		if(in_array($set, array('yes', 'no'))) {
			update_option($this->id . '_in_process', $set);
		}
	}


	/**
	 * set current page of the export
	 */
	final public function setPage($page) {
		$this->currentpage = $page;
		update_option($this->id . '_page', $page);
	}

	public function debugOn() {
		$this->debug = true;
	}

	/**
	 * Debug off
	 */
	public function debugOff() {
		$this->debug = false;
	}

	/**
	 * Blocks the export (multiple button click error)
	 */
	final public function exportLock() {
		update_option($this->id . '_lock', true);
	}

	/**
	 * Unblocks the export
	 */
	final public function exportUnlock() {
		update_option($this->id . '_lock', false);
	}

	/**
	 * Checks whether export is blocked
	 */
	final public function isLock() {
		return get_option($this->id . '_lock');
	}

	final public function numberOffers() {
		global $wpdb;
		$ids = $this->getIdsQueryForExport();

		if (sizeof($ids->posts) == 0) {
			return 0;
		}

		$ids_string = implode(',', $ids->posts);

		$offers = $wpdb->get_results("SELECT COUNT(*) as num_offers FROM {$wpdb->prefix}postmeta WHERE meta_key='" . $this->id . "_yml_offer' AND post_id IN ($ids_string)");

		if (sizeof($offers) == 0) {
			return 0;
		}

		return $offers[0]->num_offers;
	}

	/**
	 * Export categories
	 */
	final public function renderCats() {
		$get_terms = $this->getRelationsTax();

		if(!empty($get_terms['product_cat'])) {
			if(in_array('all', $get_terms['product_cat'])) {
				$terms = get_terms('product_cat');
			} else {
				$terms = get_terms('product_cat', array('include' => $get_terms['product_cat']));
			}
		} else {
			$terms = get_terms('product_cat');
		}

		if(!empty($terms)) {
			$yml = '<categories>' . "\n";

			foreach($terms as $key => $cat) {
				$parent = ($cat->parent) ? 'parentId="' . $cat->parent . '"' : '';
				$yml .= "\t\t" . '<category id="' . $cat->term_id . '" ' . $parent . '><![CDATA[' . htmlspecialchars($cat->name) . ']]></category>' . "\n";
			}

			$yml .= '</categories>' . "\n";

			$yml = apply_filters($this->id . '_render_cats', $yml);

			return $yml;
		}
	}

	/**
	 * Export currencies
	 */
	final public function renderCurrency() {
		$yml = '<currency id="' . $this->getWooCurrency() . '" rate="1"/>';

		$yml = apply_filters($this->id . '_render_currency', $yml);

		return $yml;
	}


	/**
	 * Gets product attrs
	 */
	final public function getProductAttributes($product) {
		$attributes = $product->get_attributes();
		$out_attr   = '';

		foreach($attributes as $key => $attribute) {

			if($attribute['is_taxonomy'] && !taxonomy_exists($attribute['name'])) {
				continue;
			}

			$name = wc_attribute_label($attribute['name']);

			if($attribute['is_taxonomy']) {
				if($product->get_type() == 'variation' && array_key_exists('attribute_' . $attribute['name'], $product->variation_data)) {
					$value = apply_filters('woocommerce_attribute', $product->variation_data[ 'attribute_' . $attribute['name'] ]);
				}

				if($product->get_type() != 'variation' || empty($value)) {
					$values = wc_get_product_terms($product->get_id(), $attribute['name'], array('fields' => 'names'));
					$value  = apply_filters('woocommerce_attribute', wptexturize(implode(', ', $values)), $attribute, $values);
				}
			} else {
				if($product->get_type() == 'variation' && array_key_exists('attribute_' . $attribute['name'], $product->variation_data)) {
					$value = apply_filters('woocommerce_attribute', $product->variation_data[ 'attribute_' . $attribute['name'] ]);
				} else {
					// Convert pipes to commas and display values
					$values = array_map('trim', explode(WC_DELIMITER, $attribute['value']));
					$value  = apply_filters('woocommerce_attribute', wptexturize(implode(', ', $values)), $attribute, $values);
				}
			}

			if(!empty($value) and !empty($name)) {
				$out_attr .= '<param name="' . $name . '"><![CDATA[' . htmlspecialchars($value) . ']]></param>' . "\n";
			}
		}

		$out_attr = apply_filters($this->id . '_export_attributes', $out_attr, $product, $attributes);

		return $out_attr;
	}

	/**
	 * Product images
	 */
	final public function getImagesProduct($product) {
		$images = array();

		if($product->get_type() == 'variation' && method_exists($product, 'get_image_id')) {
			$image_id = $product->get_image_id();
		} else {
			$image_id = get_post_thumbnail_id($product->get_id());
		}

		$general_image = WC_Shopybot_Functions::sanitize(wp_get_attachment_url($image_id));

		if(!empty($general_image)) {
			$images[] = $general_image;
		}

		$ids = $product->get_gallery_image_ids();

		if(!empty($ids)) {

			foreach($ids as $id) {
				$image = wp_get_attachment_image_src($id, 'full');
				if(!empty($image[0])) {
					$images[] = WC_Shopybot_Functions::sanitize($image[0]);
				}

			}
		}

		return $images;
	}

	/**
	 * Sets export params
	 */
	final public function setOfferParams($product) {
		$terms = wp_get_post_terms($product->get_id(), 'product_cat');

		if(!empty($terms)) {
			$cat = $terms[0]->term_id;
		} else {
			$this->bread('cat not set id=' . $product->get_id());

			return false;
		}

		$excerpt     = trim(strip_tags($product->get_short_description()));
		$description = (!empty($excerpt)) ? $excerpt : trim(strip_tags($product->get_description()));
		$description = WC_Shopybot_Functions::substr($description, 500, false);

		if($this->vendors == false) {
			$vendor = get_post_meta($product->get_id(), '_vendor', true);
		} else {
			$terms = wp_get_post_terms($product->get_id(), $this->vendors);

			if(!is_wp_error($terms)) {
				if(!empty($terms[0])) {
					$vendor = $terms[0]->name;
				}
			}
		}

		if(empty($vendor)) {
			$vendor = get_option($this->id . '_def_vendor');
		}

		if(empty($vendor)) {
			$vendor = 'none';
		}


		$pictures = $this->getImagesProduct($product);

		if(empty($pictures)) {
			return false;
		}

		$params = array(
			'url'         => WC_Shopybot_Functions::sanitize(urldecode(esc_attr($product->get_permalink()))),
			'price'       => $product->get_price(),
			'currencyId'  => $this->getWooCurrency(),
			'categoryId'  => $cat,
			'picture'     => $pictures,
			'store'       => ($this->isdeliver and !$this->cpa) ? $this->isstore : '',
			'pickup'      => ($this->isdeliver and !$this->cpa) ? $this->ispickup : '',
			'delivery'    => ($this->isdeliver and !$this->cpa) ? 'true' : '',
			'vendor'      => $vendor,
			'name'        => WC_Shopybot_Functions::del_symvol(strip_tags($product->get_title())),
			'description' => WC_Shopybot_Functions::del_symvol(strip_tags($description)),
			'sales_notes' => (!empty($this->salesNote)) ? WC_Shopybot_Functions::substr($this->salesNote, 50, false) : '',
			'cpa'         => ($this->cpa) ? $this->cpa : '',
		);


		$params = apply_filters($this->id . '_set_offer_params', $params, $product);

		if(empty($params['vendor'])) {
			$this->bread('vendor not set id=' . $product->get_id());

			return false;
		}

		if(empty($params['name'])) {
			$this->bread('name not set id=' . $product->get_id());

			return false;
		}


		if($params['price'] == 0) {
			return false;
		}


		$params['sales_notes'] = WC_Shopybot_Functions::substr($params['sales_notes'], 50, false);

		return $params;
	}


	/**
	 * Exports page of the products
	 */
	final public function renderPartOffers() {
		$products = $this->makeQuery();

		if($products->post_count == $products->found_posts) {
			$this->yaml_finished = true;
		}

		if($products->have_posts()) {
			$this->bread('found posts');

			while($products->have_posts()) {
				$products->the_post();
				$product = wc_get_product($products->post->ID);

				// error_log("PRODUCT_TYPE :: " . var_export($product->get_type(), true));

				$allowed_product_types = array('external', 'simple', 'variation');

				if(in_array($product->get_type(), $allowed_product_types)) {
					if($product->get_type() == 'variation') {
						if(!$this->checkVariationUniqueness($product)) {
							delete_post_meta($product->variation_id, $this->id . '_yml_offer');
							$this->bread('WARNING: skipping product variation ID ' . $product->variation_id . ' (product ID ' . $product->get_id() . ') — variation has no unique attributes');
							continue;
						}
					}
					$this->renderPartOffer($product);
				}
			}

			wp_reset_postdata();

			$this->setPage($this->currentpage + 1);

		} else {
			$this->bread('no have posts');
			$this->yaml_finished = true;
		}

	}

	final public function renderPartOffer($product) {
	    if ($product == false) {
	        return false;
	    }

		$param = $this->setOfferParams($product);

		// error_log("PARAM :: " . var_export($param, true));

		if($product->get_type() == 'variation') {
			$product_id = $product->variation_id;
		} else {
			$product_id = $product->get_id();
		}

		if(!empty($param)) {
			$offer = '';

			$available = ($product->is_in_stock() == 'instock') ? "true" : "false";
			$available = apply_filters($this->id . '_set_offer_param_available', $available, $product);

			if($this->isbid == true) {
				$bid = ($this->bid) ? 'bid="' . $this->bid . '"' : '';
			} else {
				$bid = "";
			}

			$offer .= '<offer id="' . $product_id . '" type="vendor.model" available="' . $available . '" ' . $bid;

			if($product->get_type() == 'variation' && $this->isgroupidattr && isset($product->parent->id)) {
				$offer .= ' group_id="' . $product->parent->id . '"';
			}

			$offer .= '>' . "\n";

			// error_log("offer :: " . var_export($offer, true));

			foreach($param as $key => $value) {
				if(!empty($value)) {
					if(is_array($value)) {
						foreach($value as $values) {
							$offer .= "<$key><![CDATA[" . htmlspecialchars($values) . "]]></$key>\n";
						}
					} else {
						$offer .= "<$key><![CDATA[" . htmlspecialchars($value) . "]]></$key>\n";
					}
				}
			}

			$offer .= $this->getProductAttributes($product);
			$offer .= '</offer>' . "\n";
			if(!empty($offer)) {
				$md5offer = md5($offer);

				if(!in_array($md5offer, $this->md5offer)) {
					$this->md5offer[] = $md5offer;
					update_post_meta($product_id, $this->id . '_yml_offer', $offer);
					return true;
				}
			} else {
				update_post_meta($product_id, $this->id . '_yml_offer', '');
				return false;
			}

		} else {
			update_post_meta($product_id, $this->id . '_yml_offer', '');
			return false;
		}
	}

	/**
	 * Check variation
	 */
	final public function checkVariationUniqueness($variation) {

		$product = wc_get_product($variation->id);

		if(!is_object($product) || !($product instanceof WC_Product_Variable)) {
			return false;
		}

		if(method_exists($product, 'get_children')) {
			$children = $product->get_children();
		} else {
			return false;
		}

		$differs      = false;
		$pairs_differ = array();

		foreach($children as $_id) {

			$_variation = wc_get_product($_id);

			if($_variation->variation_id == $variation->variation_id) {
				continue;
			}

			$pair_differs = false;

			foreach($variation->variation_data as $attr => $value) {
				foreach($_variation->variation_data as $attr_compare => $value_compare) {
					if($attr === $attr_compare && $value !== $value_compare) {
						$pair_differs = true;
						break;
					}
				}

				if($pair_differs) {
					break;
				}
			}

			$pairs_differ[] = $pair_differs;
		}

		$differs = in_array(false, $pairs_differ) ? false : true;

		return $differs;

	}

	final public function getShellArg() {
		$shell_arg = @getopt("", array("wooexportyml_" . $this->shellPrefix . "::", "debug::", "unlock::", 'fullexport::', "unittests::"));

		if(empty($shell_arg)) {
			$shell_arg = array();
		} else {
			$shell_arg = array_keys($shell_arg);
		}

		return $shell_arg;
	}

	/**
	 * shell params
	 * attrs:
	 * --wooexportyml - required, main key
	 * --debug        - optional, show debug info
	 * --unlock       - optoinal, unlocks the export
	 * --fullexport   - optional, full export, not paginated
	 */
	public function shell() {
		global $wpdb;

		$shell_arg = $this->getShellArg();

		if(in_array('wooexportyml_' . $this->shellPrefix, $shell_arg)) {
			if(in_array('unlock', $shell_arg)) {
				$this->action_unlock();
				die;
			}

			if(in_array('debug', $shell_arg)) {
				$this->debugOn();
			}

			$this->action_fullexport();
			die;
		}
	}

	/**
	 * Unlocks the export
	 */
	public function action_unlock() {
		$this->inProcessSet('no');
		$this->setPage(1);
		$this->exportUnlock();
	}

	/**
	 * Full re-export, not paginated
	 */
	public function action_fullexport() {
		$this->inProcessSet('no');
		$this->setPage(1);
		$this->exportUnlock();

		while(!$this->yaml_finished) {
			$this->export();
		}
	}

	/**
	 * Main export function
	 */
	public function export() {
        if($this->inProcess()) {
            // error_log("in process");
            $this->bread('in process');
            $this->renderPartOffers();
        } else {
            // error_log("not in process");
            $this->bread('not in process');
            $this->bread('check time true');
            $this->inProcessSet('yes');
            $this->renderPartOffers();
        }

        if($this->yaml_finished) {
            // error_log("yaml finished");
            $this->bread('is yaml_finished true');
            $this->inProcessSet('no');
            $this->setPage(1);
        }
	}


	/**
	 * Renders YAML head
	 */
	final public function renderHead($arg) {
		extract($arg);
		echo '<?xml version="1.0" encoding="utf-8"?>

    <!DOCTYPE yml_catalog SYSTEM "shops.dtd">
    <yml_catalog date="' . date("Y-m-d H:i") . '">
    <shop>
    <name><![CDATA[' . $name . ']]></name>
    <company><![CDATA[' . $desc . ']]></company>
    <url><![CDATA[' . $siteurl . ']]></url>
    <currencies>
    ' . $this->renderCurrency() . '
    </currencies>
    ' . $this->renderCats() . '
    <offers>
    ';

	}


	/**
	 * Renders YAML footer
	 */
	final public function renderFooter() {
		echo '
    </offers>
    </shop>
    </yml_catalog>
    ';

	}

	final public function renderOffers() {
		global $wpdb;

		$ids = $this->getIdsQueryForExport();
		$ids = implode(',', $ids->posts);

		$offers = $wpdb->get_results("SELECT DISTINCT meta_value, post_id FROM {$wpdb->prefix}postmeta WHERE meta_key='" . $this->id . "_yml_offer' AND post_id IN ($ids)");

		foreach($offers as $offer) {
			echo apply_filters($this->id . '_renderOffers', $offer->meta_value, $offer->post_id);
		}
	}


	/**
	 * Fetches offers from postmeta and generates YML file
	 */
	final public function getYml($gzip = false) {
		if($gzip) {
			header('Content-Type: application/gzip');
			ob_start();
		} else {
			header("Content-Type:text/xml; charset=utf-8");
		}


		$arg = array(
			'name'    => get_option('blogname'),
			'desc'    => get_option('blogdescription'),
			'siteurl' => esc_attr(site_url()),
			//            'this' => $this,
		);

		$arg = apply_filters($this->id . '_make_yml_arg', $arg);

		$this->renderHead($arg);
		$this->renderOffers();
		$this->renderFooter();

		if($gzip) {
			WC_Shopybot_Functions::print_gzencode_output($this->id . '.xml.gz');
		}
	}


	final public function getIdsQueryForExport() {

		$this->bread('Generate ids');

		$args = array(
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'post_type'      => array('product'),
			'fields'         => 'ids'
		);

		$relations = $this->getRelationsTax();

		foreach($relations as $tax => $terms) {
			if(!empty($terms)) {

				if(!in_array('all', $terms)) {
					$args['tax_query'][] = array(
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => $terms
					);
				} else if($tax == 'product_cat' and in_array('all', $terms)) {

					$get_terms = get_terms($tax);
					$terms     = array();

					foreach($get_terms as $term) {
						$terms[] = $term->term_id;
					}

					$args['tax_query'][] = array(
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => $terms
					);
				}
			}
		}


		$args         = apply_filters($this->id . '_make_query_get_ids', $args);
		$products_ids = new WP_Query($args);

		$variations_ids = $this->getVariationsIds();

		$ids_query             = new WP_Query();
		$ids_query->posts      = array_merge($products_ids->posts, $variations_ids->posts);
		$ids_query->post_count = $products_ids->post_count + $variations_ids->post_count;

		return $ids_query;
	}

	final public function getVariationsIds() {

		$args = array(
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'post_type'      => array('product_variation'),
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_price',
					'value'   => '0',
					'compare' => '>',
				),
			)
		);

		return new WP_Query($args);
	}

	/**
	 * Export DB query
	 */
	final public function makeQuery() {

		if($this->currentpage == 1) {
			$ids_query   = $this->getIdsQueryForExport();
			$this->posts = $ids_query->posts;
			update_option($this->id . '_get_ids', $this->posts);
		}


		$this->bread('Current page - ' . $this->currentpage);

		$shell_arg = $this->getShellArg();

		$perpage = (in_array('wooexportyml', $shell_arg)) ? 500 : 150;

		$args = array(
			'post__in'       => (array) $this->posts,
			'posts_per_page' => $perpage,
			'paged'          => $this->currentpage,
			'post_type'      => array('product', 'product_variation'),
		);

		// Когда всего 200 товаров, нет смысла выгружать партиями.
		if((int) $ids_query->found_posts <= 200) {
			$args['posts_per_page'] == 200;
		}

		$args = apply_filters($this->id . '_make_query_get_products', $args);

		$query = new WP_Query($args);
		update_option($this->id . '_pages', $query->max_num_pages);

		return $query;
	}


	/**
	 * Get list of taxonomies
	 */
	final public function getRelationsTax() {
		$tax = get_taxonomies(array('object_type' => array('product')), 'objects');

		$relations = array();

		foreach($tax as $key => $tax_val) {

			if($key == 'product_type') {
				continue;
			}

			if(strripos($key, 'pa_') !== false) {
				continue;
			}

			$relations[ $key ] = get_option($this->id . '_tax_' . $key);
		}

		if(!isset($relations['product_cat'])) {
			$relations['product_cat'] = array();
		}


		$options = get_option($this->id . '_filters');

		if(!empty($options)) {
			foreach($options as $key => $value) {
				if(in_array('notfiltered', $value)) {
					continue;
				}

				$relations[ $key ] = $value;
			}
		}

		return $relations;
	}

	public function ajaxUpdateOffers() {
		if($_POST['unlock'] == 'yes') {
			$this->action_unlock();
		}

		$this->export();

		echo json_encode(array('yaml_finished' => $this->yaml_finished, 'bread' => $this->bread));
		die;
	}

	final public function generateOffer($meta_id, $post_id) {
		$product = wc_get_product($post_id);
		if ($product != false) {
	    	$this->renderPartOffer($product);
		}
	}

	final public function wp_insert_post($post_id, $post) {
		if($post->post_type == 'product') {
			$product = wc_get_product($post_id);
			if ($product != false) {
			    $this->renderPartOffer($product);
			}
		}
	}

	final public function set_object_terms($post_id) {
		$post = get_post($post_id);

		if($post->post_type == 'product') {
			$product = wc_get_product($post_id);
			if ($product != false) {
			    $this->renderPartOffer($product);
			}
		}
	}

	/**
	 * @return mixed|void
	 */
	private function getWooCurrency() {
		return apply_filters('woocommerce_currency', get_option('woocommerce_currency'));
	}

}
