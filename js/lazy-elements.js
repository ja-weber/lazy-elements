var lazyElements = {
    loadedElements: [],
    isVisible: function (elm) {
        var vpH = $(window).height(),
            st = $(window).scrollTop(),
            y = $(elm).offset().top,
            elementHeight = $(elm).height();
        return ((y < (vpH + st)) && (y > (st - elementHeight)));
    },
    startWatching: function (contentId, ajaxParams, onlyIfVisible) {
        if (!onlyIfVisible || lazyElements.isVisible(jQuery("#ajax-placeholder-" + contentId))) {
            lazyElements.loadElement(contentId, ajaxParams);
        }

        jQuery(document).scroll(function () {

            if (!onlyIfVisible) {
                //should be loaded already.
                return;
            }

            let placeholder = jQuery("#ajax-placeholder-" + contentId);
            if (placeholder.length === 0) {
                return;
            }
            if (!lazyElements.isVisible(placeholder)) {
                return;
            }

            lazyElements.loadElement(contentId, ajaxParams);
        });
    },
    loadElement: function (contentId, ajaxParams) {
        let placeholder = jQuery('#ajax-placeholder-' + contentId);
        if (placeholder.hasClass("loaded")) {
            return;
        }
        placeholder.addClass("loaded");

        wp.ajax.post('lazy_element_action', ajaxParams).done(function (response) {
            placeholder.html(response);
        }).fail(function (response) {
            if(response && response.responseJSON && response.responseJSON.data){
                placeholder.html(response.responseJSON.data);
            }
            console.error(response, 'Error in the ajax wrapper.')
        })
    }
}
