jQuery( function( $ ) {
    $(document).ready(function () {
        window.mobileCheck = function() {
            let check = false;
            (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))) check = true;})(navigator.userAgent||navigator.vendor||window.opera);
            return check;
        };

        $(document.body).on('updated_checkout payment_method_selected', function() {
            var placeOrdeButton = $("#place_order");
            if (document.querySelector('input[name="payment_method"]:checked').value != "jifitipayment") {
                placeOrdeButton.removeClass("hide-element");
                $("#jifiti_place_order").addClass("hide-element");
                return;
            }
    
            placeOrdeButton.addClass("hide-element");
    
            if (!$("#jifiti_place_order").length) {
                placeOrdeButton.after(
                    '<button type="submit" class="button alt" id="jifiti_place_order" value="Place order" data-value="Place order">Place order</button>'
                )
            }
    
            $("#jifiti_place_order").removeClass("hide-element");
        });
    
    
        $('body').on('click', '#jifiti_place_order', function(e) {
            e.preventDefault();
            
            var $form = $(".checkout.woocommerce-checkout");

            $form.addClass( 'processing' );
            blockOnSubmit($form);

            var isMobile = window.mobileCheck();
            var windowReference = null;

            if (isMobile && $("#windowBehavior").val() == "newtab") {
                windowReference = window.open();
            }

            $.ajax({
                type:		'POST',
                url:		wc_checkout_params.checkout_url,
                data:		$form.serialize(),
                dataType:   'json',
                success:	function( result ) {
                    $form.removeClass( 'processing' ).unblock();
                    if ( 'success' === result.result && $form.triggerHandler( 'checkout_place_order_success', result ) !== false ) {
                        if (result.redirect && (result.redirect.indexOf("https://") !== -1 || result.redirect.indexOf("http://") !== -1)) {
                            if ($("#windowBehavior").val() == "newtab") {
                                $("#paymentGatewayURL").val(result.redirect);

                                if (windowReference) {
                                    windowReference.location = result.redirect;
                                }
                                else {
                                    $("#newTabWindow").click();
                                }
                            }
                            else if ($("#windowBehavior").val() == "sametab") {
                                $form.removeClass( 'processing' ).unblock();
                                window.location = result.redirect;
                            }
                            else {
                                $form.removeClass( 'processing' ).unblock();
                                $("#paymentGatewayModal .modal-content").append(
                                    $("<iframe>").attr("id", "paymentGatewayContianer")				
                                );

                                $("#paymentGatewayContianer")
                                    .attr("src", result.redirect)
                                    .attr("frameborder", "0")
                                    .attr("gesture", "media");

                                const eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
                                const eventer = window[eventMethod];
                                const messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";
                                const hostname = new URL(result.redirect).hostname;

                                eventer(messageEvent, applicationResponseHandler.bind(this, hostname, result.CallbackURL), { passive: true, capture: true });
                                
                                $("#paymentGatewayModal .close").on("click", function () {
                                    $("#paymentGatewayModal").hide();
                                    $("#paymentGatewayContianer").remove();
                                }); 
                                $("#paymentGatewayModal").show();
                            }
                        }
                        else {
                            submit_error( 'There are an error, Please try again later.' );
                        }
                    }
                    else {
                        if (result.messages) {
                            submit_error( result.messages);
                        }
                        else {
                            submit_error( 'There are an error, Please try again later.' );
                        }
                    }
                },
                error: function() {
                    $form.removeClass( 'processing' ).unblock();
                    submit_error( 'There are an error, Please try again later.' );
                }
            });
        });
    });

    function submit_error ( error_message ) {
        var $checkout_form = $( 'form.checkout' );
        $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
        $checkout_form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len
        $checkout_form.removeClass( 'processing' ).unblock();
        $checkout_form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).trigger( 'blur' );
        scroll_to_notices();
        $( document.body ).trigger( 'checkout_error' , [ error_message ] );
    }

    function scroll_to_notices() {
        var scrollElement           = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

        if ( ! scrollElement.length ) {
            scrollElement = $( 'form.checkout' );
        }
        $.scroll_to_notices( scrollElement );
    }

    function applicationResponseHandler (hostname, callbackUrl, e) {
        e.stopPropagation();

        // Check if the response comes from same popup
        if (!e.origin.includes(hostname)) {
            return;
        }

        var action = e.data;

        switch (action) {
            case "closeIframe":
                $("#paymentGatewayModal").hide();
                $("#paymentGatewayContianer").remove();
                $.blockUI({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
                window.location.href = callbackUrl;
                break;
        }
    }

    function blockOnSubmit( $form ) {
        var isBlocked = $form.data( 'blockUI.isBlocked' );

        if ( 1 !== isBlocked ) {
            $form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }
    }
});
 