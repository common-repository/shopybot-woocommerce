function shopybot_connect(url) {
    shopybot_redirect_to_shopybot(url);
}
function shopybot_disconnect(url) {
    var r = confirm("Are you sure to disconnect the shop and delete the bot?");
    if (r == true) {
        shopybot_redirect_to_shopybot(url);
    }
}

function shopybot_fb_connect(url) {
    shopybot_redirect_to_shopybot(url);
}
function shopybot_fb_disconnect(url) {
    var r = confirm("Are you sure to disconnect the Facebook page from your bot?");
    if (r == true) {
        shopybot_redirect_to_shopybot(url);
    }
}

function shopybot_redirect_to_shopybot(url) {
    window.location = url;
}


jQuery(document).ready(function ($) {
    $('#woocommerce_shopybot-integration_generate_export_url, #woocommerce_shopybot-woocommerce_generate_export_url').click(function () {
        generate_export_url();
    });

    function generate_export_url() {
        $('#woocommerce_shopybot-integration_generate_export_url').html('Generating, please do not close this window until file is ready')

        window.offerunlock = 'yes';
        $('#shopybot-generate-progress, .shopybot-loading').show();

        updateoffers();

        return false;
    }

    var updateoffers = function () {

        var data = {action: 'shopybot_woocommerce_ajaxUpdateOffers', unlock: offerunlock};

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (data) {
                window.offerunlock = 'no';
                if (!data.yaml_finished) {
                    updateoffers();
                } else {
                    $('#shopybot-generate-progress, .shopybot-loading').hide();
                    window.location.reload();
                }
            }
        }).done(function () {
            console.log("success");
        }).fail(function (data) {
            alert('Please try to regenerate Export file once again');
        }).always(function () {
            console.log("complete");
        });


    }


});