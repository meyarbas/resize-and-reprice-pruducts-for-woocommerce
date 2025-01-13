<?php

/**
 * Plugin Name: Resize and Reprice Products for WooCommerce
 * Description: Plugin to resize product images and calculate price by entering product dimensions on WordPress-based websites with WooCommerce
 * Version: 1.0 Beta
 * Author: meyarbas
 * Author URI: https://github.com/meyarbas
 * Text Domain: resize-and-reprice-pruducts-for-woocommerce
 * Requires PHP: 7.4
 * License: GPL v3
 */

if (!defined('ABSPATH')) {
    exit; // Block direct access
}

// Check if WooCommerce is installed
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Automatically create RRP category
function rrp_create_product_category() {
    if (!term_exists('RRP', 'product_cat')) {
        wp_insert_term(
            'RRP', // Category name
            'product_cat', // Category taxonomy
            array(
                'description' => 'Resize and Reprice Products',
                'slug'        => 'rrp'
            )
        );
    }
}
register_activation_hook(__FILE__, 'rrp_create_product_category');

// Make sure the plugin only works on products in the RRP category
function rrp_should_load_for_product() {
    if (is_product()) {
        global $post;
        $terms = wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'slugs'));

        if (in_array('rrp', $terms)) {
            return true;
        }
    }
    return false;
}

add_action('wp_enqueue_scripts', 'rrp_enqueue_scripts');
function rrp_enqueue_scripts() {
    if (!rrp_should_load_for_product()) {
        return;
    }

    // Cropper.js library
    wp_enqueue_script('cropper-js', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js', array('jquery'), '1.5.12', true);
    wp_enqueue_style('cropper-css', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css');

    // Custom script and style
    wp_enqueue_script('rrp-custom-js', plugins_url('assets/rrp-functions.js', __FILE__), array('jquery', 'cropper-js'), '1.0', true);
    wp_enqueue_style('rrp-custom-css', plugins_url('assets/rrp-style.css', __FILE__));

    // Pass Ajax URL and nonce value to JavaScript
    wp_localize_script('rrp-custom-js', 'ajax_obj', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('crop_image_nonce')
    ));
}

add_action('wp_enqueue_scripts', 'rrp_enqueue_styles');
function rrp_enqueue_styles() {
    if (!rrp_should_load_for_product()) {
        return;
    }

    wp_add_inline_style('rrp-custom-css', '
        /* Custom style for products in RRP category */
        .single-product .product .woocommerce-product-gallery {
            display: none;
        }
        .single-product .product .summary {
            width: 100%;
            max-width: 100%;
        }
        .wp-block-column.is-layout-flow.wp-block-column-is-layout-flow:nth-of-type(1) {
            display: none !important;
        }
        .woocommerce div.product form.cart button.single_add_to_cart_button, .woocommerce div.product form.cart button[name=add-to-cart] {
            width: max-content !important;
        }
    ');

    // Add new class
    wp_add_inline_style('rrp-custom-css', '
        /* Custom class for Product Image Gallery */
        .single-product .product .woocommerce-product-gallery.rrp-custom-gallery-class {
            /* Custom gallery class CSS properties */
        }
    ');
}

// Add class to Product Image Gallery when plugin is activated
function add_custom_class_to_product_image_gallery_on_activation() {
    if (is_product()) {
        add_filter('woocommerce_single_product_image_gallery_classes', 'add_custom_gallery_class');
    }
}

function add_custom_gallery_class($classes) {
    $classes[] = 'rrp-custom-gallery-class'; // CSS classes to add
    return $classes;
}

add_action('wp', 'add_custom_class_to_product_image_gallery_on_activation');

// Remove class from Product Image Gallery when plugin is disabled
function remove_custom_class_from_product_image_gallery_on_deactivation() {
    remove_filter('woocommerce_single_product_image_gallery_classes', 'add_custom_gallery_class');
}

register_deactivation_hook(__FILE__, 'remove_custom_class_from_product_image_gallery_on_deactivation');

// Show custom fields on product page
add_action('woocommerce_before_add_to_cart_button', 'rrp_display_custom_fields');
function rrp_display_custom_fields() {
    if (!rrp_should_load_for_product()) {
        return;
    }

    global $product;

    // Get product main image
    $product_image_url = get_the_post_thumbnail_url($product->get_id(), 'full');
    if (!$product_image_url) {
        echo 'No image found!';
        return;
    }
    ?>

    <div class="rrp-custom-calculator">

        <div class="input-container">
            <label for="rrp_width">Width (cm):</label>
            <input type="number" id="rrp_width" name="rrp_width" step="1" min="10" required>
            <span class="error-message" style="color: red; display: none;">Please enter a number of at least 2 digits.</span>

            <label for="rrp_height">Height (cm):</label>
            <input type="number" id="rrp_height" name="rrp_height" step="1" min="10" required>
            <span class="error-message" style="color: red; display: none;">Please enter a number of at least 2 digits.</span>

            <input type="hidden" id="rrp_crop_data" name="rrp_crop_data">
            <button id="confirm-crop" type="button" class="wp-element-button" style="width: 100%;">Confirm Cropping</button>

            <label for="rrp_paper_type">Paper Type:</label>
            <select id="rrp_paper_type" name="rrp_paper_type" required>
                <option value="">Select Paper Type</option>
                <option value="mat">Matte</option>
                <option value="parlak">Bright</option>
            </select>

            <!-- Image Crop Area -->
            <p id="crop-area-display"></p>

            <!-- Add to Cart Button -->
            <?php do_action('woocommerce_after_add_to_cart_button'); ?>
        </div>

        <div class="rrp-crop-container">
            <label for="rrp_crop_image">Crop Image:</label>
            <img id="rrp-crop-image" src="<?php echo esc_url($product_image_url); ?>" alt="Image to Crop" style="max-width: 100%;" />
        </div>

    </div>

    <?php
}

add_filter('woocommerce_add_cart_item_data', 'rrp_add_custom_data_to_cart', 10, 2);
function rrp_add_custom_data_to_cart($cart_item_data, $product_id) {
    if (isset($_POST['rrp_width'], $_POST['rrp_height'], $_POST['rrp_paper_type'], $_POST['rrp_crop_data'])) {
        $width = floatval($_POST['rrp_width']);
        $height = floatval($_POST['rrp_height']);
        $area = ($width / 100) * ($height / 100); // Convert to square meters
        $price_per_sqm = 50; // Sample unit price

        // Total price calculation
        $total_price = $area * $price_per_sqm;

        $cart_item_data['rrp_width'] = $width;
        $cart_item_data['rrp_height'] = $height;
        $cart_item_data['rrp_area'] = $area;
        $cart_item_data['rrp_crop_data'] = sanitize_text_field($_POST['rrp_crop_data']);
        $cart_item_data['rrp_paper_type'] = sanitize_text_field($_POST['rrp_paper_type']);
        $cart_item_data['rrp_total_price'] = $total_price;

        $cart_item_data['unique_key'] = md5(microtime() . rand());
    }
    return $cart_item_data;
}

add_action('woocommerce_before_calculate_totals', 'rrp_update_cart_item_price', 10, 1);
function rrp_update_cart_item_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['rrp_total_price'])) {
            $cart_item['data']->set_price($cart_item['rrp_total_price']);
        }
    }
}

add_filter('woocommerce_get_item_data', 'rrp_display_custom_fields_on_cart', 10, 2);
function rrp_display_custom_fields_on_cart($item_data, $cart_item) {
    if (isset($cart_item['rrp_width'], $cart_item['rrp_height'])) {
        $item_data[] = array(
            'name'  => 'Width x Height',
            'value' => $cart_item['rrp_width'] . 'cm x ' . $cart_item['rrp_height'] . 'cm',
        );
    }
    if (isset($cart_item['rrp_paper_type'])) {
        $item_data[] = array(
            'name'  => 'Paper Type',
            'value' => ucfirst($cart_item['rrp_paper_type']),
        );
    }
    return $item_data;
}

// For AJAX operations
add_action('wp_ajax_save_cropped_image', 'rrp_save_cropped_image');
add_action('wp_ajax_nopriv_save_cropped_image', 'rrp_save_cropped_image');

function rrp_save_cropped_image() {
    // Nonce control for security
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'crop_image_nonce')) {
        wp_send_json_error(array('data' => 'Invalid nonce.'));
        exit;
    }

    // Get file data
    if (isset($_FILES['file'])) {
        $uploaded_file = $_FILES['file'];

        // File upload process
        $upload = wp_handle_upload($uploaded_file, array('test_form' => false));

        if (isset($upload['file'])) {
            // Successful file upload
            $file_url = $upload['url']; // URL of uploaded file

            // Operations such as saving the image to the database can be performed
            wp_send_json_success(array('data' => 'Image uploaded successfully', 'file_url' => $file_url));
        } else {
            // Upload error
            wp_send_json_error(array('data' => 'File upload error: ' . $upload['error']));
        }
    } else {
        wp_send_json_error(array('data' => 'The file was not sent.'));
    }
}
