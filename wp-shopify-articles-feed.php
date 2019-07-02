<?php
/*
Plugin Name:  WP Shopify Articles Feed
Plugin URI:   https://www.renzramos.com
Description:  Simple plugin to get Shopify Articles
Version:      1.0
Author:       Renz Ramos
Author URI:   https://www.renzramos.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  wp_shopify_articles_feed
*/

class ShopifyWoocommerceIntegration{

    private $options;

    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );

        add_action( 'load-post.php',     array( $this, 'init_metabox' ) );
        add_action( 'load-post-new.php', array( $this, 'init_metabox' ) );


        add_action( 'init', array( $this, 'update_images_from_shopify' ) );
    }

    /*
    * Dashboard 
    */
    public function add_plugin_page(){
        add_menu_page('SH + WC', 'Shopify + WC', 'manage_options', 'sh-wc-setting-page', array( $this, 'admin_page' ), 'dashicons-admin-links');
    }

    public function admin_page(){

        $this->options = get_option( 'sh_wc_options' );
        $shopify_api_key = (isset($this->options['shopify_api_key'])) ? $this->options['shopify_api_key'] : '';

        ?>
        <style>
        table {
            margin: 15px 0px;
            width:100%;
        }
        table td {
            border: 1px solid rgba(51, 51, 51, 0.45);
            padding: 10px;
        }
        </style>
        <div class="wrap">
            <h1>Shopify + Woocommerce Integration Setting</h1>
            <div class="endpoint-url">
                <p><?php echo $this->generate_shopify_endpoint($shopify_api_key); ?></p>
            </div>
            <pre> Sample Shopify API + Domain : xxxxxx@xxxx.myshopify.com</pre>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'my_option_group' );
                do_settings_sections( 'sh-wc-setting-page' );
                submit_button();
            ?>
            </form>
            <hr>
            <h2>References</h2>
            <a href="https://help.shopify.com/en/api/reference">https://help.shopify.com/en/api/reference</a>
            <table>
                <tr>
                    <td>Products</td>
                    <td><?php echo $this->generate_shopify_endpoint($shopify_api_key); ?>admin/products.json?fields=id,images,title</td>
                </tr>
                <tr>
                    <td>Cron Job</td>
                    <td><?php echo home_url(); ?>?update-shopify-products-images=run</td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function page_init(){        
        register_setting(
            'my_option_group', // Option group
            'sh_wc_options'
        );

        add_settings_section(
            'setting_section_id', // ID
            'Configurations', // Title
            array( $this, 'print_section_info' ), // Callback
            'sh-wc-setting-page' // Page
        );  

        add_settings_field(
            'shopify_api_key', // ID
            'Shopify API Key + Domain', // Title 
            array( $this, 'shopify_api_key_callback' ), // Callback
            'sh-wc-setting-page', // Page
            'setting_section_id' // Section           
        );      
    }


    public function print_section_info()
    {
        print 'Enter your settings below:';
    }


    public function shopify_api_key_callback()
    {
        ?>
        <input style="width: 100%;" type="text" id="shopify_api_key" name="sh_wc_options[shopify_api_key]" value="<?php echo isset( $this->options['shopify_api_key'] ) ? esc_attr( $this->options['shopify_api_key']) : ''; ?>" />
        <?php
    }

    /*
    * Metabox 
    */
    public function init_metabox() {
        add_action( 'add_meta_boxes', array( $this, 'add_metabox'  )        );
        add_action( 'save_post', array( $this, 'save_metabox' ), 10, 2 );
    }
    public function add_metabox() {
        add_meta_box( 'istw-meta-box',  __( 'Shopify Data', 'istw' ), array( $this, 'render_metabox' ), 'product', 'advanced', 'default');
    }

    public function render_metabox( $post ) {

        wp_nonce_field( 'custom_nonce_action', 'custom_nonce' );
        
        $this->options = get_option( 'sh_wc_options' );
        $shopify_api_key = (isset($this->options['shopify_api_key'])) ? $this->options['shopify_api_key'] : '';
        $shopify_products = $this->generate_shopify_endpoint($shopify_api_key) . 'admin/products.json?fields=id,title';
        $response = wp_remote_get( $shopify_products );
        
        $shopify_product_id = get_post_meta($post->ID,'shopify_product_id', true);
        

        if ( is_array( $response ) ) {
            $body = $response['body'];
            $data = json_decode($body);
        ?>

            <label>Shopify Product</label>
            <select style="width: 100%;" name="shopify-product-id">
                <option value="">Please choose here...</option>
                <?php foreach ($data->products as $product){ ?>
                <option value="<?php echo $product->id; ?>" <?php echo (isset($shopify_product_id) && $shopify_product_id == $product->id) ? 'selected': ''; ?>><?php echo $product->title; ?></option>
                <?php } ?>
            </select>


            <label>Main Product</label>
            <select style="width: 100%;" name="wordpress-product-id">
            <?php

            // For WPML
            if ( function_exists('icl_object_id') ) {
                global $sitepress;
                $sitepress->switch_lang('en');
            }

            $wordpress_product_id = get_post_meta($post->ID,'wordpress_product_id', true);
            $query = new WP_Query(
                array(
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'order' => 'ASC',
                    'orderby' => 'title'
                )
            );
            if ($query->have_posts()): 
                ?>
                <option value="">Please choose here...</option>
                <?php
                while ($query->have_posts()){ $query->the_post(); 
                ?>
                <option value="<?php echo get_the_ID(); ?>" <?php echo (isset($wordpress_product_id) && $wordpress_product_id == get_the_ID()) ? 'selected': ''; ?>><?php echo get_the_title(); ?></option>
                <?php
                }
                wp_reset_postdata();
            endif;
            ?>
            </select>        
        <?php 
        }
    }

    public function save_metabox( $post_id, $post ) {
        // Add nonce for security and authentication.
        $nonce_name   = isset( $_POST['custom_nonce'] ) ? $_POST['custom_nonce'] : '';
        $nonce_action = 'custom_nonce_action';
 
        // Check if nonce is set.
        if ( ! isset( $nonce_name ) ) {
            return;
        }
 
        // Check if nonce is valid.
        if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
            return;
        }
 
        // Check if user has permissions to save data.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
 
        // Check if not an autosave.
        if ( wp_is_post_autosave( $post_id ) ) {
            return;
        }
 
        // Check if not a revision.
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ($_REQUEST['shopify-product-id']){
            update_post_meta($post_id,'shopify_product_id',$_REQUEST['shopify-product-id']);
        }
        if ($_REQUEST['wordpress-product-id']){
            update_post_meta($post_id,'wordpress_product_id',$_REQUEST['wordpress-product-id']);
        }
    }


    /* 
    * Updater
    */
    
    public function update_images_from_shopify(){
        if ($_GET['update-shopify-products-images']){

            $params = array(
                'post_type' => 'product',
                'meta_query' => array(
                    array(
                        'key' => 'shopify_product_id', 
                        'compare' => 'EXIST',
                    )
                ),  
                'posts_per_page' => 5 

            );
            $wc_query = new WP_Query($params);
            global $post, $product;

            if( $wc_query->have_posts() ) {

                while( $wc_query->have_posts() ) {

                    $wc_query->the_post();
                    $shopify_product_id = get_post_meta(get_the_ID(),'shopify_product_id', true);

                    if ($shopify_product_id):

                        $images = $this->get_shopify_product_images($shopify_product_id);

                        $shopify_images = array();
                        foreach ($images as $image){
                            $shopify_images[] = $image->src;
                        }
                        update_post_meta(get_the_ID(),'shopify_product_images',$shopify_images);

                    endif;
                    

                } 

            }else{
                echo "nothing found";
            }

            wp_reset_postdata();


            exit;
        }
    }

    /*
    * Helper
    */
    public function get_shopify_product_images($shopify_product_id){

        $this->options = get_option( 'sh_wc_options' );
        $shopify_api_key = (isset($this->options['shopify_api_key'])) ? $this->options['shopify_api_key'] : '';
        $shopify_products = $this->generate_shopify_endpoint($shopify_api_key) . 'admin/products.json?ids=' . $shopify_product_id . '&fields=images';
        $response = wp_remote_get( $shopify_products );

        $body = $response['body'];
        $data = json_decode($body);
        return $data->products[0]->images;
    }
    
    public function generate_shopify_endpoint($api_key){
        return 'https://'  . $api_key . '/';
    }

    

}
$shopify_woocommerce_integration = new ShopifyWoocommerceIntegration();

    