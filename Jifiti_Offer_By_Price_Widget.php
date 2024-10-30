<?php

defined('ABSPATH') || exit;

add_action('wp_head','include_jifiti_widget_script', 10);

// Get the widget URL based on the environment selected in the admin setting.
function jifiti_getWidgetUrl() {
    $options = get_option( 'jobpw_options' );
    return $options['jobpw_js_widget_url'];
}

// Import the jifiti script
function include_jifiti_widget_script () {
    ?>
    <script src="<?php echo esc_attr(jifiti_getWidgetUrl()) ?>"></script>
    <?php
}

$newCart = false;
// Initialize the key and set on load price.
add_action('woocommerce_before_shop_loop', 'jifiti_init_offer_by_price_widget', 10 );
add_action('woocommerce_single_product_summary', 'jifiti_init_offer_by_price_widget', 10 );
add_action('woocommerce_before_cart', 'jifiti_init_offer_by_price_widget', 10 );
add_action('woocommerce_blocks_enqueue_cart_block_scripts_after', 'jifiti_init_offer_by_price_widget', 10 );
function jifiti_init_offer_by_price_widget() {
    if(current_filter() === 'woocommerce_blocks_enqueue_cart_block_scripts_after'){
        include_jifiti_widget_script();
        global $newCart;
        $newCart = true;
    }
    $options = get_option( 'jobpw_options' );
    jifiti_select_price_for_product_script_function();
    ?>
    <?php if (!empty($options['jobpw_pm_auth_token'])): ?>
    <script>
        const client = jifiti.init(`<?php echo esc_attr($options['jobpw_pm_auth_token']);?>`);
    </script>
    <?php endif; ?>
    <?php
}

function jifiti_select_price_for_product_script_function() {
    ?>
    <script>
        <?php
            echo "const thousand_separator = '" . get_option('woocommerce_price_thousand_sep') . "';";
            echo "const decimal_separator = '" . get_option('woocommerce_price_decimal_sep') . "';";
        ?>
        function get_price_with_currency(productItem) {
            let priceWithCurrency = productItem?.querySelectorAll('ins .woocommerce-Price-amount.amount')[0]?.textContent
            if (priceWithCurrency === undefined) {
                priceWithCurrency = productItem?.querySelectorAll('.woocommerce-Price-amount.amount')[0]?.textContent
            }
            return priceWithCurrency;
        }
        function select_price_for_product(productItem) {
            let quantityItem = productItem.querySelector('.quantity .input-text.qty');
            let quantityValue = quantityItem?.value
            if(quantityItem === null) {
                quantityValue = 1
            }
            let priceWithCurrency = get_price_with_currency(productItem);
            if (productItem.querySelectorAll('.woocommerce-variation-price').length || productItem.classList?.contains('product-type-variable')) {
                priceWithCurrency = get_price_with_currency(productItem.querySelector('.woocommerce-variation-price'));
            }
            if (priceWithCurrency === undefined) {
                priceWithCurrency = productItem.querySelectorAll('.woocommerce-Price-amount.amount')[0]?.textContent
            }

            let currency = productItem.querySelector('.woocommerce-Price-amount.amount .woocommerce-Price-currencySymbol')?.textContent
            
            let priceWithoutCurrency = priceWithCurrency?.split('').map(char => {
                switch(char) {
                    case currency:
                    case thousand_separator:
                        return '';
                    case decimal_separator:
                        return '.';
                    default:
                        return char;
                }
            }).join('');

            let priceValue = 1;
            
            if (priceWithoutCurrency) {
                priceValue = parseFloat(priceWithoutCurrency);
            }
            return ( quantityValue * priceValue)
        }
    </script>
    <?php
}

// Get the jifiti configuration.
function jifiti_get_product_configuration($price) {
    $options = get_option( 'jobpw_options' );
    $link_text = array_key_exists('jobpw_link_text', $options) ? $options['jobpw_link_text'] : null;
    $link_behavior = array_key_exists('jobpw_link_behavior', $options) ? $options['jobpw_link_behavior'] : null;
    $template_name = array_key_exists('jobpw_template_name', $options) ? $options['jobpw_template_name'] : null;

    if (is_string($price)) {
        $price = (double)filter_var($price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    $configuration = array (
        "price" => $price,
    );
    if (!empty($link_behavior)) {
        $configuration['modal'] =  $link_behavior;
    }
    if (!empty($link_text)) {
        $configuration['linkText'] =  $link_text;
    }
    if (!empty($template_name)) {
        $configuration['template'] =  $template_name;
    }

    $configuration['initiateFlow'] =  true;
    if (!empty($link_text) && strtolower($link_text) == "learn more") {
        $configuration['initiateFlow'] =  false;
    }
    $configuration['initiateFlowRequest'] = [];
    $configuration['initiateFlowRequest']['CompletionMethod'] = "TransferCard";
    
    return $configuration;
}

function init_and_get_offer_price($price, $product_id = '', $source_page_type = '', $showLink = false){
 
    $container_id = 'OfferByPrice';
    if ($product_id) {
        $container_id = $container_id . '_' . $product_id;
    }
    $configuration = jifiti_get_product_configuration($price);
    $configuration['showLink'] = $showLink;
    $configuration['selectorId'] = $container_id;
    $configuration['initiateFlowRequest']['SourcePageType'] = $source_page_type;
    ?>
    <div id="<?php echo esc_attr($container_id);?>"></div>
    <script>
    var configuration = <?php echo json_encode($configuration); ?>;
    <?php
        $options = get_option( 'jobpw_options' );
        ?>
        client.ui.show(configuration);
    </script>
    <?php
}

// Offer price widget for all PDP's type except Grouped products.
add_action('woocommerce_before_add_to_cart_form' , 'jifiti_offer_price_widget_pdp');

function jifiti_offer_price_widget_pdp() {
    $options = get_option( 'jobpw_options' );
    global $product;
    if ( !empty($options['jobpw_active']) && $options['jobpw_active'] === 'active' && !empty($options['jobpw_pdp']) && $options['jobpw_pdp'] === 'product_description' && !$product->is_type('grouped')) {
        $product_id = $product->get_ID();
        $price = $product->get_price();
        $container_id = 'OfferByPrice_' . $product_id;
        $showLink = false;
        if(!empty($options['jobpw_use_link_pdp']) && $options['jobpw_use_link_pdp'] === 'true'){
            $showLink = true;
        }
        init_and_get_offer_price($price, $product_id, "PIP", $showLink);
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            function update_offer_price_widget() {
                if(document.querySelector('.entry-summary') != null) {
                    var priceValue = select_price_for_product(document.querySelector('.entry-summary'));
                }
                else{
                    var priceValue = select_price_for_product(document.querySelector('.wp-block-column.is-layout-flow:has(.wp-block-woocommerce-product-price)'));
                }
                configuration.price = priceValue;
                configuration.selectorId = '<?php echo esc_attr($container_id);?>';
                client.ui.show(configuration);
                //OfferByPrice.setPrice('<?php //echo esc_attr($container_id);?>', priceValue);
            } 

            jQuery( 'input.variation_id' ).change( function(){
                if( jQuery.trim( jQuery( 'input.variation_id' ).val() )!='' ) {
                    update_offer_price_widget();
                }
            });

            document.querySelectorAll('.input-text.qty')[0].addEventListener('change', (event) => {
                update_offer_price_widget();
            });
        });
        </script>
        <?php
    }
}

// Offer price widget for PLP page.
//add_action('woocommerce_after_shop_loop_item' , 'jifiti_offer_price_widget_shop');
add_action('woocommerce_after_shop_loop_item_title' , 'jifiti_offer_price_widget_shop');

function jifiti_offer_price_widget_shop() {
    $options = get_option( 'jobpw_options' );
    if (!empty($options['jobpw_active']) && $options['jobpw_active'] === 'active' && !empty($options['jobpw_plp']) && $options['jobpw_plp'] === 'product_listing' && is_shop()) {
        global $product;
        $product_id = $product->get_ID();
        $price = $product->get_price();
        $showLink = false;
        if(!empty($options['jobpw_use_link_plp']) && $options['jobpw_use_link_plp'] === 'true'){
            $showLink = true;
        }
        init_and_get_offer_price($price, $product_id, "PCP", $showLink);
    }
}


// Offer price widget for Grouped Products PDP.
add_action('woocommerce_grouped_product_list_after', 'jifiti_update_offer_price_widget_grouped_products_pdp', 10, 3);

function jifiti_update_offer_price_widget_grouped_products_pdp($grouped_product_columns, $quantites_required, $product) {
    global $product;
    $options = get_option( 'jobpw_options' );
    if (!empty($options['jobpw_active']) && $options['jobpw_active'] === 'active' && !empty($options['jobpw_pdp']) && $options['jobpw_pdp'] === 'product_description') {
        $price = $product->get_price();
        $container_id = 'OfferByPrice';
        $showLink = false;
        if(!empty($options['jobpw_use_link_pdp']) && $options['jobpw_use_link_pdp'] === 'true'){
            $showLink = true;
        }
        init_and_get_offer_price($price, '', "PIP", $showLink);
        ?>
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                function update_offer_price_widget_grouped_product() {
                    var totalPrice = 0;
                    jQuery('.woocommerce-grouped-product-list .woocommerce-grouped-product-list-item').map(
                        (index, item) => {
                            totalPrice = totalPrice + select_price_for_product(item);
                        }
                    );
                    configuration.selectorId = 'OfferByPrice';
                    configuration.price = totalPrice;
                    client.ui.show(configuration);
                }
                update_offer_price_widget_grouped_product();
                document.querySelectorAll('.input-text.qty').forEach((item) => {
                    item.addEventListener('change', (event) => {
                        update_offer_price_widget_grouped_product();
                    });
                })
                
            });
            </script>

        <?php
    }
}

// Configure the update offer widget at Cart page
add_action( 'woocommerce_cart_totals_before_order_total', 'jifiti_update_cart_offer_price_widget', 20, 4 );

function jifiti_update_cart_offer_price_widget () {
    $options = get_option( 'jobpw_options' );
    if (!empty($options['jobpw_active']) && $options['jobpw_active'] === 'active' && !empty($options['jobpw_cart']) && $options['jobpw_cart'] === 'cart') {
        global $woocommerce;
        $total = WC()->cart->total;
        $showLink = false;
        if(!empty($options['jobpw_use_link_cart']) && $options['jobpw_use_link_cart'] === 'true'){
            $showLink = true;
        }
        init_and_get_offer_price($total, '', 'Cart', $showLink);
        ?><script>
        document.addEventListener("DOMContentLoaded", function() {
            jQuery( document.body ).on( 'updated_cart_totals', function() {
                var container_id = 'OfferByPrice';
                let priceWithCurrency = document.querySelector('.order-total .amount')?.textContent
                let currency = document.querySelector('.order-total .amount .woocommerce-Price-currencySymbol')?.textContent
                let priceValue = parseFloat(priceWithCurrency?.replaceAll(currency, '')?.replaceAll(',', ''));
                configuration.price = priceValue;
                configuration.selectorId = container_id;
                client.ui.show(configuration);
            });
        });
        </script><?php
    }
}

add_action('wp_body_open', 'quantityChange');
function quantityChange(){
    global $newCart;
    if(is_cart() && $newCart){
        $options = get_option( 'jobpw_options' );
        if (!empty($options['jobpw_active']) && $options['jobpw_active'] === 'active' && !empty($options['jobpw_cart']) && $options['jobpw_cart'] === 'cart') {
            global $woocommerce;
            $total = WC()->cart->total;
            $showLink = false;
            if(!empty($options['jobpw_use_link_cart']) && $options['jobpw_use_link_cart'] === 'true'){
                $showLink = true;
            }
            init_and_get_offer_price($total, '', 'Cart', $showLink);
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    jQuery("#OfferByPrice").hide();
                    var refreshIntervalId = setInterval(function () {
                        if (jQuery('.wc-block-components-totals-item.wc-block-components-totals-footer-item').length) {
                            jQuery(".wc-block-components-totals-item.wc-block-components-totals-footer-item").attr('id', 'totals-footer-item');
                            jQuery(".wc-block-components-totals-item.wc-block-components-totals-footer-item").append(jQuery("#OfferByPrice"));
                            jQuery("#OfferByPrice").show();
                            clearInterval(refreshIntervalId);
                        }
                    }, 1000);
                    const observer = new PerformanceObserver((list) => {
                        for (const entry of list.getEntries()) {
                          if (entry.initiatorType === "fetch") {
                            jifiti_update_cart_price_widget();
                          }
                        }
                    });

                    observer.observe({
                      entryTypes: ["resource"]
                    });
                }, false);
            
                function jifiti_update_cart_price_widget(){
                    var store = window.wp.data.select( 'wc/store/cart' );
                    var cartData = store.getCartData();
                    var container_id = 'OfferByPrice';
                    configuration.price = parseFloat(cartData.totals.total_price)/Math.pow(10,cartData.totals.currency_minor_unit);
                    configuration.selectorId = container_id;
                    client.ui.show(configuration);
                }
            </script>
            <?php
        }
    }
}

