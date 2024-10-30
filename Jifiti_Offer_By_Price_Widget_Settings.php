<?php

defined('ABSPATH') || exit;

/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */
 
/**
 * custom option and settings
 */
function jobpw_settings_init() {
    // Register a new setting for "jobpw" page.
    register_setting( 'jobpw', 'jobpw_options' );
    
    $configuration_list = array(
        "active" => "Active",
        "pm_environment" => "Environment",
        "region" => "Region",
        "store_identifier" => "Store Identifier (optional)",
        "allowed_pages" => "Select Active Pages",
        "pm_auth_token" => "Client Auth Token",
        "use_link" => "Use Link?",
        "link_text" => "Link text",
        "link_behavior" => "Link behavior (optional)",
        "template_name" => "Widget Template",
        "js_widget_url" => "JS Widget URL"
    );

    foreach ( $configuration_list as $key => $value ) {
        add_settings_field(
            'jobpw_field_' . $key, // As of WP 4.6 this value is used only internally.
                                    // Use $args' label_for to populate the id inside the callback.
                __( $value, 'jobpw' ),
            'jobpw_field_' . $key,
            'jobpw',
            'jobpw_section_developers',
            array(
                'label_for'         => 'jobpw_' . $key,
                'class'             => 'jobpw_row',
                'jobpw_custom_data' => 'custom',
            )
        );

    }

    // Register a new section in the "jobpw" page.
    add_settings_section(
        'jobpw_section_developers',
        __( 'Promotion Message', 'jobpw' ), 'jobpw_section_developers_callback',
        'jobpw'
    );    
}

/**
 * Register our jobpw_settings_init to the admin_init action hook.
 */
add_action( 'admin_init', 'jobpw_settings_init' );
 

function jobpw_section_developers_callback( $args ) {
    ?>
    <!-- <p id="<?php // echo esc_attr( $args['id'] ); ?>"><?php // esc_html_e( 'Follow the white rabbit.', 'jobpw' ); ?></p> -->
    <?php
}

function jobpw_field_text( $args, $description ) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option( 'jobpw_options' );
    ?>
    <p>
        <input  name="jobpw_options[<?php echo esc_attr( $args['label_for'] ); ?>]" type="text" id="<?php echo esc_attr( $args['label_for']); ?>" value="<?php echo esc_attr($options[ $args['label_for'] ])  ?>" <?php echo $args['label_for'] == 'jobpw_js_widget_url' ? 'disabled' : '' ?> >
    </p>
    <p class="description">
        <?php esc_html_e( $description, 'jobpw' ); ?>
    </p>
    <?php
}

function jobpw_field_text_area( $args, $description ) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option( 'jobpw_options' );
    ?>
    <p>
        <textarea name="jobpw_options[<?php echo esc_attr( $args['label_for'] ); ?>]" id="<?php echo esc_attr( $args['label_for']); ?>" rows="4" cols="50" ><?php echo esc_attr($options[ $args['label_for'] ]) ?></textarea>            
    </p>
    <p class="description">
        <?php esc_html_e( $description, 'jobpw' ); ?>
    </p>
    <?php
}

function jobpw_field_select( $args, $list_options, $description, $disable_options = []) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option( 'jobpw_options' );
    ?>
    <select
            id="<?php echo esc_attr( $args['label_for'] ); ?>"
            data-custom="<?php echo esc_attr( $args['jobpw_custom_data'] ); ?>"
            name="jobpw_options[<?php echo esc_attr( $args['label_for'] ); ?>]">
    <?php
    
    foreach ( $list_options as $value => $label ) {
        $doption = "";
        if (in_array($value, $disable_options)) {
            $doption = "disabled";
        }
        ?>
        <option value="<?php echo esc_attr($value); ?>" <?php echo isset( $options[ $args['label_for'] ] ) ? ( esc_attr(selected( $options[ $args['label_for'] ], $value, false ) )) : ( '' ); ?> <?php echo $doption; ?>>
            <?php esc_html_e( $label, 'jobpw' ); ?>
        </option>
        <?php
    }
    ?>
    </select>
    <p class="description">
        <?php esc_html_e( $description, 'jobpw' ); ?>
    </p>
    <?php

}

function jobpw_field_pm_auth_token( $args ) {
    jobpw_field_text($args, 'Authorization token to initialize the promotional message widget.');
}

function jobpw_field_link_text( $args ) {
    $list_options = array("Learn more" => "Learn more", "Check Eligibility" => "Check Eligibility", "Prequalify" => "Prequalify");
    $description = "Text displayed for the link. This is the link that will open the application flow or help page - 'Learn More' is the default value.";
    jobpw_field_select($args, $list_options, $description );
}

function jobpw_field_store_identifier( $args ) {
    jobpw_field_text($args, 'This is the ID of your store for reporting purposes.');
}

function jobpw_field_js_widget_url( $args ) {
    jobpw_field_text($args, 'Widget JS Library URL.');
}

function jobpw_field_allowed_pages( $args ) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option( 'jobpw_options' );
    ?>
    <p>
        <label for="jobpw_options[<?php echo esc_attr( 'jobpw_pdp' ); ?>]" >
            <input  name="jobpw_options[<?php echo esc_attr( 'jobpw_pdp' ); ?>]" type="checkbox" id="jobpw_options[<?php echo esc_attr( 'jobpw_pdp' ); ?>]" <?php echo isset( $options[ 'jobpw_pdp' ] ) ? ( esc_attr(checked( $options[ 'jobpw_pdp' ], 'product_description', false ) )) : ( '' ); ?> value="product_description" >
            Product page
        </label>
    </p>
    <p>
        <label for="jobpw_options[<?php echo esc_attr( 'jobpw_cart' ); ?>]" >
            <input  name="jobpw_options[<?php echo esc_attr( 'jobpw_cart' ); ?>]" type="checkbox" id="jobpw_options[<?php echo esc_attr( 'jobpw_cart' ); ?>]" <?php echo isset( $options[ 'jobpw_cart' ] ) ? esc_attr(( checked( $options[ 'jobpw_cart' ], 'cart', false ) )) : ( '' ); ?> value="cart" >
            Cart page
        </label>
    </p>
    <p class="description">
        <?php esc_html_e( "This will define in which pages the promotional widget will be shown.", 'jobpw' ); ?>
    </p>
    <?php
}
/*
function jobpw_field_allowed_pages( $args ) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option( 'jobpw_options' );
    ?>
    <p>
        <label for="jobpw_options[<?php echo esc_attr( $args['label_for'][0] ); ?>]" >
            <input  name="jobpw_options[<?php echo esc_attr( $args['label_for'][0] ); ?>]" type="checkbox" id="jobpw_options[<?php echo esc_attr( $args['label_for'][0]); ?>]" <?php echo isset( $options[ $args['label_for'][0]] ) ? ( checked( $options[ $args['label_for'][0]], 'product_listing', false ) ) : ( '' ); ?> value="product_listing" >
            Product Listing
        </label>
    </p>
    <p>
        <label for="jobpw_options[<?php echo esc_attr( $args['label_for'][1] ); ?>]" >
            <input  name="jobpw_options[<?php echo esc_attr( $args['label_for'][1] ); ?>]" type="checkbox" id="jobpw_options[<?php echo esc_attr( $args['label_for'][1]); ?>]" <?php echo isset( $options[ $args['label_for'][1] ] ) ? ( checked( $options[ $args['label_for'][1] ], 'product_description', false ) ) : ( '' ); ?> value="product_description" >
            Product Description
        </label>
    </p>
    <p>
        <label for="jobpw_options[<?php echo esc_attr( $args['label_for'][2] ); ?>]" >
            <input  name="jobpw_options[<?php echo esc_attr( $args['label_for'][2] ); ?>]" type="checkbox" id="jobpw_options[<?php echo esc_attr( $args['label_for'][2]); ?>]" <?php echo isset( $options[ $args['label_for'][2] ] ) ? ( checked( $options[ $args['label_for'][2] ], 'cart', false ) ) : ( '' ); ?> value="cart" >
            Cart
        </label>
    </p>
    <p class="description">
        <?php esc_html_e( "Select the pages that the promotional message widget should show within them.", 'jobpw' ); ?>
    </p>
    <?php
}

*/

function jobpw_field_active( $args ) {
    $list_options = array("active" => "Yes", "inActive" => "No");
    $description = "Select Yes/No to Active/Inactive the promotion message widget.";
    jobpw_field_select($args, $list_options, $description );
}

function jobpw_field_pm_environment( $args ) {
    $list_options = array("uat" => "UAT/Staging", "prod" => "Production");
    $description = "Select UAT/staging environment for development or testing stores, and production env for your online live store.";
    jobpw_field_select($args, $list_options, $description);
}

function jobpw_field_region( $args ) {
    $list_options = array("US" => "North America", "EU" => "Europe");
    $description = "Select your closest region.";
    jobpw_field_select($args, $list_options, $description);
}

function jobpw_field_use_link( $args ) {
    // Get the value of the setting we've registered with register_setting()
    $options = get_option( 'jobpw_options' );
    ?>
    <p>
        <label for="jobpw_options[<?php echo esc_attr( 'jobpw_use_link_pdp' ); ?>]" >
            <input  name="jobpw_options[<?php echo esc_attr( 'jobpw_use_link_pdp' ); ?>]" type="checkbox" id="jobpw_options[<?php echo esc_attr( 'jobpw_use_link_pdp' ); ?>]" <?php echo isset( $options[ 'jobpw_use_link_pdp' ] ) ? ( esc_attr(checked( $options[ 'jobpw_use_link_pdp' ], 'true', false ) )) : ( '' ); ?> value="true" >
            Product page
        </label>
    </p>
    <p>
        <label for="jobpw_options[<?php echo esc_attr( 'jobpw_use_link_cart' ); ?>]" >
            <input  name="jobpw_options[<?php echo esc_attr( 'jobpw_use_link_cart' ); ?>]" type="checkbox" id="jobpw_options[<?php echo esc_attr( 'jobpw_use_link_cart' ); ?>]" <?php echo isset( $options[ 'jobpw_use_link_cart' ] ) ? esc_attr(( checked( $options[ 'jobpw_use_link_cart' ], 'true', false ) )) : ( '' ); ?> value="true" >
            Cart page
        </label>
    </p>
    <p class="description">
        <?php esc_html_e( "This will define if the link will be shown on the promotional widget component or not", 'jobpw' ); ?>
    </p>
    <?php
}

function jobpw_field_template_name( $args ) {
    $list_options = array(
        "info" => "Default (Recommended)",
        "spbar" => "SplitPay Bar",
        "cfRange" => "Financing Slider"
    );

    $wc_gateway = new WC_Jifiti_Payment_Gateway();
    
    $disable_options = [];
    
    if (!empty($wc_gateway->allowed_product_types)) {
        if (!in_array("SplitPayments", $wc_gateway->allowed_product_types)) {
            $disable_options[] = "spbar";
        }
    }
    else {
        $disable_options[] = "spbar";
    }

    $description = "The template to be used for the promotional messaging widget show in your product page, category product box and in the cart.";
    jobpw_field_select($args, $list_options, $description, $disable_options);
}

function jobpw_field_link_behavior( $args ) {
    $list_options = array(
        "iframe" => "LightBox (Recommended)",
        "window" => "New Window"
    );
    $description = 'Link opened as new window or iframe lightbox';

    jobpw_field_select($args, $list_options, $description);

}



/**
 * Add the top level menu page.
 */
function jobpw_options_page() {
    add_menu_page(
        'Jifiti Offer By Price',
        'Jifiti Offer By Price Widget',
        'manage_options',
        'jobpw',
        'jobpw_options_page_html'
    );
}
 
 
/**
 * Register our jobpw_options_page to the admin_menu action hook.
 */
add_action( 'admin_menu', 'jobpw_options_page' );
 
 
/**
 * Top level menu callback function
 */
function jobpw_options_page_html() {
    // check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
  
    // check if the user have submitted the settings
    // WordPress will add the "settings-updated" $_GET parameter to the url
    if ( isset( $_GET['settings-updated'] ) ) {
        // add settings saved message with the class of "updated"
        add_settings_error( 'jobpw_messages', 'jobpw_message', __( 'Settings Saved', 'jobpw' ), 'updated' );
    }
 
    // show error/update messages
    settings_errors( 'jobpw_messages' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "jobpw"
            settings_fields( 'jobpw' );
            // output setting sections and their fields
            // (sections are registered for "jobpw", each field is registered to a specific section)
            do_settings_sections( 'jobpw' );
            // output save settings button
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}